<?php
/**
 * 사진 처리: 업로드 리사이즈 · 썸네일(WebP/JPEG) · srcset
 *  - 썸네일은 처음 요청될 때 img.php 가 만들고 cache/thumbs 에 저장 → 이후엔 정적 파일로 바로 제공
 *  - GD 확장이 없거나 메모리가 부족하면 조용히 원본으로 대체(사이트는 항상 동작)
 */
require_once __DIR__ . '/../config.php';

const PHOTO_WIDTHS = [240, 480, 960, 1600, 2400];

function photo_root(): string { return dirname(__DIR__) . '/travel-photos'; }
function photo_gd_ok(): bool { return function_exists('imagecreatetruecolor') && function_exists('imagejpeg'); }
function photo_webp_ok(): bool { return function_exists('imagewebp'); }
function photo_client_webp(): bool { return photo_webp_ok() && stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'image/webp') !== false; }

/** 상대경로를 travel-photos 안의 실제 파일로 안전하게 변환(경로 조작 차단). 실패 시 null */
function photo_abs(string $rel): ?string {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || str_contains($rel, '..') || preg_match('/[\x00-\x1F]/', $rel)) return null;
    $root = realpath(photo_root()); $abs = realpath(photo_root() . '/' . $rel);
    if ($root === false || $abs === false || !is_file($abs)) return null;
    if (!str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) return null;
    return $abs;
}

/** [가로, 세로, 타입] — 요청 안에서 메모이즈 */
function photo_info(string $rel): ?array {
    static $memo = [];
    if (array_key_exists($rel, $memo)) return $memo[$rel];
    $abs = photo_abs($rel); $r = null;
    if ($abs && ($i = @getimagesize($abs)) && $i[0] > 0) $r = ['w' => (int)$i[0], 'h' => (int)$i[1], 'type' => (int)$i[2], 'abs' => $abs, 'mtime' => (int)@filemtime($abs), 'size' => (int)@filesize($abs)];
    return $memo[$rel] = $r;
}

function photo_thumb_file(array $info, string $rel, int $w, string $ext): string {
    // 키에 수정시각을 쓰지 않는다(PC 에서 미리 만든 썸네일과 이름이 같아야 하므로). 원본이 바뀌면 파일 크기·해상도가 달라져 새로 생성된다.
    $key = sha1($rel . '|' . $info['size'] . '|' . $info['w'] . 'x' . $info['h']);
    return rtrim(CACHE_DIR, '/\\') . '/thumbs/' . substr($key, 0, 2) . '/' . $key . '-' . $w . '.' . $ext;
}
function photo_thumb_ext(array $info, bool $webp): string {
    if ($webp) return 'webp';
    return $info['type'] === IMAGETYPE_PNG ? 'png' : 'jpg';
}

/** 화면에 쓸 URL: 캐시가 있으면 정적 파일, 없으면 img.php(생성 후 캐시). GD 가 없으면 원본. */
function photo_thumb_url(string $rel, int $w): string {
    $info = photo_info($rel);
    if (!$info || !photo_gd_ok()) return photo_url($rel);
    if ($info['w'] <= $w && $info['type'] === IMAGETYPE_JPEG && $info['w'] <= 1000) return photo_url($rel); // 이미 작은 JPEG
    $w = min(nearest_width($w), $info['w']);   // 표준 폭(240/480/960/1600/2400)으로 맞춰야 미리 만든 정적 파일에 적중한다
    $webp = photo_client_webp();
    $file = photo_thumb_file($info, $rel, $w, photo_thumb_ext($info, $webp));
    if (is_file($file)) return 'cache/thumbs/' . basename(dirname($file)) . '/' . basename($file);   // 정적 파일로 직접 제공
    return 'img.php?p=' . rawurlencode($rel) . '&w=' . nearest_width($w);
}
function nearest_width(int $w): int { foreach (PHOTO_WIDTHS as $c) if ($c >= $w) return $c; return end(PHOTO_WIDTHS); }

/** srcset 문자열(원본보다 큰 폭은 제외) */
function photo_srcset(string $rel, array $widths = [480, 960, 1600]): string {
    $info = photo_info($rel); if (!$info || !photo_gd_ok()) return '';
    $out = [];
    foreach ($widths as $w) { if ($w < $info['w']) $out[] = photo_thumb_url($rel, $w) . ' ' . $w . 'w'; }
    if (!$out || end($widths) >= $info['w']) $out[] = photo_thumb_url($rel, min($info['w'], end($widths))) . ' ' . min($info['w'], end($widths)) . 'w';
    $out = array_unique($out);
    return count($out) > 1 ? implode(', ', $out) : '';   // 후보가 하나뿐이면 srcset 불필요
}

/** <img> 태그 한 개 (srcset · 크기 속성 포함) */
function photo_img(string $rel, string $alt, array $o = []): string {
    $info = photo_info($rel);
    $w = (int)($o['w'] ?? 960);
    $a = ['src' => photo_thumb_url($rel, $w), 'alt' => $alt, 'decoding' => 'async'];
    $set = photo_srcset($rel, $o['widths'] ?? [480, 960, 1600]);
    if ($set !== '') { $a['srcset'] = $set; $a['sizes'] = $o['sizes'] ?? '100vw'; }
    if ($info && empty($o['nodim'])) { $a['width'] = $info['w']; $a['height'] = $info['h']; }
    if (!empty($o['eager'])) { $a['fetchpriority'] = 'high'; } else { $a['loading'] = 'lazy'; }
    if (!empty($o['class'])) $a['class'] = $o['class'];
    $h = '<img';
    foreach ($a as $k => $v) $h .= ' ' . $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
    return $h . '>';
}

function photo_mem_ok(int $w, int $h): bool {
    $lim = trim((string)ini_get('memory_limit'));
    if ($lim === '-1') return true;
    $n = (int)$lim; $u = strtolower(substr($lim, -1));
    if ($u === 'g') $n *= 1073741824; elseif ($u === 'm') $n *= 1048576; elseif ($u === 'k') $n *= 1024;
    $need = (int)($w * $h * 5.2);            // 디코드 + 리사이즈 버퍼 여유
    return $need < ($n - memory_get_usage(true) - 6 * 1048576);
}

function photo_load(string $abs, int $type) {
    switch ($type) {
        case IMAGETYPE_JPEG: return @imagecreatefromjpeg($abs);
        case IMAGETYPE_PNG:  return @imagecreatefrompng($abs);
        case IMAGETYPE_WEBP: return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : false;
    }
    return false;
}
function photo_orient($im, string $abs, int $type) {
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data') || !function_exists('imagerotate')) return $im;
    $ex = @exif_read_data($abs); $o = (int)($ex['Orientation'] ?? 1);
    $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
    if ($deg) { $r = imagerotate($im, $deg, 0); if ($r) { imagedestroy($im); $im = $r; } }
    return $im;
}
function photo_resample($im, int $tw) {
    $sw = imagesx($im); $sh = imagesy($im);
    if ($tw >= $sw) return $im;
    $th = max(1, (int)round($sh * $tw / $sw));
    $dst = imagecreatetruecolor($tw, $th);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $tw, $th, $sw, $sh);
    imagedestroy($im);
    return $dst;
}
function photo_save($im, string $file, string $ext, int $quality): bool {
    $tmp = $file . '.tmp' . bin2hex(random_bytes(3));
    if ($ext === 'webp') { imagealphablending($im, false); imagesavealpha($im, true); $ok = imagewebp($im, $tmp, $quality); }
    elseif ($ext === 'png') { imagesavealpha($im, true); $ok = imagepng($im, $tmp, 6); }
    else {
        // JPEG 는 투명을 못 쓰므로 흰 배경에 합성
        $w = imagesx($im); $h = imagesy($im); $bg = imagecreatetruecolor($w, $h);
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255)); imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
        imageinterlace($bg, true); $ok = imagejpeg($bg, $tmp, $quality); imagedestroy($bg);
    }
    if (!$ok) { @unlink($tmp); return false; }
    if (!@rename($tmp, $file)) { $ok = @copy($tmp, $file); @unlink($tmp); }
    return (bool)$ok;
}

/** 썸네일 생성(없으면 만들어 캐시 경로 반환). 실패하면 null */
function photo_make_thumb(string $rel, int $w, bool $webp, bool $wait = true): ?string {
    $info = photo_info($rel);
    if (!$info || !photo_gd_ok() || !in_array($info['type'], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return null;
    $w = min($w, $info['w']);
    $ext = photo_thumb_ext($info, $webp && photo_webp_ok());
    $file = photo_thumb_file($info, $rel, $w, $ext);
    if (is_file($file)) return $file;
    if (!photo_mem_ok($info['w'], $info['h'])) return null;
    $dir = dirname($file); if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
    // 사양이 낮은 NAS 에서 여러 장이 동시에 디코드되면 멈추므로, 생성은 한 번에 하나씩(최대 ~10초 대기)
    $lock = photo_lock($wait ? 10 : 0);
    if ($lock === false) return null;
    if (is_file($file)) { photo_unlock($lock); return $file; }          // 기다리는 사이 다른 요청이 만들어 둠
    $im = photo_load($info['abs'], $info['type']);
    if (!$im) { photo_unlock($lock); return null; }
    $im = photo_orient($im, $info['abs'], $info['type']);
    $im = photo_resample($im, $w);
    $ok = photo_save($im, $file, $ext, $ext === 'webp' ? 80 : 82);
    imagedestroy($im);
    photo_unlock($lock);
    return $ok ? $file : null;
}

/** 썸네일 생성 직렬화용 잠금. 얻지 못하면 false(호출자는 원본으로 대체) */
function photo_lock(int $waitSec = 10) {
    $dir = rtrim(CACHE_DIR, '/\\') . '/thumbs'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $h = @fopen($dir . '/.lock', 'c'); if (!$h) return null;            // 잠금 파일을 못 열면 잠금 없이 진행
    $end = microtime(true) + $waitSec;
    do { if (flock($h, LOCK_EX | LOCK_NB)) return $h; if ($waitSec <= 0) break; usleep(100000); } while (microtime(true) < $end);
    fclose($h); return false;
}
function photo_unlock($h): void { if (is_resource($h)) { flock($h, LOCK_UN); fclose($h); } }

/**
 * 업로드 직후 자주 쓰는 폭(480/960/1600)을 미리 만들어 둔다 → 방문자가 처음 볼 때 서버가 이미지를 처리하지 않는다.
 * 반환: 새로 만든 파일 수
 */
function photo_pregen(string $rel, array $widths = [480, 960, 1600]): int {
    $info = photo_info($rel); if (!$info) return 0;
    $n = 0;
    foreach ($widths as $w) {
        if ($w >= $info['w'] && $w !== $widths[0]) { $w = $info['w']; }
        $w = min($w, $info['w']);
        foreach (photo_webp_ok() ? [true] : [false] as $webp) {
            $ext = photo_thumb_ext($info, $webp);
            if (is_file(photo_thumb_file($info, $rel, $w, $ext))) continue;
            if (photo_make_thumb($rel, $w, $webp, true) !== null) $n++;
        }
        if ($w >= $info['w']) break;
    }
    return $n;
}

/* ── 썸네일 대기열: 업로드 때는 원본만 저장하고, 썸네일은 저장 후 백그라운드(admin/thumb-bg.php)에서 만든다 ── */
function photo_queue_file(): string { return rtrim(PRIVATE_DIR, '/\\') . '/thumb-queue.txt'; }
function photo_queue_add(string $rel): void {
    $f = photo_queue_file(); if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
    @file_put_contents($f, $rel . "\n", FILE_APPEND | LOCK_EX);
}
function photo_queue_list(): array {
    $f = photo_queue_file(); if (!is_file($f)) return [];
    return array_values(array_unique(array_filter(array_map('trim', file($f, FILE_IGNORE_NEW_LINES) ?: []), fn($x) => $x !== '')));
}
function photo_queue_remove(string $rel): void {
    $f = photo_queue_file(); $h = @fopen($f, 'c+'); if (!$h) return;
    if (flock($h, LOCK_EX)) {
        $keep = array_filter(array_map('trim', explode("\n", (string)stream_get_contents($h))), fn($x) => $x !== '' && $x !== $rel);
        ftruncate($h, 0); rewind($h); fwrite($h, $keep ? implode("\n", $keep) . "\n" : ''); fflush($h); flock($h, LOCK_UN);
    }
    fclose($h);
}

/** 프로필 사진 등: 가운데 정사각형으로 잘라 $size 로 저장. 반환 [성공, 확장자, 메시지] */
function photo_store_square(string $tmp, string $mime, string $targetNoExt, int $size = 480): array {
    $type = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP][$mime] ?? null;
    if ($type === null) return [false, '', '허용되지 않는 형식입니다.'];
    $i = @getimagesize($tmp);
    if ($i && photo_gd_ok() && ($type !== IMAGETYPE_WEBP || function_exists('imagecreatefromwebp')) && photo_mem_ok((int)$i[0], (int)$i[1])) {
        $im = photo_load($tmp, $type);
        if ($im) {
            $im = photo_orient($im, $tmp, $type);
            $w = imagesx($im); $h = imagesy($im); $side = min($w, $h);
            $dst = imagecreatetruecolor($size, $size);
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
            imagecopyresampled($dst, $im, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
            imagedestroy($im);
            $ext = $type === IMAGETYPE_PNG ? 'png' : 'jpg';
            $ok = photo_save($dst, $targetNoExt . '.' . $ext, $ext, 88);
            imagedestroy($dst);
            if ($ok) return [true, $ext, ''];
        }
    }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
    if (@move_uploaded_file($tmp, $targetNoExt . '.' . $ext) || @rename($tmp, $targetNoExt . '.' . $ext)) return [true, $ext, ''];
    return [false, $ext, '파일 저장에 실패했습니다.'];
}

/**
 * 업로드된 임시 파일을 최종 위치에 저장.
 * GD 가능 + PHOTO_MAX_EDGE>0 이면: 방향 보정 → 긴 변 제한 → 재압축(위치정보 제거).
 * 그렇지 않으면 원본 그대로 저장. 반환: [성공여부, 최종확장자, 메시지]
 */
function photo_store_upload(string $tmp, string $mime, string $targetNoExt): array {
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $extMap[$mime] ?? null; if (!$ext) return [false, '', '허용되지 않는 형식입니다.'];
    $type = ['jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP][$ext];
    $max = (int)PHOTO_MAX_EDGE;
    $i = @getimagesize($tmp);
    if ($max > 0 && $i && photo_gd_ok() && ($ext !== 'webp' || photo_webp_ok()) && photo_mem_ok((int)$i[0], (int)$i[1])) {
        $im = photo_load($tmp, $type);
        if ($im) {
            $im = photo_orient($im, $tmp, $type);
            $longest = max(imagesx($im), imagesy($im));
            if ($longest > $max) $im = photo_resample($im, (int)round(imagesx($im) * $max / $longest));
            $ok = photo_save($im, $targetNoExt . '.' . $ext, $ext, 90);
            imagedestroy($im);
            if ($ok) return [true, $ext, ''];
        }
    }
    // 처리하지 못하면 원본 그대로 저장. 업로드 임시 파일이면 옮기고, NAS 에서 가져온 원본이면 복사(원본은 건드리지 않음)
    $dst = $targetNoExt . '.' . $ext;
    if (is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dst) : @copy($tmp, $dst)) return [true, $ext, ''];
    return [false, $ext, '파일 저장에 실패했습니다.'];
}


/** EXIF GPS 값(분수 문자열 배열) → 십진 좌표 */
function photo_exif_gps($parts, $ref): ?float {
    if (!is_array($parts) || count($parts) < 3) return null;
    $f = function ($v) { if (is_string($v) && str_contains($v, '/')) { [$a, $b] = array_pad(explode('/', $v, 2), 2, 1); return (float)$b != 0 ? (float)$a / (float)$b : 0.0; } return (float)$v; };
    $d = $f($parts[0]) + $f($parts[1]) / 60 + $f($parts[2]) / 3600;
    return in_array(strtoupper((string)$ref), ['S', 'W'], true) ? -$d : $d;
}
/**
 * 촬영 시각·위치 추출(업로드 원본에서 — 저장본은 위치정보를 제거하므로 업로드 시점에 읽어야 함).
 * exif 확장이 없거나 정보가 없으면 null 들을 돌려준다.
 */
function photo_exif_meta(string $file): array {
    $m = ['taken' => null, 'lat' => null, 'lng' => null];
    if (!function_exists('exif_read_data')) return $m;
    $ex = @exif_read_data($file, 'ANY_TAG', false);
    if (!is_array($ex)) return $m;
    foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $k) {
        if (!empty($ex[$k]) && preg_match('/^(\d{4}):(\d{2}):(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/', (string)$ex[$k], $x) && $x[1] !== '0000') {
            $m['taken'] = "$x[1]-$x[2]-$x[3] $x[4]:$x[5]:$x[6]"; break;
        }
    }
    if (isset($ex['GPSLatitude'], $ex['GPSLongitude'])) {
        $lat = photo_exif_gps($ex['GPSLatitude'], $ex['GPSLatitudeRef'] ?? 'N');
        $lng = photo_exif_gps($ex['GPSLongitude'], $ex['GPSLongitudeRef'] ?? 'E');
        if ($lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0 && $lng == 0)) { $m['lat'] = round($lat, 6); $m['lng'] = round($lng, 6); }
    }
    return $m;
}


/* ── HEIC(아이폰·갤럭시 고효율 사진) ──
 * GD 는 HEIC 를 읽지 못하므로 보통은 브라우저가 JPG 로 바꿔서 올린다(admin/photo-source.js).
 * 서버에 HEIC 를 읽을 수 있는 Imagick 이 있으면 서버에서도 변환한다(없으면 안내 메시지).
 */
function photo_is_heic(string $file, string $mime = '', string $name = ''): bool {
    if (preg_match('~^image/hei[cf](-sequence)?$~i', $mime)) return true;
    $x = strtolower(pathinfo($name !== '' ? $name : $file, PATHINFO_EXTENSION));
    if (!in_array($x, ['heic', 'heif'], true)) return false;
    // 오래된 libmagic 은 HEIC 를 application/octet-stream 으로 본다 → 파일 머리(ftyp)로 확인
    $h = @fopen($file, 'rb'); if (!$h) return false;
    $head = (string)fread($h, 16); fclose($h);
    return substr($head, 4, 4) === 'ftyp' && preg_match('/^(hei[cmsx]|hevc|hevx|mif1|msf1)$/', substr($head, 8, 4));
}
function photo_heic_server_ok(): bool {
    static $ok = null;
    if ($ok === null) { $ok = false; try { $ok = class_exists('Imagick') && in_array('HEIC', \Imagick::queryFormats('HEI*'), true); } catch (\Throwable $e) {} }
    return $ok;
}
/** HEIC → 임시 JPG(Imagick). 실패하면 null. 호출자가 임시 파일을 지운다. */
function photo_heic_to_jpeg(string $src): ?string {
    if (!photo_heic_server_ok()) return null;
    try {
        $im = new \Imagick($src);
        if (method_exists($im, 'autoOrient')) $im->autoOrient();
        $im->setImageFormat('jpeg'); $im->setImageCompressionQuality(92); $im->stripImage();
        $out = rtrim(sys_get_temp_dir(), '/\\') . '/heic-' . bin2hex(random_bytes(6)) . '.jpg';
        $ok = $im->writeImage($out); $im->clear();
        return $ok && is_file($out) ? $out : null;
    } catch (\Throwable $e) { return null; }
}

/** 브라우저가 원본(HEIC)에서 읽어 보낸 촬영 시각·위치. 형식이 맞지 않으면 null */
function photo_client_meta(array $in): array {
    $m = ['taken' => null, 'lat' => null, 'lng' => null];
    $t = trim((string)($in['client_taken'] ?? ''));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $t, $x) && $x[1] !== '0000' && checkdate((int)$x[2], (int)$x[3], (int)$x[1])) $m['taken'] = $t;
    $lat = $in['client_lat'] ?? ''; $lng = $in['client_lng'] ?? '';
    if (is_numeric($lat) && is_numeric($lng)) {
        $lat = (float)$lat; $lng = (float)$lng;
        if (abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0 && $lng == 0)) { $m['lat'] = round($lat, 6); $m['lng'] = round($lng, 6); }
    }
    return $m;
}

/** 여행 사진 폴더 이름 검사 (예: "2026-10-09 south korea") */
function photo_folder_ok(string $folder): bool {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2} [a-z0-9][a-z0-9 ._-]*$/', $folder) && !str_contains($folder, '..');
}

/**
 * 사진 한 장을 travel-photos/<폴더> 에 넣는다 — 관리자 업로드(upload.php)와 NAS 가져오기(nas-photos.php) 공용.
 *   $src: 업로드 임시 파일 또는 NAS 원본(원본은 읽기만 함)
 *   $clientMeta: 브라우저가 보낸 촬영 시각·위치(서버가 EXIF 를 못 읽을 때만 사용)
 * 반환: 성공 ['ok'=>true, path, name, taken, lat, lng, w, h] / 실패 ['ok'=>false, 'code'=>HTTP, 'message'=>...]
 */
function photo_ingest(string $src, string $folder, array $clientMeta = [], string $origName = ''): array {
    $fail = function (int $code, string $msg) { return ['ok' => false, 'code' => $code, 'message' => $msg]; };
    if (!photo_folder_ok($folder)) return $fail(400, '여행날짜와 국가 영문명을 먼저 입력해주세요.');
    if (!is_file($src) || !is_readable($src)) return $fail(404, '사진 파일을 읽을 수 없습니다.');
    if ((int)@filesize($src) > 50 * 1024 * 1024) return $fail(413, '한 장당 50MB까지 올릴 수 있습니다.');
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($src);
    $tmpConv = null;
    $meta = photo_exif_meta($src);                                 // 저장 전에(위치정보 제거 전) 읽는다. PHP 8.2 이상은 HEIC 도 읽음
    if (photo_is_heic($src, $mime, $origName)) {
        $tmpConv = photo_heic_to_jpeg($src);
        if (!$tmpConv) return $fail(415, 'HEIC 사진은 브라우저가 JPG 로 바꿔서 올립니다. 화면을 새로고침(Ctrl+F5)한 뒤 다시 시도해 주세요.');
        $src = $tmpConv; $mime = 'image/jpeg';
    }
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return $fail(415, 'JPG, PNG, WEBP, HEIC 사진만 올릴 수 있습니다.');
    foreach (['taken', 'lat', 'lng'] as $k) if ($meta[$k] === null && isset($clientMeta[$k])) $meta[$k] = $clientMeta[$k];
    if ($meta['lat'] === null || $meta['lng'] === null) { $meta['lat'] = $meta['lng'] = null; }
    $dir = photo_root() . '/' . $folder;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { if ($tmpConv) @unlink($tmpConv); return $fail(500, '사진 폴더를 만들 수 없습니다. travel-photos 폴더의 쓰기 권한을 확인하세요.'); }
    $base = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    [$ok, $ext, $msg] = photo_store_upload($src, $mime, $dir . '/' . $base);
    if ($tmpConv) @unlink($tmpConv);
    if (!$ok) return $fail(500, $msg ?: '파일 저장에 실패했습니다.');
    $name = $base . '.' . $ext;
    @photo_queue_add($folder . '/' . $name);   // 썸네일은 저장 후 백그라운드에서(thumb-bg.php)
    $info = @getimagesize($dir . '/' . $name);
    return ['ok' => true, 'path' => $folder . '/' . $name, 'name' => $name, 'taken' => $meta['taken'], 'lat' => $meta['lat'], 'lng' => $meta['lng'],
            'w' => $info ? (int)$info[0] : null, 'h' => $info ? (int)$info[1] : null];
}

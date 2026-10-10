<?php
/**
 * 공유 폴더 파일 내려받기 / 미리보기 / 썸네일
 *   get.php?s=공유ID&p=상대경로            → 다운로드(이어받기·분할 다운로드 지원)
 *   get.php?s=…&p=…&inline=1             → 브라우저에서 바로 보기(사진·동영상·음악·PDF·텍스트만)
 *   get.php?s=…&p=…&thumb=1[&big=1]      → Synology 가 만들어 둔 미리보기 이미지(@eaDir) — HEIC·RAW 도 이걸로 미리보기
 * 권한 확인 → 경로가 공유 폴더 밖으로 나가지 않는지 확인 → 1MB 단위로 흘려보냄(메모리 거의 안 씀).
 */
require_once dirname(__DIR__, 2) . '/_boot.php';
$u = hub_require_login();

function hub_fail(int $code, string $msg): void { http_response_code($code); header('Content-Type: text/plain; charset=utf-8'); echo $msg; exit; }

$share = hub_can_access($u, (int)($_GET['s'] ?? 0));
if (!$share) hub_fail(404, '폴더를 찾을 수 없거나 접근 권한이 없습니다.');
$r = hub_resolve($share, (string)($_GET['p'] ?? ''));
if (!$r || !is_file($r['abs'])) hub_fail(404, '파일을 찾을 수 없습니다.');
if (!is_readable($r['abs'])) hub_fail(403, '파일을 읽을 권한이 없습니다. (NAS 에서 http 사용자에게 읽기 권한 필요)');

// 세션 잠금 해제 — 큰 파일을 받는 동안에도 다른 페이지가 막히지 않게
session_write_close();

$file = $r['abs']; $name = basename($file);

/* ── 썸네일 ── */
if (!empty($_GET['thumb'])) {
    $t = hub_syno_thumb($file, !empty($_GET['big']));
    if (!$t && empty($_GET['big'])) $t = hub_gd_thumb($file);      // Synology 썸네일이 없으면 직접 만든 작은 미리보기
    if (!$t) hub_fail(404, 'no thumbnail');
    $mt = (int)filemtime($t); $etag = '"t' . dechex($mt) . dechex((int)filesize($t)) . '"';
    header('Cache-Control: private, max-age=86400');
    header('ETag: ' . $etag);
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($t));
    readfile($t);
    exit;
}

/* ── 본 파일 ── */
$inlineMime = !empty($_GET['inline']) ? hub_inline_mime($name) : null;
$size  = (float)sprintf('%u', (string)@filesize($file));   // 32비트 PHP 에서도 2GB 넘는 크기를 숫자로
$mtime = (int)filemtime($file);
$etag  = '"' . dechex($mtime) . '-' . dechex((int)fmod($size, 4294967296)) . '"';

// 이름: 한글 등은 RFC 5987 방식(filename*)으로, 옛 브라우저용으로 ASCII 대체 이름도 함께
$ascii = preg_replace('/[^\x20-\x7E]+|["\\\\]/', '_', $name);
$disp  = ($inlineMime ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);

$start = 0.0; $end = $size - 1; $partial = false;
$range = (string)($_SERVER['HTTP_RANGE'] ?? '');
$ifRange = trim((string)($_SERVER['HTTP_IF_RANGE'] ?? ''));
if ($range !== '' && ($ifRange === '' || $ifRange === $etag || strtotime($ifRange) === $mtime)) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) || ($m[1] === '' && $m[2] === '')) {
        header('Content-Range: bytes */' . sprintf('%.0f', $size)); hub_fail(416, 'Range Not Satisfiable');
    }
    if ($m[1] === '') { $start = max(0, $size - (float)$m[2]); }          // 마지막 N 바이트
    else { $start = (float)$m[1]; if ($m[2] !== '') $end = min((float)$m[2], $size - 1); }
    if ($start > $end || $start >= $size) { header('Content-Range: bytes */' . sprintf('%.0f', $size)); hub_fail(416, 'Range Not Satisfiable'); }
    $partial = true;
}
$length = $end - $start + 1;

// 처음부터 받는 다운로드만 기록(동영상 탐색·이어받기는 중복 기록하지 않음)
if (!$inlineMime && $start == 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') hub_log_download($u, (int)$share['id'], $share['name'] . '/' . $r['rel'], $size);

while (ob_get_level()) ob_end_clean();
@set_time_limit(0);
@ini_set('zlib.output_compression', '0');

http_response_code($partial ? 206 : 200);
header('Content-Type: ' . ($inlineMime ?: 'application/octet-stream'));
header('Content-Disposition: ' . $disp);
header('Accept-Ranges: bytes');
header('Content-Length: ' . sprintf('%.0f', $length));
if ($partial) header('Content-Range: bytes ' . sprintf('%.0f-%.0f/%.0f', $start, $end, $size));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);
header('Cache-Control: private, no-transform');
header('X-Accel-Buffering: no');                 // Synology nginx 프록시가 큰 파일을 통째로 버퍼링하지 않도록
if ($inlineMime && strpos($inlineMime, 'text/') === 0) header("Content-Security-Policy: default-src 'none'; sandbox");   // 텍스트는 어떤 스크립트도 실행되지 않게 (PDF 는 브라우저 뷰어가 막히므로 제외)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;

$fh = fopen($file, 'rb');
if (!$fh) exit;
if ($start > 0) fseek($fh, (int)$start);
$left = $length; $chunk = 1048576;
while ($left > 0 && !feof($fh) && !connection_aborted()) {
    $buf = fread($fh, (int)min($chunk, $left));
    if ($buf === false || $buf === '') break;
    echo $buf; flush();
    $left -= strlen($buf);
}
fclose($fh);
exit;

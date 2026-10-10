<?php
// 내 공간 배경 이미지: GET 으로 보기, POST(파일 'img')로 올리기, POST del=1 로 지우기. 본인 것만.
require_once __DIR__ . '/_desk.php';
hub_private_headers();
if (!hub_available()) { http_response_code(500); exit; }
$u = hub_current_user();
if (!$u) { http_response_code(401); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $f = desk_wall_file($u);
    if (!$f) { http_response_code(404); exit; }
    $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    header_remove('Cache-Control');
    header('Cache-Control: private, max-age=31536000');   // 주소에 ?v=수정시각 이 붙으므로 오래 캐시해도 됨
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') desk_json(['ok' => false, 'error' => 'method'], 405);
if (!hub_csrf_ok($_POST['csrf'] ?? '')) desk_json(['ok' => false, 'error' => 'csrf'], 403);

$dir = desk_wall_dir();
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) desk_json(['ok' => false, 'error' => '저장 폴더를 만들 수 없습니다.'], 500);
$old = desk_wall_file($u);

if (!empty($_POST['del'])) {
    if ($old) @unlink($old);
    desk_json(['ok' => true]);
}

$f = $_FILES['img'] ?? null;
if (!$f || !is_uploaded_file($f['tmp_name'] ?? '') || (int)$f['error'] !== UPLOAD_ERR_OK) desk_json(['ok' => false, 'error' => '파일을 받지 못했습니다.'], 400);
if ((int)$f['size'] > 15 * 1024 * 1024) desk_json(['ok' => false, 'error' => '15MB 이하 이미지만 올릴 수 있어요.'], 413);
$info = @getimagesize($f['tmp_name']);
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
if (!$info || !$ext) desk_json(['ok' => false, 'error' => 'JPG · PNG · WebP 이미지만 올릴 수 있어요.'], 415);

$dest = $dir . '/wall-u' . (int)$u['id'] . '.' . $ext;
$tmp = $dest . '.tmp';

// GD 가 있으면 긴 변 2560px 로 줄여 JPEG 로 저장(배경이 빨리 뜨도록). 없으면 원본 그대로.
$saved = false;
if (function_exists('imagecreatetruecolor') && max($info[0], $info[1]) > 2560) {
    $src = $ext === 'png' ? @imagecreatefrompng($f['tmp_name']) : ($ext === 'webp' ? @imagecreatefromwebp($f['tmp_name']) : @imagecreatefromjpeg($f['tmp_name']));
    if ($src) {
        $r = 2560 / max($info[0], $info[1]);
        $w = (int)round($info[0] * $r); $h = (int)round($info[1] * $r);
        $dst = imagecreatetruecolor($w, $h);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        $dest = $dir . '/wall-u' . (int)$u['id'] . '.jpg'; $tmp = $dest . '.tmp';
        $saved = imagejpeg($dst, $tmp, 86);
        imagedestroy($src); imagedestroy($dst);
    }
}
if (!$saved && !move_uploaded_file($f['tmp_name'], $tmp)) desk_json(['ok' => false, 'error' => '저장하지 못했습니다.'], 500);
if ($old && $old !== $dest) @unlink($old);
rename($tmp, $dest);
desk_json(['ok' => true, 'url' => 'desk-wall.php?v=' . filemtime($dest)]);

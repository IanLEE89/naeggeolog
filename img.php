<?php
// 썸네일 생성/제공 엔드포인트:  img.php?p=<travel-photos 기준 상대경로>&w=<240|480|960|1600|2400>
// 한 번 만들어진 썸네일은 cache/thumbs 에 저장되고, 이후 페이지는 그 정적 파일을 직접 가리킵니다.
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/images.php';

$rel = (string)($_GET['p'] ?? '');
$w   = (int)($_GET['w'] ?? 960);
if (!in_array($w, PHOTO_WIDTHS, true)) $w = 960;
if (photo_abs($rel) === null) { http_response_code(404); exit; }

$file = photo_make_thumb($rel, $w, photo_client_webp());
if ($file === null) {            // GD 없음 · 메모리 부족 · 생성 실패 → 원본으로 대체
    header('Cache-Control: public, max-age=300');
    header('Location: ' . photo_url($rel), true, 302);
    exit;
}
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = ['webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg'][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=31536000, immutable');
header('Vary: Accept');
header('X-Content-Type-Options: nosniff');
readfile($file);

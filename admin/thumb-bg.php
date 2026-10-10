<?php
// 백그라운드 썸네일: 업로드 때 쌓인 대기열(private/thumb-queue.txt)을 관리자 목록 화면에서 한 장씩 처리한다.
//  GET  → {pending, items[]}      POST rel → 한 장 처리 후 {ok, left}
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/images.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && !csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
session_write_close();                          // 처리 중에도 다른 관리자 화면이 막히지 않게
$q = photo_queue_list();
if (!$isPost) {
    $items = [];
    foreach ($q as $rel) { if (photo_abs($rel) === null) { photo_queue_remove($rel); continue; } $items[] = $rel; }
    echo json_encode(['pending' => count($items), 'items' => array_slice($items, 0, 400)], JSON_UNESCAPED_UNICODE); exit;
}
$rel = (string)($_POST['rel'] ?? '');
if (!in_array($rel, $q, true)) { echo json_encode(['ok' => true, 'left' => count($q)]); exit; }
@set_time_limit(90);
if (photo_abs($rel) !== null) { try { photo_pregen($rel, [240, 480, 960, 1600]); } catch (Throwable $e) { error_log('thumb-bg: ' . $e->getMessage()); } }
photo_queue_remove($rel);
echo json_encode(['ok' => true, 'left' => count($q) - 1]);

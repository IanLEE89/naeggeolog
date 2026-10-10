<?php
// 헤더 검색창(⌘K / "/")의 실시간 검색용 JSON. 읽기 전용, 결과는 최대 8건.
require_once __DIR__ . '/includes/layout.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=20');
header('X-Content-Type-Options: nosniff');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) > 80) $q = mb_substr($q, 0, 80);
if ($q === '') { echo json_encode(['q' => '', 'total' => 0, 'items' => []]); exit; }

$ids = trip_search_ids($q);
$byId = [];
foreach (load_trips() as $t) $byId[$t['id']] = $t;
$items = [];
$ids = array_values(array_filter($ids, fn($id) => isset($byId[$id])));   // 숨김 여행 제외
foreach (array_slice($ids, 0, 8) as $id) {
    $t = $byId[$id];
    $items[] = [
        'id' => $t['id'], 'title' => $t['title'], 'date' => $t['date'], 'country' => $t['country'], 'location' => $t['location'],
        'thumb' => $t['cover'] !== '' ? photo_thumb_url($t['cover'], 240) : '',
    ];
}
echo json_encode(['q' => $q, 'total' => count($ids), 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

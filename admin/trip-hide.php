<?php
// 여행 숨기기/공개하기 (POST: id, hide=1|0, csrf). 목록 상태(q/status/sort/dir/page)를 유지한 채 돌아간다.
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/data.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청입니다.'); }
$id = trim((string)($_POST['id'] ?? ''));
$st = store(); $trip = $id !== '' ? $st->get($id) : null;
if (!$trip) { http_response_code(404); exit('여행을 찾을 수 없습니다. <a href="index.php">돌아가기</a>'); }
$hide = !empty($_POST['hide']);
$trip['hidden'] = $hide;
if (!$hide) { $trip['draft'] = false; $trip['publish_at'] = ''; }   // '공개하기' = 초안·예약도 해제
if (!$st->save($trip)) { http_response_code(500); exit('저장할 수 없습니다. 쓰기 권한을 확인하세요.'); }
$keep = [];
foreach (['q', 'status', 'sort', 'dir', 'page'] as $k) { $v = (string)($_POST[$k] ?? ''); if ($v !== '') $keep[$k] = $v; }
$keep['hid'] = $hide ? '1' : '0';
header('Location: index.php?' . http_build_query($keep) . '#list'); exit;

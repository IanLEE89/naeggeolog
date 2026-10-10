<?php
// 목록 일괄 작업 (POST: ids[], act, csrf). 삭제해도 사진 파일은 그대로 둡니다(사진 관리 화면에서 정리).
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/data.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청입니다.'); }
$act = (string)($_POST['act'] ?? '');
$ids = array_values(array_unique(array_filter(array_map('strval', (array)($_POST['ids'] ?? [])), fn($v) => $v !== '' && $v !== '_template')));
$keep = [];
foreach (['q', 'status', 'sort', 'dir', 'page'] as $k) { $v = (string)($_POST[$k] ?? ''); if ($v !== '') $keep[$k] = $v; }
if (!$ids || !in_array($act, ['publish', 'hide', 'draft', 'pin', 'unpin', 'delete'], true)) { header('Location: index.php?' . http_build_query($keep + ['bulk' => '0']) . '#list'); exit; }
@set_time_limit(60);
$st = store(); $n = 0;
foreach (array_slice($ids, 0, 200) as $id) {
    $t = $st->get($id); if (!$t) continue;
    if ($act === 'delete') { if ($st->delete($id)) $n++; continue; }
    switch ($act) {
        case 'publish': $t['hidden'] = false; $t['draft'] = false; $t['publish_at'] = ''; break;
        case 'hide':    $t['hidden'] = true; break;
        case 'draft':   $t['draft'] = true; $t['hidden'] = false; $t['publish_at'] = ''; break;
        case 'pin':     $t['pinned'] = true; break;
        case 'unpin':   $t['pinned'] = false; break;
    }
    if ($st->save($t)) $n++;
}
$keep['bulk'] = (string)$n; $keep['act'] = $act;
header('Location: index.php?' . http_build_query($keep) . '#list'); exit;

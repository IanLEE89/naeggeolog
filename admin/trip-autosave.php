<?php
// 편집 화면의 자동저장 (JSON 응답). 실제 여행 데이터는 건드리지 않고 비밀 폴더에 초안만 보관한다.
require_once __DIR__.'/auth.php'; require_admin(); require_once __DIR__.'/form-data.php'; require_once __DIR__.'/drafts.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_check($_POST['csrf']??'')){ http_response_code(403); echo json_encode(['ok'=>false]); exit; }
$key=(string)($_POST['autosave_key']??''); if(!draft_valid_key($key)){ http_response_code(400); echo json_encode(['ok'=>false]); exit; }
$trip=fd_normalize_form();
if(($trip['title']??'')===''&&count($trip['sections'])<=1){ echo json_encode(['ok'=>true,'saved'=>false]); exit; }   // 아직 내용 없음
$ok=draft_save($key,$trip,(string)($_POST['upload_key']??''));
echo json_encode(['ok'=>$ok,'saved'=>$ok,'at'=>date('H:i:s')]);

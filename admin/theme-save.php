<?php
require_once __DIR__.'/auth.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'요청이 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$raw = (string)($_POST['theme'] ?? '');
$data = json_decode($raw, true);
$allowed = ['page_bg','surface','surface_alt','text','muted','heading','border','accent','accent_soft','link','header_bg','footer_bg','footer_text','hero_overlay'];
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'테마 데이터 형식이 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$out=[];
foreach($allowed as $key){
    $value=$data[$key]??'';
    if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/',$value)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>'색상 값이 올바르지 않습니다: '.$key], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $out[$key]=strtolower($value);
}
$ok=travel_write_json(rtrim(PRIVATE_DIR,'/\\').'/theme.json',$out);
if(!$ok){
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'테마 파일을 저장할 수 없습니다. private 폴더 쓰기 권한을 확인하세요.'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

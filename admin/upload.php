<?php
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/images.php';
header('Content-Type: application/json; charset=utf-8');
function up_fail(int $code, string $msg){ http_response_code($code); echo json_encode(['ok'=>false,'message'=>$msg],JSON_UNESCAPED_UNICODE); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST' || !csrf_check($_POST['csrf']??'')) up_fail(403,'잘못된 요청입니다.');
session_write_close();   // 세션 잠금을 풀어 여러 장을 동시에 올리고 다른 관리자 화면도 막히지 않게 한다
$key=trim((string)($_POST['upload_key']??''));
$folder=trim((string)($_POST['photo_folder']??''));
if($key==='' || preg_match('/[\x00-\x1F\x7F]/u',$key) || str_contains($key,'..')) up_fail(400,'잘못된 업로드 키입니다.');
if(!photo_folder_ok($folder)) up_fail(400,'여행날짜와 국가 영문명을 먼저 입력해주세요.');
if(empty($_FILES['image']) || $_FILES['image']['error']!==UPLOAD_ERR_OK) up_fail(400,'이미지 업로드에 실패했습니다. (서버의 upload_max_filesize / post_max_size 설정도 확인하세요)');
$f=$_FILES['image']; if((int)$f['size']>50*1024*1024) up_fail(413,'한 장당 50MB까지 업로드할 수 있습니다.');
// HEIC 는 브라우저가 JPG 로 바꿔 올리면서, 원본에서 읽은 촬영 시각·위치를 client_* 로 함께 보낸다(변환하면 EXIF 가 사라지므로)
$r=photo_ingest($f['tmp_name'],$folder,photo_client_meta($_POST),(string)($f['name']??''));
if(!$r['ok']) up_fail($r['code'],$r['message']);
echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

<?php
// 사이트 대표 이미지 / 프로필 사진 업로드 (AJAX, JSON 응답). kind = hero | profile
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/images.php';
header('Content-Type: application/json; charset=utf-8');
function su_fail(int $code, string $msg){ http_response_code($code); echo json_encode(['ok'=>false,'message'=>$msg],JSON_UNESCAPED_UNICODE); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST' || !csrf_check($_POST['csrf']??'')) su_fail(403,'잘못된 요청입니다. 페이지를 새로고침하세요.');
$kind=(string)($_POST['kind']??''); if(!in_array($kind,['hero','profile'],true)) su_fail(400,'잘못된 종류입니다.');
if(empty($_FILES['image']) || $_FILES['image']['error']!==UPLOAD_ERR_OK) su_fail(400,'업로드에 실패했습니다. (서버의 upload_max_filesize / post_max_size 설정도 확인하세요)');
$f=$_FILES['image']; if((int)$f['size']>30*1024*1024) su_fail(413,'30MB 이하의 이미지만 올릴 수 있습니다.');
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)) su_fail(415,'JPG, PNG, WEBP 이미지만 허용됩니다.');
$dir=photo_root().'/_site';
if(!is_dir($dir) && !@mkdir($dir,0755,true)) su_fail(500,'travel-photos 폴더의 쓰기 권한을 확인하세요.');
$base=$kind.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
@set_time_limit(60);
[$ok,$ext,$msg] = $kind==='profile' ? photo_store_square($f['tmp_name'],$mime,$dir.'/'.$base,480) : photo_store_upload($f['tmp_name'],$mime,$dir.'/'.$base);
if(!$ok) su_fail(500,$msg?:'파일 저장에 실패했습니다.');
if($kind==='hero') @photo_pregen('_site/'.$base.'.'.$ext, [960,1600]);
$rel='travel-photos/_site/'.$base.'.'.$ext;
echo json_encode(['ok'=>true,'path'=>$rel,'preview'=>'../'.$rel],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

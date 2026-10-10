<?php
require_once __DIR__.'/auth.php'; require_admin(); require_once __DIR__.'/form-data.php'; require_once __DIR__.'/../includes/data.php'; require_once __DIR__.'/drafts.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_check($_POST['csrf']??'')){http_response_code(403);exit('잘못된 요청입니다.');}
function save_slug_id($date,$title){$s=fd_slug($title);return trim($date).($s!==''?' '.$s:'');}
function save_photo_folder($date,$countryEn){
  $date=trim((string)$date); $countryEn=strtolower(trim((string)$countryEn));
  $countryEn=preg_replace('/\s+/u',' ',$countryEn);
  $countryEn=preg_replace('/[^a-z0-9 ._-]/','',$countryEn);
  return trim($date.' '.$countryEn);
}
$date=fd_clean($_POST['date']??'');$title=fd_clean($_POST['title']??'');$countryEn=fd_clean($_POST['country_en']??'');
if($date===''||$title==='') exit('여행날짜와 제목은 반드시 입력해야 합니다. <a href="javascript:history.back()">돌아가기</a>');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) exit('여행날짜 형식이 올바르지 않습니다(YYYY-MM-DD). <a href="javascript:history.back()">돌아가기</a>');
if($countryEn==='') exit('국가 영문명을 입력해야 사진 폴더를 만들 수 있습니다. <a href="javascript:history.back()">돌아가기</a>');

$root=dirname(__DIR__); $st=store();

// ── ID 결정 ─────────────────────────────────────────
// 수정: 기존 id 를 그대로 사용(같은 레코드/파일에 덮어씀).
// 신규: 날짜+제목으로 id 를 만들고, 이미 있으면 -2, -3 … 을 붙여 덮어쓰기를 막습니다.
$old=fd_clean($_POST['original_id']??'');
if($old!==''){ $id=$old; }
else { $base=save_slug_id($date,$title); $id=$base; $n=2; while($st->exists($id)){ $id=$base.'-'.$n++; if($n>99) exit('같은 제목의 여행이 너무 많습니다.'); } }
if(preg_match('/[\x00-\x1F\x7F\/\\\\]/',$id)||$id==='.'||$id==='..') exit('여행 ID에 사용할 수 없는 문자가 있습니다.');
$trip=fd_normalize_form(); $trip['id']=$id;

// ── 예전 방식 draft 사진 폴더가 남아 있으면 최종 폴더로 이동 ──
$upload_key=fd_clean($_POST['upload_key']??''); $photoRoot=$root.'/travel-photos';
$finalFolder=save_photo_folder($date,$countryEn);
if($upload_key!=='' && preg_match('/^draft_[a-f0-9]{24}$/',$upload_key)){
  $src=$photoRoot.'/'.$upload_key;$dst=$photoRoot.'/'.$finalFolder;
  if(is_dir($src)){
    if(!is_dir($dst))mkdir($dst,0755,true);
    foreach(glob($src.'/*')?:[] as $f){$base=basename($f);$new=$dst.'/'.$base;if(is_file($f)&&rename($f,$new)){
      foreach($trip['sections'] as &$sec){if(is_array($sec['images']??null)){foreach($sec['images'] as &$img){if(str_starts_with((string)$img,$upload_key.'/'))$img=$finalFolder.'/'.substr($img,strlen($upload_key)+1);}unset($img);}}
      unset($sec);
    }}
    @rmdir($src);
  }
}

if($id!=='' && ($__old=$st->get($id))) rev_snapshot($id,$__old);   // 저장 전 내용을 수정 이력에 보관
if(!$st->save($trip)){
  http_response_code(500);
  exit('저장에 실패했습니다. private 폴더(데이터베이스)에 웹 서버 계정(http)의 쓰기 권한이 있는지 확인하세요. <a href="javascript:history.back()">돌아가기</a>');
}
draft_delete(draft_key_for_id($id)); if(preg_match('/^draft_[a-f0-9]{24}$/',$upload_key)) draft_delete('new-'.$upload_key); // 저장 완료 → 자동저장본 정리
header('Location: index.php?saved=1');exit;

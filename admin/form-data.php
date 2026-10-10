<?php
require_once __DIR__.'/../includes/data.php';
function fd_clean($v){ return trim((string)$v); }
require_once __DIR__.'/../includes/sanitize.php';
function fd_lines($s){ $a=is_array($s)?array_map('strval',$s):preg_split('/\R/u',(string)$s); return array_values(array_filter(array_map('trim',$a),fn($x)=>$x!=='')); }
function fd_captions($s){ $out=[]; foreach(fd_lines($s) as $line){ $p=explode('|',$line,2); if(count($p)===2 && trim($p[0])!=='') $out[trim($p[0])]=trim($p[1]); } return $out; }
function fd_slug($title){ $s=preg_replace('/[^\p{L}\p{N}]+/u','-',trim((string)$title)); return trim((string)$s,'-'); }
function fd_basic_html($b){
  $date=fd_clean($b['date']??''); $weather=fd_clean($b['weather']??''); $country=fd_clean($b['country']??'');
  $lodging=fd_clean($b['lodging']??''); $lodgingUrl=fd_clean($b['lodging_url']??''); $places=fd_clean($b['places']??'');
  $transport=array_values(array_filter(array_map('fd_clean',is_array($b['transport']??null)?$b['transport']:[])));
  $other=fd_clean($b['transport_other']??'');
  if(in_array('기타',$transport,true)){ $transport=array_values(array_filter($transport,fn($x)=>$x!=='기타')); if($other!=='') $transport[]=$other; }
  $icons=['맑음'=>'☀️','눈'=>'❄️','비'=>'🌧️','흐림'=>'☁️','폭풍'=>'⛈️'];
  $dateText=$date!=='' && strtotime($date)!==false ? date('Y년 n월 j일',strtotime($date)) : $date;
  $h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
  $lodgingHtml=$lodging!=='' ? ($lodgingUrl!==''?'<a href="'.$h($lodgingUrl).'">'.$h($lodging).'</a>':$h($lodging)) : '';
  return '<p>날짜: '.$h($dateText).'<br>날씨: '.$h(($icons[$weather]??'').$weather).'<br>나라명: '.$h($country).'<br>숙소: '.$lodgingHtml.'<br>이동수단: '.$h(implode(', ',$transport)).'<br>주요 장소: '.$h($places).'</p>';
}
function fd_basic_from_legacy_section($s){
  $info=['date'=>'','weather'=>'','country'=>'','country_en'=>'','lodging'=>'','lodging_url'=>'','transport'=>[],'transport_other'=>'','places'=>''];
  if(is_array($s['basic_info']??null)){ foreach($s['basic_info'] as $k=>$v){ if($v!=='' && $v!==[] && $v!==null) $info[$k]=$v; } }
  $raw=(string)($s['content_html']??'');
  if($info['date']==='' && preg_match('/날짜\s*:\s*(?:<[^>]+>\s*)?([0-9]{4})년\s*([0-9]{1,2})월\s*([0-9]{1,2})일/u',$raw,$m)) $info['date']=sprintf('%04d-%02d-%02d',$m[1],$m[2],$m[3]);
  if($info['weather']!=='') $info['weather']=preg_replace('/^[^\p{L}\p{N}]+/u','',$info['weather']);
  if($info['weather']==='' && preg_match('/날씨\s*:\s*(?:<[^>]+>)?([^<\r\n]+?)(?:<br\s*\/?\s*>|<\/p>|\r?\n|$)/iu',$raw,$m)) $info['weather']=trim(strip_tags($m[1]));
  if($info['country']==='' && preg_match('/나라명\s*:\s*(?:<[^>]+>)?([^<\r\n]+?)(?:<br\s*\/?\s*>|<\/p>|\r?\n|$)/iu',$raw,$m)) $info['country']=trim(strip_tags($m[1]));
  if($info['lodging']==='' && preg_match('/숙소\s*:\s*(?:<a[^>]*>(.*?)<\/a>|([^<\r\n]+))/iu',$raw,$m)) $info['lodging']=trim(strip_tags($m[1]??($m[2]??'')));
  if($info['lodging_url']==='' && preg_match('/숙소\s*:\s*<a[^>]*href=["\']([^"\']+)["\']/iu',$raw,$m)) $info['lodging_url']=$m[1];
  if(empty($info['transport']) && preg_match('/이동수단\s*:\s*(?:<[^>]+>)?([^<\r\n]+?)(?:<br\s*\/?\s*>|<\/p>|\r?\n|$)/iu',$raw,$m)){
    foreach(preg_split('/[,，]+/u',strip_tags($m[1])) as $t){ $t=trim($t); if($t==='') continue; $known=['자동차','버스','기차','지하철','도보','비행기']; if(in_array($t,$known,true)) $info['transport'][]=$t; else { $info['transport'][]='기타'; $info['transport_other']=trim(($info['transport_other']??'').($info['transport_other']!==''?', ':'').$t); } }
  }
  if($info['places']==='' && preg_match('/주요\s*장소\s*:\s*(?:<[^>]+>)?([^<\r\n]+?)(?:<br\s*\/?\s*>|<\/p>|\r?\n|$)/iu',$raw,$m)) $info['places']=trim(strip_tags($m[1]));
  return $info;
}
function fd_existing_basic($trip){
  $b=['date'=>(string)($trip['date']??''),'weather'=>(string)($trip['weather']??''),'country'=>(string)($trip['country']??''),'country_en'=>(string)($trip['country_en']??''),'lodging'=>'','lodging_url'=>'','transport'=>[],'transport_other'=>'','places'=>''];
  if(is_array($trip['basic_info']??null)){ foreach($trip['basic_info'] as $k=>$v){ if($v!=='' && $v!==[] && $v!==null) $b[$k]=$v; } }
  foreach((array)($trip['sections']??[]) as $s){ if((int)($s['level']??2)===1){ $legacy=fd_basic_from_legacy_section($s); foreach($legacy as $k=>$v){ if(($b[$k]??'')==='' || ($b[$k]??[])===[]) { if($v!=='' && $v!==[] && $v!==null) $b[$k]=$v; } } break; } }
  if($b['weather']!=='') $b['weather']=preg_replace('/^[^\p{L}\p{N}]+/u','',$b['weather']);
  $b['transport']=is_array($b['transport']??null)?array_values(array_filter(array_map('fd_clean',$b['transport']))):[];
  return $b;
}
function fd_normalize_form(){
  $date=fd_clean($_POST['date']??''); $title=fd_clean($_POST['title']??'');
  $b=[
    'date'=>$date,'weather'=>fd_clean($_POST['weather']??''),'country'=>fd_clean($_POST['country']??''),'country_en'=>fd_clean($_POST['country_en']??''),
    'lodging'=>fd_clean($_POST['lodging']??''),'lodging_url'=>fd_clean($_POST['lodging_url']??''),'transport'=>array_values(array_filter(array_map('fd_clean',is_array($_POST['transport']??null)?$_POST['transport']:[]))),
    'transport_other'=>fd_clean($_POST['transport_other']??''),'places'=>fd_clean($_POST['places']??'')
  ];
  $sections=[]; $raw=$_POST['sections']??[];
  if(is_array($raw)) foreach($raw as $s){
    if(!is_array($s)) continue;
    $title2=fd_clean($s['title']??''); if($title2==='') continue;
    $si=[]; $rawSi=$s['subsection_info_json']??''; if(is_string($rawSi)&&trim($rawSi)!==''){ $decoded=json_decode($rawSi,true); if(is_array($decoded)) $si=$decoded; }
    $features=is_array($si['features']??null)?array_values(array_filter(array_map('fd_clean',$si['features']),fn($x)=>$x!=='')):[];
    $expenses=[]; $rawEx=$s['expenses_json']??''; if(is_string($rawEx)&&trim($rawEx)!==''){ $decoded=json_decode($rawEx,true); if(is_array($decoded)) $expenses=$decoded; }
    $cleanExpenses=[]; foreach($expenses as $ex){ if(!is_array($ex)) continue; $item=fd_clean($ex['item']??''); $currency=strtoupper(fd_clean($ex['currency']??'KRW')); $amount=(float)($ex['amount']??0); if($item!=='' || $amount>0) $cleanExpenses[]=['item'=>$item,'currency'=>$currency,'amount'=>$amount,'rate_to_krw'=>isset($ex['rate_to_krw'])?(float)$ex['rate_to_krw']:null,'krw_amount'=>isset($ex['krw_amount'])?(float)$ex['krw_amount']:null,'category'=>(in_array((string)($ex['category']??''),['lodging','transport','activity','food','shopping','telecom','other'],true)?(string)$ex['category']:'')]; }
    $section=['level'=>2,'title'=>$title2,'content_html'=>fd_sanitize_html($s['content_html']??''),'images'=>fd_lines($s['images']??[])];
    $caps=fd_captions($s['image_captions']??''); if($caps) $section['image_captions']=$caps;
    $section['anchor']=fd_slug($title2);
    $section['subsection_info']=['location'=>fd_clean($si['location']??''),'location_url'=>fd_clean($si['location_url']??''),'lat'=>fd_clean($si['lat']??''),'lng'=>fd_clean($si['lng']??''),'place_id'=>fd_clean($si['place_id']??''),'purpose'=>fd_clean($si['purpose']??''),'rating'=>max(0,min(5,(int)($si['rating']??0))),'features'=>$features];
    if($cleanExpenses) $section['expenses']=$cleanExpenses;
    $sections[]=$section;
  }
  $trip=['id'=>fd_clean($_POST['original_id']??''),'wp_id'=>fd_clean($_POST['wp_id']??''),'date'=>$date,'title'=>$title,'country'=>$b['country'],'country_en'=>$b['country_en'],'location'=>fd_clean($_POST['location']??''),'summary'=>fd_clean($_POST['summary']??''),'guid'=>fd_clean($_POST['guid']??''),'lat'=>($_POST['lat']??'')===''?'':(float)$_POST['lat'],'lng'=>($_POST['lng']??'')===''?'':(float)$_POST['lng'],'cover'=>fd_clean($_POST['cover']??''),'basic_info'=>$b,'sections'=>$sections,'image_count'=>array_sum(array_map(fn($s)=>count($s['images']??[]),$sections)),'hidden'=>false,'draft'=>false,'publish_at'=>'','tags'=>trip_clean_tags($_POST['tags']??''),'pinned'=>!empty($_POST['pinned'])];
  // 공개 상태: public | scheduled | draft | hidden  (예전 폼의 hidden 체크박스도 호환)
  $__state=(string)($_POST['state']??(!empty($_POST['hidden'])?'hidden':'public')); $__pub=trim((string)($_POST['publish_at']??''));
  if($__state==='hidden') $trip['hidden']=true;
  elseif($__state==='draft') $trip['draft']=true;
  elseif($__state==='scheduled' && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/',$__pub)) $trip['publish_at']=str_replace('T',' ',$__pub);
  if($trip['cover']===''){ foreach($sections as $__s){ foreach((array)($__s['images']??[]) as $__i){ if($__i!==''){ $trip['cover']=(string)$__i; break 2; } } } }   // 대표 사진 미지정 → 첫 사진
  // Keep the legacy/public-facing basic-information section so the existing public trip page remains compatible.
  array_unshift($trip['sections'],['level'=>1,'title'=>'여행 기본 정보','content_html'=>fd_basic_html($b),'images'=>[],'basic_info'=>$b]);
  return $trip;
}

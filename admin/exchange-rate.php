<?php
require_once __DIR__.'/auth.php'; require_admin();
header('Content-Type: application/json; charset=utf-8');
$from=strtoupper(trim((string)($_GET['from']??'')));$date=trim((string)($_GET['date']??''));
if(!preg_match('/^[A-Z]{3}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){http_response_code(400);echo json_encode(['ok'=>false,'message'=>'통화 또는 날짜가 올바르지 않습니다.'],JSON_UNESCAPED_UNICODE);exit;}
if($from==='KRW'){echo json_encode(['ok'=>true,'from'=>'KRW','to'=>'KRW','date'=>$date,'rate'=>1],JSON_UNESCAPED_UNICODE);exit;}
$url='https://api.frankfurter.dev/v2/rate/'.strtolower($from).'/krw?date='.rawurlencode($date);
$ctx=stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'header'=>"User-Agent: NaeggeologAdmin/1.0 (travel archive)\r\nAccept: application/json\r\n"]]);
$raw=@file_get_contents($url,false,$ctx); if($raw===false){http_response_code(502);echo json_encode(['ok'=>false,'message'=>'환율 서버에 연결하지 못했습니다.'],JSON_UNESCAPED_UNICODE);exit;}
$data=json_decode($raw,true);$rate=(float)($data['rate']??0);if($rate<=0){http_response_code(502);echo json_encode(['ok'=>false,'message'=>'해당 날짜의 환율을 찾지 못했습니다.'],JSON_UNESCAPED_UNICODE);exit;}
echo json_encode(['ok'=>true,'from'=>$from,'to'=>'KRW','date'=>(string)($data['date']??$date),'rate'=>$rate],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

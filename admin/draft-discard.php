<?php
require_once __DIR__.'/auth.php'; require_admin(); require_once __DIR__.'/drafts.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_check($_POST['csrf']??'')){ http_response_code(403); exit('잘못된 요청입니다.'); }
draft_delete((string)($_POST['key']??''));
$back=(string)($_POST['back']??'index.php'); if(!preg_match('~^(trip-edit\.php\?id=[^\r\n]*|trip-new\.php|index\.php)$~',$back)) $back='index.php';
header('Location: '.$back); exit;

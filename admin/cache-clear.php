<?php
// 썸네일/본문 캐시 비우기 (사진을 교체했거나 용량을 정리할 때). 원본 사진·여행 데이터는 건드리지 않습니다.
require_once __DIR__.'/auth.php'; require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_check($_POST['csrf']??'')){http_response_code(403);exit('잘못된 요청입니다.');}
$root=realpath(CACHE_DIR); $n=0;
if($root!==false){
  foreach(['thumbs','html'] as $sub){
    $d=$root.'/'.$sub; if(!is_dir($d)) continue;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f){ $p=$f->getPathname(); if(strpos(realpath($p)?:'',$root.DIRECTORY_SEPARATOR)!==0) continue; if($f->isDir()) @rmdir($p); elseif(@unlink($p)) $n++; }
  }
}
header('Location: index.php?cache='.$n); exit;

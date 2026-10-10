<?php
require_once __DIR__.'/auth.php'; require_admin(); require_once __DIR__.'/style.php';
$upload_key='draft_'.bin2hex(random_bytes(12));
$trip=['id'=>'','wp_id'=>'','date'=>date('Y-m-d'),'title'=>'','country'=>'','country_en'=>'','location'=>'','summary'=>'','guid'=>'','lat'=>'','lng'=>'','cover'=>'','basic_info'=>['date'=>date('Y-m-d'),'weather'=>'','country'=>'','country_en'=>'','lodging'=>'','lodging_url'=>'','transport'=>[],'transport_other'=>'','places'=>''],'sections'=>[],'image_count'=>0];
$is_new=true;
include __DIR__.'/trip-edit.php';

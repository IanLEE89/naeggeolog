<?php
// 저장하지 않은 입력 내용을 실제 상세 페이지와 "같은 렌더러"로 미리보기합니다.
require_once __DIR__.'/auth.php'; require_admin(); require_once __DIR__.'/form-data.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_check($_POST['csrf']??'')){http_response_code(403);exit('잘못된 요청입니다.');}
require_once __DIR__.'/../includes/trip-render.php';

$trip=fd_normalize_form(); $trip['id']=fd_clean($_POST['original_id']??'');
$current_country=$trip['country']??''; $current_id=$trip['id'];
page_head(['title'=>($trip['title']?:'제목 없음').' · 미리보기','base'=>'../','robots'=>'noindex,nofollow']);
echo '<div class="sticky top-0 z-[1500] bg-accent px-4 py-2 text-center text-xs text-white">미리보기 — 아직 저장되지 않았습니다. 이 창을 닫고 편집 화면에서 저장하세요.</div>';
include __DIR__.'/../includes/header.php';
echo '<main id="main">'; trip_render_body($trip); echo '</main>';
trip_render_lightbox();
include __DIR__.'/../footer.php';
page_end(['maps'=>(bool)trip_route_points($trip)]);

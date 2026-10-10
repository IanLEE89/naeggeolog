<?php
require_once __DIR__.'/auth.php'; require_admin(); require_once __DIR__.'/style.php'; require_once __DIR__.'/form-data.php'; require_once __DIR__.'/../includes/data.php';
$googleMapsKey=travel_google_maps_config()['key'];
$is_new = isset($is_new) ? $is_new : false;
$root=dirname(__DIR__);
if(!$is_new){
  $id=trim((string)($_GET['id']??'')); $trip=$id!==''?get_trip($id):null;
  if(!$trip){http_response_code(404);echo '<div class="p-8">여행 데이터를 찾을 수 없습니다.</div>';admin_foot();exit;}
  $upload_key=$id;
}
// ── 자동저장본 / 이전 버전 불러오기 ──
require_once __DIR__.'/drafts.php';
$loadNotice=''; $autosaveRow=null;
$__dkey = $is_new ? ('new-'.($upload_key??'')) : draft_key_for_id((string)($trip['id']??''));
if($is_new && !empty($_GET['resume']) && preg_match('/^new-draft_[a-f0-9]{24}$/',(string)$_GET['resume'])){
  $row=draft_load((string)$_GET['resume']); if($row){ $trip=$row['trip']; $upload_key=(string)($row['upload_key']??$upload_key); $__dkey=(string)$_GET['resume']; $loadNotice='이어서 작성하는 초안을 불러왔습니다 ('.tv($row['saved_at']).' 자동저장). 저장해야 여행으로 등록됩니다.'; }
}
if(!$is_new){
  $__row=draft_load($__dkey);
  if(($_GET['from']??'')==='autosave' && $__row){ $trip=array_merge($trip,$__row['trip']); $loadNotice='자동저장된 미저장 변경(' . tv($__row['saved_at']) . ')을 불러왔습니다. 저장해야 반영됩니다.'; }
  elseif($__row) $autosaveRow=$__row;
  if(!empty($_GET['rev']) && preg_match('/^\d{8}-\d{6}$/',(string)$_GET['rev'])){ $rv=rev_load((string)($trip['id']??''),(string)$_GET['rev']); if($rv){ $trip=$rv; $loadNotice='이전 버전('.tv(substr($_GET['rev'],0,4).'-'.substr($_GET['rev'],4,2).'-'.substr($_GET['rev'],6,2).' '.substr($_GET['rev'],9,2).':'.substr($_GET['rev'],11,2)).')을 불러왔습니다. 저장하면 이 버전으로 되돌아갑니다.'; } }
}
$revList=$is_new?[]:rev_list((string)($trip['id']??''));
$newResume=$is_new?draft_latest_new():null;
$curState=trip_state($trip); if(!empty($trip['publish_at'])&&$curState==='public') $curState='public';
$trip=is_array($trip??null)?$trip:[];
$basic=fd_existing_basic($trip);
if($basic['date']==='' && !empty($trip['date'])) $basic['date']=$trip['date'];
$travelSections=[];
foreach((array)($trip['sections']??[]) as $s){ if((int)($s['level']??2)!==1 && trim((string)($s['title']??''))!=='') $travelSections[]=$s; }
if(!$travelSections) $travelSections=[['level'=>2,'title'=>'','content_html'=>'','images'=>[],'image_captions'=>[],'subsection_info'=>['location'=>'','location_url'=>'','purpose'=>'','rating'=>0,'features'=>[]]]];
function tv($v){return htmlspecialchars(is_array($v)?implode(', ',array_map('strval',$v)):(string)$v,ENT_QUOTES,'UTF-8');}
function rows_for($s){$imgs=is_array($s['images']??null)?$s['images']:[];$caps=is_array($s['image_captions']??null)?$s['image_captions']:[];$rows=[];foreach($imgs as $img)$rows[]=['path'=>(string)$img,'caption'=>(string)($caps[$img]??'')];return $rows;}
function si_for($s){$src=is_array($s['subsection_info']??null)?$s['subsection_info']:[];return ['location'=>(string)($src['location']??''),'location_url'=>(string)($src['location_url']??''),'lat'=>(string)($src['lat']??''),'lng'=>(string)($src['lng']??''),'place_id'=>(string)($src['place_id']??''),'purpose'=>(string)($src['purpose']??''),'rating'=>max(0,min(5,(int)($src['rating']??0))),'features'=>is_array($src['features']??null)?array_values(array_filter(array_map('strval',$src['features']),fn($x)=>trim($x)!=='')):[]];}
function ex_for($s){return is_array($s['expenses']??null)?$s['expenses']:[];}
$countryOptions=[['South Korea','대한민국'],['Japan','일본'],['China','중국'],['Taiwan','대만'],['Hong Kong','홍콩'],['Vietnam','베트남'],['Thailand','태국'],['Philippines','필리핀'],['Singapore','싱가포르'],['Malaysia','말레이시아'],['Indonesia','인도네시아'],['Australia','호주'],['New Zealand','뉴질랜드'],['United States','미국'],['Canada','캐나다'],['United Kingdom','영국'],['France','프랑스'],['Italy','이탈리아'],['Spain','스페인'],['Germany','독일'],['Switzerland','스위스'],['Austria','오스트리아'],['Czech Republic','체코'],['Hungary','헝가리'],['Netherlands','네덜란드'],['Belgium','벨기에'],['Greece','그리스'],['Turkey','튀르키예'],['Portugal','포르투갈'],['Croatia','크로아티아'],['United Arab Emirates','아랍에미리트'],['India','인도'],['Egypt','이집트'],['Morocco','모로코']];
admin_head($is_new?'새 여행 작성':'여행 수정');
?>
<div class="pb-28">
  <div class="flex flex-col gap-5 border-b border-stone-200 pb-7 sm:flex-row sm:items-end sm:justify-between">
    <div><p class="text-[10px] font-semibold tracking-[.22em] text-stone-400"><?= $is_new?'NEW TRIP':'EDIT TRIP' ?></p><h1 class="admin-serif mt-2 text-[34px] font-medium leading-tight tracking-[-.045em] text-stone-900 sm:text-[40px]"><?= $is_new?'새 여행 작성':'여행 수정' ?></h1><p class="mt-2 text-xs text-stone-500">기본 정보는 한 번에 정리하고, 여행 내용은 소제목별 기록에 집중합니다.</p></div>
    <div class="flex flex-wrap items-center gap-2"><?php if($revList): ?><details class="rev-menu"><summary>🕘 수정 이력 <small><?=count($revList)?></small></summary><div class="rev-pop"><p>저장할 때마다 이전 내용이 보관됩니다. 고르면 편집 화면에 불러오며, 저장해야 반영됩니다.</p><?php foreach($revList as $__r): ?><a href="trip-edit.php?id=<?=rawurlencode((string)$trip['id'])?>&rev=<?=tv($__r['name'])?>"><?=tv($__r['label'])?><small><?=tv($__r['size'])?></small></a><?php endforeach; ?></div></details><?php endif; ?><a href="index.php" class="inline-flex w-fit items-center rounded-full border border-stone-300 bg-white px-4 py-2.5 text-xs text-stone-600 transition hover:border-stone-400 hover:text-stone-900">← 여행 관리</a></div>
  </div>
  <?php if($loadNotice): ?><div class="edit-note is-info"><?=$loadNotice?></div><?php endif; ?>
  <?php if($autosaveRow): ?><div class="edit-note is-warn" id="autosaveBanner">💾 저장하지 않은 <b>자동저장본</b>이 있습니다 (<?=tv($autosaveRow['saved_at'])?>). <a href="trip-edit.php?id=<?=rawurlencode((string)$trip['id'])?>&from=autosave">불러오기</a> · <form method="post" action="draft-discard.php" class="inline"><input type="hidden" name="csrf" value="<?=tv(csrf_token())?>"><input type="hidden" name="key" value="<?=tv($__dkey)?>"><input type="hidden" name="back" value="<?=tv('trip-edit.php?id='.rawurlencode((string)$trip['id']))?>"><button type="submit">버리기</button></form></div><?php endif; ?>
  <?php if($newResume): ?><div class="edit-note is-warn">💾 작성 중이던 새 여행이 있습니다: <b><?=tv($newResume['trip']['title']?:'제목 없음')?></b> (<?=tv($newResume['saved_at'])?> 자동저장). <a href="trip-new.php?resume=<?=tv($newResume['key'])?>">이어서 작성</a> · <form method="post" action="draft-discard.php" class="inline"><input type="hidden" name="csrf" value="<?=tv(csrf_token())?>"><input type="hidden" name="key" value="<?=tv($newResume['key'])?>"><input type="hidden" name="back" value="trip-new.php"><button type="submit">버리기</button></form></div><?php endif; ?>
  <form id="tripForm" method="post" action="trip-save.php" class="mt-8 space-y-10">
    <input type="hidden" name="csrf" value="<?=tv(csrf_token())?>"><input type="hidden" name="original_id" value="<?=tv($trip['id']??'')?>"><input type="hidden" name="upload_key" value="<?=tv($upload_key)?>">
    <input type="hidden" name="wp_id" value="<?=tv($trip['wp_id']??'')?>"><input type="hidden" name="guid" value="<?=tv($trip['guid']??'')?>"><input type="hidden" name="summary" value="<?=tv($trip['summary']??'')?>"><input type="hidden" name="cover" value="<?=tv($trip['cover']??'')?>">

    <section class="section-card overflow-hidden">
      <div class="border-b border-stone-200 px-5 py-5 sm:px-7"><div class="flex items-start gap-4"><span class="step-badge">01</span><div><h2 class="section-heading">기본 정보</h2><p class="section-description">여행 페이지 전체에서 공통으로 사용하는 정보를 먼저 입력합니다.</p></div></div></div>
      <div class="grid gap-x-6 gap-y-6 px-5 py-6 sm:grid-cols-2 sm:px-7 sm:py-7">
        <label class="block sm:col-span-2"><span class="field-label">제목</span><input type="text" name="title" value="<?=tv($trip['title']??'')?>" class="admin-input mt-2 text-[15px]" placeholder="예: 유럽 여행 시작, 로마의 트레비 분수" required></label>
        <label class="block"><span class="field-label">여행날짜</span><input type="date" name="date" value="<?=tv($basic['date'])?>" class="admin-input mt-2" required></label>
        <div class="block"><span class="field-label">날씨</span><div class="weather-picker mt-2"><?php foreach([['맑음','☀️'],['눈','❄️'],['비','🌧️'],['흐림','☁️'],['폭풍','⛈️']] as $w): ?><label class="weather-option"><input type="radio" name="weather" value="<?=$w[0]?>" <?=($basic['weather']===$w[0]?'checked':'')?>><span><b><?=$w[1]?></b><small><?=$w[0]?></small></span></label><?php endforeach; ?></div></div>
        <div class="block sm:col-span-2"><span class="field-label">국가</span><div class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_180px]"><input list="country-list" type="text" name="country_en" value="<?=tv($basic['country_en'])?>" class="admin-input country-picker" placeholder="영문으로 검색 또는 입력" autocomplete="off"><input type="text" name="country" value="<?=tv($basic['country'])?>" class="admin-input country-ko" placeholder="국가명 (한글)"></div><datalist id="country-list"><?php foreach($countryOptions as $c): ?><option value="<?=tv($c[0])?>"><?=tv($c[1])?></option><?php endforeach; ?></datalist><p class="field-help">영문 국가명을 선택하면 한글 국가명이 자동 입력됩니다.</p></div>
        <div class="block sm:col-span-2"><span class="field-label">도시 / 지역</span><div class="mt-2 google-search-row"><div id="locationAutocomplete" class="google-autocomplete"></div><button type="button" id="locationSearchBtn" class="google-search-btn">검색하기</button></div><div id="locationSearchResults" class="google-search-results hidden"></div><input id="locationInput" type="hidden" name="location" value="<?=tv($trip['location']??'')?>"><input id="latInput" type="hidden" name="lat" value="<?=tv($trip['lat']??'')?>"><input id="lngInput" type="hidden" name="lng" value="<?=tv($trip['lng']??'')?>"><p class="field-help">검색어를 입력한 뒤 <b>검색하기</b>를 누르고, 결과에서 도시·지역을 선택하세요.</p></div>
        <div class="sm:col-span-2"><div id="adminGoogleMap" class="admin-map" data-lat="<?=tv($trip['lat']??'')?>" data-lng="<?=tv($trip['lng']??'')?>" data-title="<?=tv($trip['location']??'')?>"></div></div>
        <div class="block sm:col-span-2"><span class="field-label">숙소</span><div class="mt-2 grid gap-2 sm:grid-cols-2"><input type="text" name="lodging" value="<?=tv($basic['lodging'])?>" class="admin-input" placeholder="숙소 이름"><input type="url" name="lodging_url" value="<?=tv($basic['lodging_url'])?>" class="admin-input" placeholder="숙소 링크 https://..."></div></div>
        <div class="block sm:col-span-2"><span class="field-label">이동수단</span><div class="transport-picker mt-2 flex flex-wrap gap-2"><?php foreach(['자동차','버스','기차','지하철','도보','비행기','기타'] as $t): ?><label class="transport-option"><input type="checkbox" name="transport[]" value="<?=$t?>" <?=in_array($t,$basic['transport']??[],true)?'checked':''?>><span><?=$t?></span></label><?php endforeach; ?></div><input type="text" name="transport_other" value="<?=tv($basic['transport_other'])?>" class="admin-input mt-3 transport-other" placeholder="기타 이동수단"></div>
        <label class="block sm:col-span-2"><span class="field-label">주요 장소</span><input type="text" name="places" value="<?=tv($basic['places'])?>" class="admin-input mt-2" placeholder="예: 트레비 분수, 스페인 광장, 콜로세움"></label>
        <div class="block sm:col-span-2"><span class="field-label">태그 <span class="font-normal text-stone-400">(선택)</span></span>
          <div class="tag-box mt-2" id="tagBox"><div id="tagChips" class="contents"></div><input type="text" id="tagInput" list="tagList" autocomplete="off" placeholder="예: 맛집, 트레킹, 가족여행 — Enter 로 추가" maxlength="24"></div>
          <input type="hidden" name="tags" id="tagsField" value="<?=tv(implode(',',$trip['tags']??[]))?>">
          <datalist id="tagList"><?php foreach(array_keys(trip_tag_counts(load_trips_admin())) as $__tg): ?><option value="<?=tv($__tg)?>"><?php endforeach; ?></datalist>
          <p class="field-help">같은 태그의 여행을 모아 보여 주고 "비슷한 여행"을 추천하는 데 쓰입니다. 최대 12개.</p></div>
      </div>
    </section>

    <section id="photoOrganizer" class="section-card overflow-hidden">
      <div class="border-b border-stone-200 px-5 py-5 sm:px-7"><div class="flex items-start gap-4"><span class="step-badge">★</span><div><h2 class="section-heading">사진으로 자동 정리 <span class="ml-1 text-[11px] font-normal text-stone-400">(선택)</span></h2><p class="section-description">사진을 여러 장 한꺼번에 올리면 촬영 시간·위치(EXIF)로 순서를 정리하고 장면별로 묶어 소제목(섹션)을 만들어 줍니다. 만든 뒤에는 자유롭게 수정할 수 있습니다.</p></div></div></div>
      <div class="px-5 py-5 sm:px-7">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
          <label class="photo-select-btn">사진 여러 장 선택<input type="file" id="poFiles" class="hidden" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple></label>
          <button type="button" id="poNas" class="photo-select-btn">NAS 에서 선택</button>
          <label class="flex items-center gap-2 text-xs text-stone-600">묶는 기준
            <select id="poGap" class="admin-select !w-auto"><option value="30">30분 이상 간격</option><option value="60">1시간 이상 간격</option><option value="120" selected>2시간 이상 간격</option><option value="240">4시간 이상 간격</option><option value="day">하루 단위</option></select></label>
          <label class="flex items-center gap-2 text-xs text-stone-600"><input type="checkbox" id="poPlace" checked> 위치로 장소 이름 자동 입력 (Google)</label>
        </div>
        <p class="field-help mt-3">먼저 위의 <b>여행날짜</b>와 <b>국가(영문)</b>를 입력하세요. 사진은 한 장당 최대 50MB, 업로드 시 3200px로 줄이고 위치정보는 저장하지 않습니다.</p>
        <div id="poStatus" class="mt-3 text-xs text-stone-500" aria-live="polite"></div>
        <div id="poGroups" class="mt-4 space-y-3"></div>
        <div id="poActions" class="mt-4 hidden"><div class="flex flex-wrap items-center gap-3"><button type="button" id="poCreate" class="rounded-lg bg-stone-900 px-5 py-2.5 text-xs font-medium text-white">선택한 묶음을 섹션으로 만들기</button><span id="poHint" class="text-[11px] text-stone-400"></span></div></div>
      </div>
    </section>

    <section>
      <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><div class="flex items-center gap-3"><span class="step-badge dark">02</span><h2 class="section-heading">여행 작성</h2></div><p class="mt-2 pl-11 text-xs text-stone-400">소제목별 장소, 목적, 추천도, 특징, 본문, 사진, 지출 내역을 작성합니다. 왼쪽 <b class="font-semibold text-stone-500">⋮⋮</b> 를 끌어 순서를 바꾸고, 각 소제목 아래의 <b class="font-semibold text-stone-500">+</b> 로 바로 아래에 추가할 수 있습니다.</p></div><div class="flex flex-wrap gap-2"><button type="button" id="toggleAll" class="add-section-btn" title="모든 섹션을 접어서 한눈에 보기 (순서 바꾸기에 편리)">모두 접기</button><button type="button" id="addSection" class="add-section-btn">+ 소제목 추가</button></div></div>
      <div id="sections" class="space-y-5">
      <?php foreach($travelSections as $i=>$s): $si=si_for($s); $rows=rows_for($s); $expenses=ex_for($s); ?>
        <article class="section-editor section-card overflow-hidden" data-index="<?=$i?>">
          <div class="flex items-center justify-between border-b border-stone-200 bg-[#faf9f6] px-5 py-4 sm:px-6"><span class="section-number text-[10px] font-semibold tracking-[.2em] text-stone-400">SECTION <?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><button type="button" class="remove-section rounded-full px-2.5 py-1.5 text-[11px] text-stone-400 transition hover:bg-red-50 hover:text-red-700">삭제</button></div>
          <div class="px-5 py-6 sm:px-6 sm:py-7">
            <label class="block"><span class="field-label">소제목</span><input name="sections[<?=$i?>][title]" value="<?=tv($s['title']??'')?>" class="section-title admin-input mt-2 text-[15px]" placeholder="예: 트레비 분수의 밤" required></label>
            <input type="hidden" name="sections[<?=$i?>][subsection_info_json]" class="subsection-info-source" value="<?=tv(json_encode($si,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">
            <input type="hidden" name="sections[<?=$i?>][expenses_json]" class="expenses-source" value="<?=tv(json_encode($expenses,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">
            <input type="hidden" name="sections[<?=$i?>][content_html]" class="content-source" value="<?=tv($s['content_html']??'')?>"><input type="hidden" name="sections[<?=$i?>][images]" class="images-source"><input type="hidden" name="sections[<?=$i?>][image_captions]" class="captions-source">
            <div class="subsection-info-panel mt-7 rounded-xl border border-stone-200 bg-[#faf9f6] p-5 sm:p-6">
              <div class="mb-5 flex items-start gap-3"><span class="mini-badge" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg></span><div><h3 class="text-[15px] font-semibold text-stone-900">소제목 정보</h3><p class="mt-1 text-[11px] leading-5 text-stone-400">장소, 목적, 추천도와 특징을 입력합니다.</p></div></div>
              <div class="grid gap-5 sm:grid-cols-2">
                <div class="block sm:col-span-2"><span class="field-label">장소</span><div class="mt-2 google-place-picker" data-location="<?=tv($si['location'])?>" data-url="<?=tv($si['location_url'])?>" data-lat="<?=tv($si['lat']??'')?>" data-lng="<?=tv($si['lng']??'')?>" data-place-id="<?=tv($si['place_id']??'')?>"><div class="google-search-row"><div class="google-place-autocomplete"></div><button type="button" class="google-search-btn place-search-btn">검색하기</button></div><div class="google-search-results place-search-results hidden"></div><input type="hidden" class="subsection-location" value="<?=tv($si['location'])?>"><input type="hidden" class="subsection-location-url" value="<?=tv($si['location_url'])?>"><input type="hidden" class="subsection-location-lat" value="<?=tv($si['lat']??'')?>"><input type="hidden" class="subsection-location-lng" value="<?=tv($si['lng']??'')?>"><input type="hidden" class="subsection-place-id" value="<?=tv($si['place_id']??'')?>"><p class="field-help">검색어를 입력한 뒤 <b>검색하기</b>를 누르고, 결과에서 장소를 선택하세요.</p></div></div>
                <label class="block sm:col-span-2"><span class="field-label">목적</span><input type="text" class="admin-input mt-2 subsection-purpose" value="<?=tv($si['purpose'])?>" placeholder="예: 야경 감상, 맛집 방문"></label>
                <div class="block"><span class="field-label">추천도</span><div class="rating-picker mt-2" role="radiogroup" aria-label="추천도"><?php for($r=1;$r<=5;$r++): ?><button type="button" class="rating-star <?=($r<=$si['rating']?'is-selected':'')?>" data-rating="<?=$r?>" aria-label="<?=$r?>점">★</button><?php endfor; ?><span class="rating-label"><?=($si['rating']>0?$si['rating'].' / 5':'선택 안 함')?></span></div><input type="hidden" class="subsection-rating" value="<?=$si['rating']?>"></div>
                <div class="block"><span class="field-label">특징</span><div class="feature-input-wrap mt-2"><input type="text" class="admin-input feature-input" placeholder="#야경 같은 특징 입력"><button type="button" class="feature-add">추가</button></div><div class="feature-tags mt-3"></div><textarea class="subsection-features-source hidden"><?=tv(implode("\n",$si['features']))?></textarea><p class="field-help">Enter 또는 추가 버튼으로 등록합니다.</p></div>
              </div>
            </div>

            <div class="generic-section-fields mt-7">
              <div class="mb-2 flex items-end justify-between"><div><span class="field-label">본문</span><p class="field-help">글씨 스타일, 링크, 목록 등을 사용해 여행 이야기를 작성하세요.</p></div></div>
              <div class="tt-host"></div>
            </div>

            <div class="section-media-fields mt-7 border-t border-stone-200 pt-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><span class="field-label">사진</span><p class="field-help">JPG, PNG, WEBP, HEIC · 여러 장 선택 가능</p></div><div class="flex flex-wrap justify-end gap-2"><button type="button" class="photo-select-btn nas-pick-btn">NAS 에서 선택</button><label class="photo-select-btn">사진 선택<input type="file" class="image-input hidden" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple></label></div></div><div class="image-list mt-4 space-y-2.5" data-uploaded='<?=tv(json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'></div><div class="drop-zone mt-3 px-4 py-3 text-center text-[11px] text-stone-400">사진을 선택하면 미리보기와 설명 입력란이 표시됩니다.</div></div>

            <div class="expense-panel mt-7 border-t border-stone-200 pt-6"><div class="flex items-end justify-between gap-4"><div><span class="field-label">지출 내역 <span class="font-normal text-stone-400">(선택사항)</span></span><p class="field-help">여행지에서 발생한 비용을 기록하면 통화별 합계와 원화 환산액을 계산합니다.</p></div><button type="button" class="expense-add">+ 지출 추가</button></div><div class="expense-list mt-4"></div><div class="expense-summary mt-4 hidden"></div></div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    </section>

    <div class="action-bar fixed inset-x-0 bottom-0 z-40 border-t border-stone-200 bg-[#f7f6f2]/95 backdrop-blur-xl"><div class="admin-shell flex items-center justify-between gap-3 py-3"><p class="hidden text-[11px] text-stone-400 sm:block">기본 정보와 소제목 내용을 확인한 후 저장하세요.</p><div class="ml-auto flex items-center gap-2"><div class="state-box"><label class="sr-only" for="stateSel">공개 상태</label><select name="state" id="stateSel" class="state-sel"><?php foreach(['public'=>'공개','scheduled'=>'예약 공개','draft'=>'초안 (비공개)','hidden'=>'숨김'] as $__k=>$__l): ?><option value="<?=$__k?>"<?=$__k===$curState?' selected':''?>><?=$__l?></option><?php endforeach; ?></select><input type="datetime-local" name="publish_at" id="pubAt" class="state-dt" value="<?=tv(str_replace(' ','T',(string)($trip['publish_at']??'')))?>"<?=$curState==='scheduled'?'':' hidden'?>><label class="pin-toggle" title="홈의 최근 여행 맨 앞에 고정"><input type="checkbox" name="pinned" value="1"<?=!empty($trip['pinned'])?' checked':''?>><span>📌 고정</span></label><span id="autosaveState" class="autosave-state" aria-live="polite"></span></div><a href="index.php" class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-xs text-stone-600">취소</a><button type="submit" formaction="preview.php" formtarget="_blank" class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-xs font-medium text-stone-700">미리보기</button><button type="submit" class="rounded-lg bg-stone-900 px-5 py-2.5 text-xs font-medium text-white">저장</button></div></div></div>
  </form>
</div>
<script>window.GOOGLE_MAPS_API_KEY=<?=json_encode($googleMapsKey)?>;</script><?php if($googleMapsKey!=='' && $googleMapsKey!=='PASTE_YOUR_GOOGLE_MAPS_API_KEY_HERE'): ?><script src="https://maps.googleapis.com/maps/api/js?key=<?=rawurlencode($googleMapsKey)?>&loading=async"></script><?php endif; ?><script>window.ADMIN_UPLOAD_KEY=<?=json_encode($upload_key)?>;window.IS_NEW=<?=json_encode($is_new)?>;window.TRAVEL_DATE=<?=json_encode($basic['date'])?>;window.PHOTO_MAX_EDGE=<?=(int)PHOTO_MAX_EDGE?>;</script><script src="photo-source.js?v=<?=@filemtime(__DIR__.'/photo-source.js')?>"></script><script src="vendor/naeggeolog-editor.js?v=<?=@filemtime(__DIR__.'/vendor/naeggeolog-editor.js')?>"></script><script src="editor.js?v=<?=@filemtime(__DIR__.'/editor.js')?>"></script><script src="photo-organizer.js?v=<?=@filemtime(__DIR__.'/photo-organizer.js')?>"></script>
<script>
(function(){
  const sel=document.getElementById('stateSel'), dt=document.getElementById('pubAt');
  const sync=()=>{ dt.hidden = sel.value!=='scheduled'; dt.required = sel.value==='scheduled'; if(sel.value==='scheduled'&&!dt.value){ const d=new Date(Date.now()+864e5); d.setMinutes(0,0,0); d.setHours(9); const p=n=>String(n).padStart(2,'0'); dt.value=d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':00'; } };
  sel.addEventListener('change',sync); sync();
  // 태그 입력
  const box=document.getElementById('tagBox'), chips=document.getElementById('tagChips'), inp=document.getElementById('tagInput'), field=document.getElementById('tagsField');
  let tags=field.value.split(',').map(x=>x.trim()).filter(Boolean);
  const render=()=>{ chips.innerHTML=''; tags.forEach((t,i)=>{ const c=document.createElement('span'); c.className='tag-chip'; c.textContent='#'+t; const b=document.createElement('button'); b.type='button'; b.setAttribute('aria-label',t+' 삭제'); b.textContent='×'; b.onclick=()=>{ tags.splice(i,1); render(); }; c.appendChild(b); chips.appendChild(c); }); field.value=tags.join(','); field.dispatchEvent(new Event('change',{bubbles:true})); };
  const add=v=>{ v=v.replace(/^#+/,'').trim().slice(0,24); if(!v||tags.length>=12||tags.some(t=>t.toLowerCase()===v.toLowerCase())) return; tags.push(v); render(); };
  inp.addEventListener('keydown',e=>{ if(e.key==='Enter'||e.key===','){ e.preventDefault(); add(inp.value); inp.value=''; } else if(e.key==='Backspace'&&!inp.value&&tags.length){ tags.pop(); render(); } });
  inp.addEventListener('change',()=>{ if(inp.value&&tags.every(t=>t!==inp.value)){ add(inp.value); inp.value=''; } });
  inp.addEventListener('blur',()=>{ if(inp.value){ add(inp.value); inp.value=''; } });
  box.addEventListener('click',()=>inp.focus()); render();
})();
</script>
<script>window.AUTOSAVE_KEY=<?=json_encode($__dkey)?>;window.AUTOSAVE_CSRF=<?=json_encode(csrf_token())?>;</script><script src="autosave.js?v=<?=@filemtime(__DIR__.'/autosave.js')?>"></script>
<?php admin_foot(); ?>

<?php
require_once __DIR__.'/auth.php';
require_admin();
require_once __DIR__.'/../theme.php';
require_once __DIR__.'/style.php';

$theme = $TRAVEL_THEME;
$labels = [
  'page_bg' => ['페이지 배경', '사이트 전체의 기본 배경색'],
  'surface' => ['카드 / 콘텐츠 배경', '여행 카드, 입력 영역, 본문 카드 등에 사용'],
  'surface_alt' => ['보조 배경', '필터, TOC, 보조 영역 등에 사용'],
  'text' => ['본문 글자', '일반 본문과 주요 텍스트'],
  'muted' => ['보조 글자', '날짜, 설명, 메타 정보 등'],
  'heading' => ['제목 글자', '페이지 제목과 주요 헤딩'],
  'border' => ['선 / 테두리', '카드와 구분선'],
  'accent' => ['포인트 색상', '활성 버튼, 주요 강조 요소'],
  'accent_soft' => ['포인트 보조색', '포인트 색상의 연한 배경'],
  'link' => ['링크 색상', '본문 링크와 장소 링크'],
  'header_bg' => ['헤더 배경', '상단 메뉴 배경'],
  'footer_bg' => ['푸터 배경', '하단 영역 배경'],
  'footer_text' => ['푸터 글자', '푸터의 글자와 링크'],
  'hero_overlay' => ['Hero 오버레이', '첫 화면 Hero 이미지 위에 깔리는 색상']
];

// 이 페이지는 includes/data.php의 e() 함수에 의존하지 않도록 자체 이스케이프 함수를 사용합니다.
function theme_admin_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

admin_head('테마 관리');
$theme_vars = '';
foreach ($theme as $key => $value) {
    $theme_vars .= '--theme-' . theme_admin_e($key) . ':' . theme_admin_e($value) . ';';
}
?>
<div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
  <div>
    <p class="text-xs tracking-[.18em] text-stone-400">THEME MANAGER</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">사이트 테마</h1>
    <p class="mt-2 text-sm text-stone-500">색상을 하나씩 바꾸면서 오른쪽 미리보기에서 전체 분위기를 확인할 수 있습니다. 다크 모드 색은 별도로 정해져 있고, 포인트·링크 색만 여기서 정한 색을 기준으로 자동 계산됩니다.</p>
  </div>
  <a href="index.php" class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-xs text-stone-600 transition hover:bg-stone-50">관리자 홈</a>
</div>

<div id="saveMessage" class="mt-6 hidden rounded-lg border border-stone-200 bg-white px-4 py-3 text-xs text-stone-600"></div>

<style>
.theme-manager-grid{display:grid;grid-template-columns:minmax(320px,380px) minmax(0,1fr);gap:24px;align-items:start}
.theme-preview-shell{display:block;width:100%;min-width:0}
#themePreview{display:block;width:100%;min-height:420px;box-sizing:border-box}
@media(max-width:1023px){.theme-manager-grid{grid-template-columns:1fr}}
</style>
<style id="theme-preview-vars">:root{<?=$theme_vars?>}</style>

<div class="mt-8 theme-manager-grid">
  <section class="section-card p-5 sm:p-6">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h2 class="section-heading">색상 설정</h2>
        <p class="section-description">현재 사이트에서 변경 가능한 핵심 색상입니다.</p>
      </div>
      <button type="button" id="resetTheme" class="toolbar-btn">기본값</button>
    </div>

    <div class="mt-6 grid gap-4">
      <?php foreach($labels as $key => [$label,$help]): ?>
        <label class="grid grid-cols-[1fr_auto] items-center gap-3">
          <span>
            <span class="block text-xs font-semibold text-stone-700"><?=theme_admin_e($label)?></span>
            <span class="mt-1 block text-[10px] leading-4 text-stone-400"><?=theme_admin_e($help)?></span>
          </span>
          <span class="flex items-center gap-2">
            <input class="theme-color h-9 w-12 cursor-pointer rounded-md border border-stone-200 bg-white p-1" type="color" data-key="<?=theme_admin_e($key)?>" value="<?=theme_admin_e($theme[$key])?>">
            <code class="theme-value w-[74px] rounded-md border border-stone-200 bg-stone-50 px-2 py-2 text-center text-[10px] text-stone-500" data-value-for="<?=theme_admin_e($key)?>"><?=theme_admin_e($theme[$key])?></code>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="mt-7 flex gap-2 border-t border-stone-200 pt-5">
      <button type="button" id="saveTheme" class="flex-1 rounded-lg bg-stone-900 px-4 py-3 text-xs font-semibold text-white transition hover:bg-stone-700">테마 저장</button>
      <a href="../index.php" target="_blank" rel="noopener" class="rounded-lg border border-stone-300 bg-white px-4 py-3 text-xs text-stone-600 transition hover:bg-stone-50">사이트 보기 ↗</a>
    </div>
  </section>

  <section class="section-card theme-preview-shell overflow-hidden p-5 sm:p-6">
    <div class="flex items-end justify-between gap-3">
      <div><h2 class="section-heading">실시간 미리보기</h2><p class="section-description">저장하기 전에도 색상 변경이 바로 반영됩니다.</p></div>
      <span class="rounded-full border border-stone-200 bg-stone-50 px-2.5 py-1 text-[10px] text-stone-400">LIVE</span>
    </div>

    <div id="themePreview" class="mt-5 overflow-hidden rounded-[16px] border border-stone-200" style="background:var(--theme-page_bg);color:var(--theme-text)">
      <div class="theme-preview-header border-b px-5 py-4" style="background:var(--theme-header_bg);border-color:var(--theme-border)">
        <div class="flex items-center justify-between gap-4">
          <div class="font-serif text-lg" style="color:var(--theme-heading)"><?=htmlspecialchars(SITE_TITLE,ENT_QUOTES,'UTF-8')?></div>
          <div class="flex gap-1 text-[11px]" style="color:var(--theme-muted)"><span class="rounded-full px-2 py-1" style="background:var(--theme-accent);color:#fff">전체 여행</span><span class="px-2 py-1">여행 지도</span><span class="px-2 py-1">검색</span></div>
        </div>
      </div>
      <div class="p-5 sm:p-7">
        <div class="relative min-h-[220px] overflow-hidden rounded-xl p-6" style="background:var(--theme-hero_overlay);color:#fff">
          <div class="absolute inset-0 opacity-30" style="background:linear-gradient(135deg,var(--theme-accent),transparent 65%)"></div>
          <div class="relative flex min-h-[175px] flex-col justify-end">
            <p class="text-[10px] tracking-[.18em] opacity-60">PERSONAL TRAVEL ARCHIVE</p>
            <h3 class="mt-2 font-serif text-3xl" style="color:#fff">길 위에서 만난 장면들</h3>
            <p class="mt-2 max-w-md text-xs opacity-70">여행의 기록, 사진, 장소를 한 곳에 모았습니다.</p>
          </div>
        </div>

        <div class="mt-7 flex items-end justify-between border-b pb-3" style="border-color:var(--theme-border)">
          <div><p class="text-[9px] font-bold tracking-[.18em]" style="color:var(--theme-muted)">ARCHIVE</p><h4 class="mt-1 font-serif text-2xl" style="color:var(--theme-heading)">여행 기록</h4></div>
          <span class="text-[10px]" style="color:var(--theme-muted)">33 journeys</span>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <article class="rounded-xl border p-4" style="background:var(--theme-surface);border-color:var(--theme-border)">
            <div class="h-24 rounded-lg" style="background:linear-gradient(135deg,var(--theme-accent_soft),var(--theme-border))"></div>
            <p class="mt-3 text-[10px]" style="color:var(--theme-muted)">2024. 02 · 베트남</p>
            <h5 class="mt-1 text-sm font-semibold" style="color:var(--theme-heading)">나트랑은 처음이지?</h5>
            <a href="#" onclick="return false" class="mt-2 inline-block text-[11px] underline underline-offset-4" style="color:var(--theme-link)">장소 보기 ↗</a>
          </article>
          <article class="rounded-xl border p-4" style="background:var(--theme-surface);border-color:var(--theme-border)">
            <div class="h-24 rounded-lg" style="background:linear-gradient(135deg,var(--theme-border),var(--theme-accent_soft))"></div>
            <p class="mt-3 text-[10px]" style="color:var(--theme-muted)">2024. 08 · 일본</p>
            <h5 class="mt-1 text-sm font-semibold" style="color:var(--theme-heading)">교토의 하루</h5>
            <a href="#" onclick="return false" class="mt-2 inline-block text-[11px] underline underline-offset-4" style="color:var(--theme-link)">여행기 보기 ↗</a>
          </article>
        </div>
      </div>
      <div class="px-5 py-5" style="background:var(--theme-footer_bg);color:var(--theme-footer_text)"><div class="text-xs font-semibold"><?=htmlspecialchars(SITE_TITLE,ENT_QUOTES,'UTF-8')?></div><div class="mt-1 text-[10px] opacity-70">여행의 기록을 오래 남깁니다.</div></div>
    </div>
  </section>
</div>

<script>
const defaults = <?=json_encode($theme, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const inputs = [...document.querySelectorAll('.theme-color')];
const msg = document.getElementById('saveMessage');
function setVar(key,value){
  document.documentElement.style.setProperty('--theme-'+key,value);
  const label=document.querySelector('[data-value-for="'+key+'"]');
  if(label) label.textContent=value;
}
// 초기 상태도 즉시 미리보기에 반영
inputs.forEach(input=>setVar(input.dataset.key,input.value));
inputs.forEach(input=>input.addEventListener('input',()=>setVar(input.dataset.key,input.value)));
document.getElementById('resetTheme').addEventListener('click',()=>inputs.forEach(input=>{input.value=defaults[input.dataset.key];setVar(input.dataset.key,input.value)}));
document.getElementById('saveTheme').addEventListener('click',async()=>{
  const theme={}; inputs.forEach(input=>theme[input.dataset.key]=input.value);
  const body=new URLSearchParams({csrf:'<?=theme_admin_e(csrf_token())?>',theme:JSON.stringify(theme)});
  msg.classList.remove('hidden'); msg.textContent='저장 중입니다…';
  try{
    const r=await fetch('theme-save.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body});
    const data=await r.json();
    msg.textContent=data.ok?'테마가 저장되었습니다. 사이트 새로고침 후에도 유지됩니다.':(data.message||'저장하지 못했습니다.');
  }catch(e){msg.textContent='저장 중 오류가 발생했습니다.';}
});
</script>
<?php admin_foot(); ?>

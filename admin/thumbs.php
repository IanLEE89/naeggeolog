<?php
// 이미지 최적화: 모든 사진의 썸네일(480/960/1600)을 미리 만들어 둔다.
// 방문자가 처음 볼 때 NAS 가 이미지를 변환하느라 멈추는 일을 없애기 위한 도구. 한 장씩 순서대로 처리한다.
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/data.php';
require_once __DIR__.'/../includes/images.php';
require_once __DIR__.'/../site.php';

const THUMB_SET = [480, 960, 1600];

function th_missing(string $rel): int {
    $info = photo_info($rel); if (!$info || !in_array($info['type'], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return 0;
    $n = 0; $ext = photo_thumb_ext($info, photo_webp_ok());
    foreach (THUMB_SET as $w) {
        $ww = min($w, $info['w']);
        if (!is_file(photo_thumb_file($info, $rel, $ww, $ext))) $n++;
        if ($w >= $info['w']) break;
    }
    return $n;
}
function th_all_rel(): array {
    global $TRAVEL_SITE;
    $set = [];
    foreach (store()->all() as $t) foreach (trip_photo_paths($t) as $p) $set[$p] = true;
    $h = (string)($TRAVEL_SITE['hero_image'] ?? '');
    if (str_starts_with($h, 'travel-photos/')) $set[substr($h, 14)] = true;
    return array_keys($set);
}

// ── AJAX: 한 장 처리 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
    $rel = (string)($_POST['rel'] ?? '');
    @set_time_limit(90);
    $made = photo_abs($rel) === null ? 0 : photo_pregen($rel, THUMB_SET);
    echo json_encode(['ok' => true, 'made' => $made, 'left' => th_missing($rel)]); exit;
}

$rels = th_all_rel(); $todo = []; $okCount = 0; $noGd = !photo_gd_ok();
if (!$noGd) foreach ($rels as $r) { if (th_missing($r) > 0) $todo[] = $r; else $okCount++; }
$size = 0; $cnt = 0;
$tdir = rtrim(CACHE_DIR, '/\\') . '/thumbs';
if (is_dir($tdir)) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tdir, FilesystemIterator::SKIP_DOTS)) as $f) { if ($f->isFile()) { $size += $f->getSize(); $cnt++; } }

require_once __DIR__.'/style.php';
admin_head('이미지 최적화');
?>
<div class="adm-wrap">
  <?php admin_nav('thumbs'); ?>
  <section>
    <p class="text-xs tracking-[.18em] text-stone-400">IMAGE OPTIMIZER</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">이미지 최적화</h1>
    <p class="mt-2 text-sm leading-6 text-stone-500">사진을 화면 크기에 맞는 가벼운 WebP 로 미리 만들어 둡니다. 미리 만들어 두면 방문자가 페이지를 열 때 NAS 가 이미지를 변환하지 않아 로딩이 멈추지 않습니다.<br>사진이 많으면 PC 에서 미리 만들어 <code>cache/thumbs</code> 에 올리는 편이 훨씬 빠릅니다(<code>pc-tools/make_thumbs.bat</code> 참고). 새로 올리는 사진은 업로드할 때 자동으로 만들어집니다. 이 화면은 <b>이미 있는 사진</b>이나 <b>캐시를 비운 뒤</b>에 한 번 실행하세요.</p>

    <div class="mt-6 grid gap-3 sm:grid-cols-3">
      <div class="section-card p-4"><p class="text-[11px] text-stone-400">전체 사진</p><p class="mt-1 text-2xl font-semibold text-stone-900"><?=count($rels)?></p></div>
      <div class="section-card p-4"><p class="text-[11px] text-stone-400">준비 완료</p><p class="mt-1 text-2xl font-semibold text-stone-900"><?=$okCount?></p></div>
      <div class="section-card p-4"><p class="text-[11px] text-stone-400">캐시 용량</p><p class="mt-1 text-2xl font-semibold text-stone-900"><?=round($size / 1048576, 1)?><small class="ml-1 text-xs font-normal text-stone-400">MB · <?=$cnt?>개</small></p></div>
    </div>

    <?php if ($noGd): ?>
      <div class="adm-note" style="border-color:#fde68a;background:#fffbeb;color:#92400e">서버에 GD 확장이 없어 썸네일을 만들 수 없습니다. Web Station → PHP 프로필 → 확장 모듈에서 <b>gd</b> 를 켜 주세요. (켜기 전에는 원본 사진이 그대로 표시됩니다.)</div>
    <?php else: ?>
      <div class="section-card mt-5 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p id="thState" class="text-sm text-stone-600"><?=count($todo) ? '아직 준비되지 않은 사진 <b>'.count($todo).'</b>장' : '✅ 모든 사진이 준비되었습니다.'?></p>
          <button id="thGo" class="adm-primary" style="height:42px" <?=count($todo)?'':'disabled'?>>지금 만들기</button>
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-stone-200"><div id="thBar" class="h-full w-0 rounded-full bg-stone-900 transition-all"></div></div>
        <p id="thLog" class="mt-2 text-[11px] text-stone-400">창을 닫지 마세요. 중간에 멈춰도 다시 누르면 이어서 진행합니다.</p>
      </div>
      <?php if (!photo_webp_ok()): ?><div class="adm-note">이 서버의 GD 는 WebP 를 지원하지 않아 JPEG/PNG 썸네일로 만듭니다.</div><?php endif; ?>
    <?php endif; ?>
  </section>
</div>
<?php if (!$noGd && $todo): ?>
<script>
(function(){
  const list = <?=json_encode($todo, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>, csrf = <?=json_encode(csrf_token())?>;
  const go = document.getElementById('thGo'), bar = document.getElementById('thBar'), st = document.getElementById('thState'), log = document.getElementById('thLog');
  let running = false;
  go.addEventListener('click', async () => {
    if (running) return; running = true; go.disabled = true; go.style.opacity = .5;
    let done = 0, fail = 0;
    for (const rel of list) {
      st.innerHTML = '만드는 중… <b>' + (done + fail + 1) + '</b> / ' + list.length;
      log.textContent = rel;
      try {
        const fd = new FormData(); fd.append('csrf', csrf); fd.append('rel', rel);
        const r = await fetch('thumbs.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json(); if (j.ok && j.left === 0) done++; else fail++;
      } catch (e) { fail++; }
      bar.style.width = ((done + fail) / list.length * 100) + '%';
    }
    st.innerHTML = '완료 — 성공 <b>' + done + '</b>장' + (fail ? ', 건너뜀 <b>' + fail + '</b>장(메모리 부족 등)' : '');
    log.textContent = '이제 페이지를 열 때 서버가 이미지를 변환하지 않습니다.'; running = false;
  });
})();
</script>
<?php endif; ?>
<?php admin_foot(); ?>

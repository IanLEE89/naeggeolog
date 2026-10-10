<?php
// 파일 — 공유 폴더 목록 → 폴더 탐색 → 다운로드/미리보기. 읽기 전용(웹에서 지우거나 바꿀 수 없음)
require_once dirname(__DIR__, 2) . '/_boot.php';
$u = hub_require_login();
$owner = hub_is_owner($u);
$shares = hub_shares_for($u);
$sid = (int)($_GET['s'] ?? 0);

/** 이 페이지 주소 만들기 */
function fx_url(array $q): string {
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return 'index.php' . ($q ? '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986) : '');
}
function fx_get(int $s, string $rel, array $extra = []): string { return 'get.php?' . http_build_query(['s' => $s, 'p' => $rel] + $extra, '', '&', PHP_QUERY_RFC3986); }
$ICONS = [
    'dir'   => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
    'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m21 16-5-5-9 9"/>',
    'video' => '<rect x="3" y="5" width="14" height="14" rx="2"/><path d="m17 10 4-2.5v9L17 14"/>',
    'audio' => '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
    'pdf'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 15h2a1.5 1.5 0 0 0 0-3H8v5"/>',
    'doc'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h6"/>',
    'sheet' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M4 9h16M4 15h16M10 3v18"/>',
    'slide' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M12 16v4M8 20h8"/>',
    'zip'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M10 3v2h2v2h-2v2h2v2h-2v2"/>',
    'file'  => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
];
$svg = fn(string $k, string $cls = '') => '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($ICONS[$k] ?? $ICONS['file']) . '</svg>';

// 공유 폴더가 하나뿐이면 바로 그 안으로
if ($sid === 0 && count($shares) === 1 && !$owner) { header('Location: ' . fx_url(['s' => (int)$shares[0]['id']])); exit; }

/* ───────── 공유 폴더 목록 ───────── */
if ($sid === 0) {
    hub_head('파일', $u, 'files'); ?>
    <p class="hb-eyebrow">FILES</p>
    <h1 class="hb-h1">파일</h1>
    <p class="hb-sub"><?=$owner ? 'NAS 에서 공개하도록 지정한 폴더입니다. 누가 어떤 폴더를 볼지는 관리자 화면에서 정합니다.' : '공유받은 폴더를 열어 파일을 바로 내려받을 수 있어요.'?></p>
    <?php if (!$shares): ?>
      <div class="hb-card hb-pad hb-empty-card">
        <?=$svg('dir', 'hb-empty-ico')?>
        <?php if ($owner): ?><p><b>아직 공개한 폴더가 없어요.</b><br>NAS 의 폴더를 골라 내 공간에 연결하세요.</p><p><a class="hb-btn hb-btn-primary" href="<?=e(hub_url('../admin/hub-folders.php'))?>">공유 폴더 설정</a></p>
        <?php else: ?><p>공유받은 폴더가 없어요. 관리자에게 요청하세요.</p><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="hb-shares">
      <?php foreach ($shares as $s): $ok = is_dir((string)$s['path']) && is_readable((string)$s['path']); ?>
        <a class="hb-share" href="<?=e(fx_url(['s' => (int)$s['id']]))?>">
          <?=$svg('dir', 'hb-share-ico')?>
          <span><b><?=e($s['name'])?></b><?php if ($s['note'] !== ''): ?><small><?=e($s['note'])?></small><?php endif; ?>
          <?php if ($owner): ?><code><?=e($s['path'])?></code><?php endif; ?>
          <?php if (!$ok): ?><em>지금 열 수 없음</em><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
      </div>
      <?php if ($owner): ?><p class="hb-foot-note"><a href="<?=e(hub_url('../admin/hub-folders.php'))?>">공유 폴더 추가 · 변경 →</a></p><?php endif; ?>
    <?php endif;
    hub_foot();
    exit;
}

/* ───────── 폴더 탐색 ───────── */
$share = hub_can_access($u, $sid);
if (!$share) { http_response_code(404); hub_head('파일', $u, 'files'); echo '<p class="hb-empty">폴더를 찾을 수 없거나 접근 권한이 없습니다. <a href="index.php">파일 홈으로</a></p>'; hub_foot(); exit; }
$r = hub_resolve($share, (string)($_GET['p'] ?? ''));
$listing = $r && is_dir($r['abs']) ? hub_list_dir($r['abs'], $r['root']) : [null, null];
[$dirs, $files] = $listing;

if ($r && is_file($r['abs'])) { header('Location: ' . fx_get($sid, $r['rel'])); exit; }   // 파일 주소로 들어오면 바로 다운로드

$sort = in_array($_GET['sort'] ?? '', ['name', 'date', 'size'], true) ? $_GET['sort'] : 'name';
$desc = ($_GET['o'] ?? ($sort === 'name' ? 'asc' : 'desc')) === 'desc';
$cmp = function ($a, $b) use ($sort, $desc) {
    $c = $sort === 'date' ? $a['mtime'] <=> $b['mtime'] : ($sort === 'size' ? $a['size'] <=> $b['size'] : 0);
    if ($c === 0) $c = strnatcasecmp($a['name'], $b['name']);
    return $desc ? -$c : $c;
};
if ($dirs !== null) { usort($dirs, $cmp); usort($files, $cmp); }
$rel = $r['rel'] ?? '';
$limit = max(HUB_LIST_LIMIT, (int)($_GET['limit'] ?? 0));
$total = $dirs === null ? 0 : count($dirs) + count($files);
$bytes = $files ? array_sum(array_column($files, 'size')) : 0;
$segs = $rel === '' ? [] : explode('/', $rel);
$canUpFlag = hub_can_upload($u, $share);                                  // 주인 + 폴더에서 업로드 허용
$canUp = $canUpFlag && $dirs !== null && is_writable($r['abs']);         // + 웹 서버가 실제로 쓸 수 있음
$title = $segs ? end($segs) : $share['name'];
$q = ['s' => $sid, 'p' => $rel, 'sort' => $sort === 'name' ? '' : $sort, 'o' => in_array($_GET['o'] ?? '', ['asc', 'desc'], true) ? $_GET['o'] : ''];

hub_head($title, $u, 'files');
?>
<nav class="hb-crumbs" aria-label="경로">
  <a href="index.php">파일</a>
  <a href="<?=e(fx_url(['s' => $sid]))?>"><?=e($share['name'])?></a>
  <?php $acc = []; foreach ($segs as $i => $sg): $acc[] = $sg; ?>
    <?php if ($i < count($segs) - 1): ?><a href="<?=e(fx_url(['s' => $sid, 'p' => implode('/', $acc)]))?>"><?=e($sg)?></a><?php else: ?><span aria-current="page"><?=e($sg)?></span><?php endif; ?>
  <?php endforeach; ?>
</nav>
<div class="hb-folder-head">
  <div>
    <h1 class="hb-h1"><?=e($title)?></h1>
    <?php if ($dirs !== null): ?><p class="hb-sub"><?=count($dirs) ? '폴더 ' . number_format(count($dirs)) . '개 · ' : ''?>파일 <?=number_format(count($files))?>개<?=$bytes ? ' · ' . e(hub_fmt_size($bytes)) : ''?></p><?php endif; ?>
  </div>
  <div class="hb-head-acts">
    <?php if ($segs): $up = implode('/', array_slice($segs, 0, -1)); ?><a class="hb-btn" href="<?=e(fx_url(['s' => $sid, 'p' => $up]))?>">↑ 상위 폴더</a><?php endif; ?>
    <?php if ($canUp): ?>
      <button type="button" class="hb-btn" data-mkdir>새 폴더</button>
      <details class="hb-up-menu">
        <summary class="hb-btn hb-btn-primary"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V7M7 12l5-5 5 5M5 4h14"/></svg>올리기</summary>
        <div class="hb-menu">
          <button type="button" data-pick="files">파일 선택…</button>
          <button type="button" data-pick="folder">폴더째 올리기…</button>
          <p class="hb-menu-tip">파일·폴더를 이 화면에 끌어다 놓아도 됩니다.</p>
        </div>
      </details>
    <?php endif; ?>
  </div>
</div>
<?php if ($canUp): ?>
  <input type="file" multiple hidden data-input="files">
  <input type="file" multiple webkitdirectory hidden data-input="folder">
  <div hidden data-uploader data-s="<?=$sid?>" data-p="<?=e($rel)?>" data-csrf="<?=e(hub_csrf())?>" data-chunk="<?=hub_chunk_size()?>" data-url="upload.php" data-folder="<?=e($title)?>"></div>
  <div class="hb-drop" data-drop hidden><div><svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V7M7 12l5-5 5 5M5 4h14"/></svg><b>여기에 놓으면 “<?=e($title)?>”에 올라갑니다</b><span>폴더도 그대로 올라가요</span></div></div>
<?php elseif ($owner && $dirs !== null && empty($share['allow_upload'])): ?>
  <p class="hb-up-off">이 폴더는 업로드가 꺼져 있어요. <a href="<?=e(hub_url('../admin/hub-folders.php?edit=' . $sid))?>">폴더 설정에서 켜기 →</a></p>
<?php endif; ?>
<?php if ($canUpFlag && !$canUp && $dirs !== null): ?>
  <p class="hb-up-off">업로드가 켜져 있지만 웹 서버(http)에 이 폴더의 <b>쓰기 권한</b>이 없어요. DSM → 제어판 → 공유 폴더 → 편집 → 권한 → 시스템 내부 사용자 → <b>http 읽기/쓰기</b>를 체크하세요.</p>
<?php endif; ?>

<?php if ($dirs === null): ?>
  <div class="hb-card hb-pad hb-empty-card">
    <p><b>이 폴더를 열 수 없어요.</b></p>
    <?php if ($owner): ?>
      <p class="hb-muted">경로 <code><?=e($share['path'])?></code> 를 웹 서버가 읽지 못합니다. ① File Station 에서 이 공유 폴더의 <b>http</b> 사용자에게 읽기 권한이 있는지, ② Web Station → PHP 프로필의 <b>open_basedir</b> 에 경로가 들어 있는지 확인하세요. <a href="<?=e(hub_url('../admin/hub-folders.php'))?>">공유 폴더 설정</a>에서 점검할 수 있습니다.</p>
    <?php else: ?><p class="hb-muted">관리자에게 알려 주세요.</p><?php endif; ?>
  </div>
<?php elseif ($total === 0): ?>
  <div class="hb-card hb-pad hb-empty-card"><?=$svg('dir', 'hb-empty-ico')?><p>빈 폴더입니다.</p></div>
<?php else: ?>
<div class="hb-toolbar">
  <label class="hb-filter"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg><input type="search" placeholder="이 폴더에서 이름 찾기" data-filter aria-label="이름으로 거르기"></label>
  <div class="hb-seg" role="group" aria-label="정렬">
    <?php foreach (['name' => '이름', 'date' => '날짜', 'size' => '크기'] as $k => $lb): $on = $sort === $k; $o = $on ? ($desc ? 'asc' : 'desc') : ($k === 'name' ? 'asc' : 'desc'); ?>
      <a href="<?=e(fx_url(['s' => $sid, 'p' => $rel, 'sort' => $k === 'name' ? '' : $k, 'o' => $o]))?>" <?=$on ? 'aria-current="true"' : ''?>><?=$lb?><?=$on ? ($desc ? ' ↓' : ' ↑') : ''?></a>
    <?php endforeach; ?>
  </div>
  <div class="hb-seg" role="group" aria-label="보기 방식">
    <button type="button" data-view="list" aria-pressed="true" title="목록"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg></button>
    <button type="button" data-view="grid" aria-pressed="false" title="사진처럼 크게"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg></button>
  </div>
</div>

<ul class="hb-files" data-files>
<?php
$shown = 0;
foreach ($dirs as $d):
    if ($shown++ >= $limit) break;
    $p = ltrim($rel . '/' . $d['name'], '/'); ?>
  <li class="hb-row is-dir" data-name="<?=e(mb_strtolower($d['name']))?>">
    <a class="hb-row-main" href="<?=e(fx_url(['s' => $sid, 'p' => $p, 'sort' => $q['sort'], 'o' => $q['o']]))?>">
      <span class="hb-thumb"><?=$svg('dir')?></span>
      <span class="hb-name"><?=e($d['name'])?></span>
    </a>
    <span class="hb-date"><?=$d['mtime'] ? e(date('Y-m-d', $d['mtime'])) : ''?></span><span class="hb-size">폴더</span><span class="hb-act"></span>
  </li>
<?php endforeach;
foreach ($files as $f):
    if ($shown++ >= $limit) break;
    $p = ltrim($rel . '/' . $f['name'], '/');
    $kind = hub_kind($f['name']);
    $inline = hub_inline_mime($f['name']);
    $hasThumb = in_array($kind, ['image', 'video'], true) && hub_syno_thumb($r['abs'] . '/' . $f['name']) !== null;
    $tryThumb = $hasThumb || ($kind === 'image' && $inline && preg_match('/\.(jpe?g|png|webp)$/i', $f['name']));   // 없으면 서버가 만들어 줌
    // 미리보기: 브라우저가 열 수 있는 사진은 원본, HEIC·RAW 는 Synology 큰 썸네일
    $preview = '';
    if ($kind === 'image') $preview = $inline ? fx_get($sid, $p, ['inline' => 1]) : ($hasThumb ? fx_get($sid, $p, ['thumb' => 1, 'big' => 1]) : '');
    elseif (($kind === 'video' || $kind === 'audio') && $inline) $preview = fx_get($sid, $p, ['inline' => 1]);
    $open = $kind === 'pdf' || ($inline && strpos($inline, 'text/') === 0) ? fx_get($sid, $p, ['inline' => 1]) : '';
    $dl = fx_get($sid, $p);
    ?>
  <li class="hb-row" data-name="<?=e(mb_strtolower($f['name']))?>" data-kind="<?=$kind?>">
    <a class="hb-row-main" href="<?=e($preview ?: ($open ?: $dl))?>" <?=$preview ? 'data-preview="' . e($preview) . '" data-type="' . $kind . '" data-dl="' . e($dl) . '"' : ($open ? 'target="_blank" rel="noopener"' : 'download')?>>
      <span class="hb-thumb hb-k-<?=$kind?>"><?=$svg($kind)?><?php if ($tryThumb): ?><img src="<?=e(fx_get($sid, $p, ['thumb' => 1]))?>" alt="" loading="lazy" decoding="async" onerror="this.remove()"><?php endif; ?></span>
      <span class="hb-name"><?=e($f['name'])?></span>
    </a>
    <span class="hb-date"><?=e(date('Y-m-d H:i', $f['mtime']))?></span>
    <span class="hb-size"><?=e(hub_fmt_size($f['size']))?></span>
    <span class="hb-act"><a class="hb-dl" href="<?=e($dl)?>" download title="내려받기" aria-label="<?=e($f['name'])?> 내려받기"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg></a></span>
  </li>
<?php endforeach; ?>
</ul>
<p class="hb-empty" data-nomatch hidden>이름이 일치하는 항목이 없어요.</p>
<?php if ($total > $limit): ?>
  <p class="hb-more"><a class="hb-btn" href="<?=e(fx_url($q + ['limit' => $limit + HUB_LIST_LIMIT]))?>">더 보기 (<?=number_format($total - $limit)?>개 남음)</a></p>
<?php endif; ?>
<?php endif; ?>

<dialog class="hb-viewer" data-viewer aria-label="미리보기">
  <div class="hb-viewer-bar">
    <span class="hb-viewer-name" data-v-name></span>
    <a class="hb-btn" data-v-dl href="#" download>내려받기</a>
    <button type="button" class="hb-btn" data-v-close aria-label="닫기">닫기 ✕</button>
  </div>
  <div class="hb-viewer-stage" data-v-stage></div>
  <button type="button" class="hb-viewer-nav prev" data-v-prev aria-label="이전">‹</button>
  <button type="button" class="hb-viewer-nav next" data-v-next aria-label="다음">›</button>
</dialog>
<?php hub_foot($canUp ? ['files.js', 'upload.js'] : ['files.js']);

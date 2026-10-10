<?php
// 내 공간 홈 — 배경화면 위의 '데스크톱'. 기능(모듈)은 아이콘으로 열고(창 또는 새 탭), 업무용 위젯을 늘 띄워 둡니다.
// 위젯 데이터는 사용자별로 private/hub.sqlite(desk_data)에 저장 → 어느 기기에서 열어도 같습니다. 기능 추가 방법은 hub/modules/README.md
require_once __DIR__ . '/_desk.php';
$u = hub_require_login();
$mods = hub_modules_for($u);
$data = desk_load($u);
$hour = (int)date('G');
$hello = $hour < 6 ? '늦은 밤이에요' : ($hour < 12 ? '좋은 아침이에요' : ($hour < 18 ? '좋은 오후예요' : '좋은 저녁이에요'));

/* 위젯 목록(기본 순서·크기). 크기: 1 = 한 칸, 2 = 두 칸 너비 */
$WIDGETS = [
    'todo'    => ['title' => '오늘 할 일',   'size' => 1],
    'calendar'=> ['title' => '캘린더',       'size' => 1],
    'notes'   => ['title' => '메모',         'size' => 2],
    'pomo'    => ['title' => '집중 타이머',  'size' => 1],
    'weather' => ['title' => '날씨',         'size' => 1],
    'dday'    => ['title' => 'D-day',        'size' => 1],
    'links'   => ['title' => '바로가기',     'size' => 1],
    'calc'    => ['title' => '계산기 · 단위 변환', 'size' => 1],
];
// 저장된 배치 적용: [{id, size, hidden}]
$layout = [];
foreach ((array)($data['layout'] ?? []) as $it) {
    if (is_array($it) && isset($it['id'], $WIDGETS[$it['id']])) $layout[$it['id']] = ['size' => (int)($it['size'] ?? 1) === 2 ? 2 : 1, 'hidden' => !empty($it['hidden'])];
}
foreach ($WIDGETS as $id => $w) if (!isset($layout[$id])) $layout[$id] = ['size' => $w['size'], 'hidden' => false];

// 배경화면
$wall = is_array($data['wall'] ?? null) ? $data['wall'] : [];
$wallFile = desk_wall_file($u);
$wallImg = $wallFile ? 'desk-wall.php?v=' . filemtime($wallFile) : '';

$apps = [];
foreach ($mods as $id => $m) $apps[] = ['id' => $id, 'title' => $m['title'], 'href' => hub_module_href($m), 'icon' => $m['icon']];

$ico = fn(string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
$I = [
    'wall'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m21 16-5-5-9 9"/>',
    'edit'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/>',
    'full'  => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
    'grip'  => '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
    'wide'  => '<path d="M3 12h18M7 8l-4 4 4 4M17 8l4 4-4 4"/>',
    'hide'  => '<path d="M6 6l12 12M18 6 6 18"/>',
    'ext'   => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    'min'   => '<path d="M5 18h14"/>',
    'max'   => '<rect x="4" y="4" width="16" height="16" rx="2"/>',
    'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
    'gear'  => '<path d="M12 5v14M5 12h14"/>',
];

hub_head('홈', $u, 'home', ['css' => ['desk.css'], 'body' => 'dk-body']);
?>
<div class="dk-wall" id="dk-wall" aria-hidden="true"></div>

<section class="dk-hero">
  <div class="dk-clock">
    <p class="dk-time" id="dk-time"><?=e(date('H:i'))?></p>
    <p class="dk-date"><span id="dk-date"></span> <span class="dk-holi" id="dk-holi"></span></p>
    <p class="dk-hello"><?=e($hello)?>, <?=e($u['display_name'])?>님</p>
  </div>
  <div class="dk-tools">
    <button type="button" class="dk-tool" data-act="wall"><?=$ico($I['wall'])?><span>배경화면</span></button>
    <button type="button" class="dk-tool" data-act="edit" aria-pressed="false"><?=$ico($I['edit'])?><span>위젯 편집</span></button>
    <button type="button" class="dk-tool" data-act="full"><?=$ico($I['full'])?><span>전체 화면</span></button>
  </div>
</section>

<nav class="dk-apps" aria-label="기능">
  <?php foreach ($apps as $a): ?>
  <a class="dk-app" href="<?=e($a['href'])?>" data-app="<?=e($a['id'])?>" title="<?=e($a['title'])?> — 클릭: 창으로 열기 · Ctrl/⌘+클릭: 새 탭">
    <span class="dk-app-ico"><?=$ico($a['icon'])?></span><b><?=e($a['title'])?></b>
  </a>
  <?php endforeach; ?>
  <?php if (hub_is_owner($u)): ?>
  <a class="dk-app dk-app-add" href="../admin/hub-modules.php" target="_blank" title="기능 관리(관리자 화면)">
    <span class="dk-app-ico"><?=$ico($I['gear'])?></span><b>기능 관리</b>
  </a>
  <?php endif; ?>
  <div class="dk-tasks" id="dk-tasks" aria-label="최소화한 창"></div>
</nav>
<?php if (!$mods): ?><p class="hb-empty">아직 사용할 수 있는 기능이 없어요. 관리자에게 폴더 공유를 요청하세요.</p><?php endif; ?>

<div class="dk-edit-bar" id="dk-edit-bar" hidden>
  <span>위젯을 끌어서 순서를 바꾸고, 넓히거나 숨길 수 있어요.</span>
  <span class="dk-hidden-list" id="dk-hidden-list"></span>
  <button type="button" class="hb-btn hb-btn-primary" data-act="edit-done">완료</button>
</div>

<div class="dk-grid" id="dk-grid">
  <?php foreach ($layout as $id => $l): $w = $WIDGETS[$id]; ?>
  <section class="dk-w<?=$l['size'] === 2 ? ' is-wide' : ''?>" data-w="<?=e($id)?>" <?=$l['hidden'] ? 'hidden' : ''?> aria-label="<?=e($w['title'])?>">
    <header class="dk-w-head">
      <span class="dk-grip" title="끌어서 옮기기"><?=$ico($I['grip'])?></span>
      <h2><?=e($w['title'])?></h2>
      <span class="dk-w-extra"></span>
      <span class="dk-w-edit">
        <button type="button" data-act="wide" title="넓게 / 좁게"><?=$ico($I['wide'])?></button>
        <button type="button" data-act="hide" title="숨기기"><?=$ico($I['hide'])?></button>
      </span>
    </header>
    <div class="dk-w-body"></div>
  </section>
  <?php endforeach; ?>
</div>

<!-- 기능 창 (여행 기록 · 파일 등) — 최소화해도 그대로 남아 있어 위젯과 함께 쓸 수 있습니다 -->
<div class="dk-win" id="dk-win" hidden role="dialog" aria-modal="false" aria-labelledby="dk-win-title">
  <div class="dk-win-bar">
    <span class="dk-win-ico" id="dk-win-ico"></span>
    <b id="dk-win-title"></b>
    <a class="dk-win-btn" id="dk-win-ext" href="#" target="_blank" title="새 탭으로 열기"><?=$ico($I['ext'])?></a>
    <button type="button" class="dk-win-btn" data-act="win-min" title="최소화 (Esc)"><?=$ico($I['min'])?></button>
    <button type="button" class="dk-win-btn" data-act="win-max" title="크게 / 작게"><?=$ico($I['max'])?></button>
    <button type="button" class="dk-win-btn" data-act="win-close" title="닫기"><?=$ico($I['close'])?></button>
  </div>
  <div class="dk-win-stage" id="dk-win-stage"></div>
</div>

<!-- 배경화면 설정 -->
<dialog class="hb-dialog dk-wall-dlg" id="dk-wall-dlg">
  <form method="dialog" class="hb-form">
    <b class="hb-dialog-title">배경화면</b>
    <div class="dk-presets" id="dk-presets"></div>
    <div class="dk-wall-row">
      <label class="hb-btn dk-upload"><input type="file" accept="image/jpeg,image/png,image/webp" id="dk-wall-file" hidden>내 사진 올리기</label>
      <label class="dk-color" title="단색"><input type="color" id="dk-wall-color" value="#3f4a43"> 단색</label>
      <button type="button" class="hb-btn" data-act="wall-none">배경 없음</button>
    </div>
    <label>어둡게 <input type="range" id="dk-wall-dim" min="0" max="70" step="5"></label>
    <label>흐리게 <input type="range" id="dk-wall-blur" min="0" max="20" step="2"></label>
    <p class="hb-err" id="dk-wall-err" hidden></p>
    <div class="hb-dialog-acts"><button class="hb-btn hb-btn-primary" value="ok">닫기</button></div>
  </form>
</dialog>

<script type="application/json" id="dk-boot"><?=json_encode([
    'csrf'    => hub_csrf(),
    'data'    => (object)$data,
    'wallImg' => $wallImg,
    'apps'    => $apps,
    'owner'   => hub_is_owner($u),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?></script>
<?php hub_foot(['desk.js']);

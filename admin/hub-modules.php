<?php
// 내 공간 · 기능 — hub/modules 폴더에 있는 기능(모듈)을 켜고 끕니다. 새 기능 추가 방법 안내.
require_once __DIR__ . '/auth.php'; require_admin();
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/hub.php';
require_once __DIR__ . '/style.php';

if (!hub_available()) { admin_head('기능'); echo '<p>PHP SQLite(pdo_sqlite) 확장이 필요합니다.</p>'; admin_foot(); exit; }
$msg = '';
$mods = hub_modules_all();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청입니다.'); }
    $on = array_map('strval', (array)($_POST['on'] ?? []));
    $off = array_values(array_diff(array_keys($mods), $on));
    hub_set_setting('modules_off', json_encode($off));
    $msg = '저장했습니다.';
}
$off = hub_module_disabled_ids();
admin_head('기능'); ?>
<style>
.hub-mod{display:flex;align-items:center;gap:14px;padding:14px 16px;border-bottom:1px solid #f0efed}
.hub-mod:last-child{border-bottom:0}
.hub-mod svg{flex:none;width:36px;height:36px;padding:7px;border-radius:10px;background:#f0efed;color:#1c1917}
.hub-mod div{flex:1;min-width:0}.hub-mod b{display:block;font-size:14px;color:#1c1917}.hub-mod small{font-size:12px;color:#78716c}
.hub-code{margin-top:12px;padding:16px;border-radius:12px;background:#1c1917;color:#e7e5e4;font:12px/1.7 ui-monospace,Menlo,Consolas,monospace;white-space:pre;overflow:auto}
</style>
<div class="adm-wrap">
  <?php admin_nav('hub-modules'); ?>
  <section>
    <p class="text-xs tracking-[.18em] text-stone-400">MY SPACE</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">기능</h1>
    <p class="mt-2 text-sm text-stone-500">내 공간 홈과 상단 메뉴에 보이는 기능입니다. 끄면 모든 사용자에게서 숨겨집니다. <a class="underline" href="../hub/" target="_blank">내 공간 열기 ↗</a></p>
    <?php if ($msg): ?><div class="adm-note" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?=e($msg)?></div><?php endif; ?>
    <form method="post" style="margin-top:16px;border:1px solid #e7e5e4;border-radius:14px;background:#fff;overflow:hidden">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <?php foreach ($mods as $id => $m): ?>
      <div class="hub-mod">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?=$m['icon']?></svg>
        <div><b><?=e($m['title'])?></b><small><?=e($m['desc'])?> · <code>hub/modules/<?=e($id)?>/</code></small></div>
        <label class="hide-toggle"><input type="checkbox" name="on[]" value="<?=e($id)?>" <?=in_array($id, $off, true) ? '' : 'checked'?>> 사용</label>
      </div>
      <?php endforeach; ?>
      <div style="padding:12px 16px;background:#faf9f6;border-top:1px solid #f0efed"><button class="adm-primary" style="height:40px">저장</button></div>
    </form>

    <h2 class="adm-h2">새 기능 추가하기</h2>
    <p class="mt-2 text-sm text-stone-500" style="line-height:1.8">
      <code>hub/modules/</code> 안에 영문 소문자 이름의 폴더를 만들고 <code>module.php</code> 와 <code>index.php</code> 를 넣으면 내 공간에 카드와 메뉴가 자동으로 생깁니다.
      자세한 설명과 예시는 <code>hub/modules/README.md</code> 에 있습니다.</p>
<div class="hub-code">// hub/modules/memo/module.php
&lt;?php
return [
    'title' =&gt; '메모',
    'desc'  =&gt; '간단한 메모장',
    'order' =&gt; 30,
    'icon'  =&gt; '&lt;path d="M5 4h14v16H5z"/&gt;&lt;path d="M8 9h8M8 13h8M8 17h5"/&gt;',
    // 'visible' =&gt; function (array $u) { return hub_is_owner($u); },  // 주인에게만 보이기
];

// hub/modules/memo/index.php
&lt;?php
require_once dirname(__DIR__, 2) . '/_boot.php';
$u = hub_require_login();            // 로그인 확인 (회원/주인)
hub_head('메모', $u, 'memo');         // 상단 바 + 메뉴
echo '&lt;h1 class="hb-h1"&gt;메모&lt;/h1&gt;';
hub_foot();</div>
  </section>
</div>
<?php admin_foot();

<?php
// 사진 관리: 용량 · 폴더별 현황 · 어느 여행에서도 쓰지 않는 사진(고아) 정리. 지우지 않고 휴지통(travel-photos/_trash)으로 옮기므로 되돌릴 수 있습니다.
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/data.php';
require_once __DIR__.'/../includes/images.php';
require_once __DIR__.'/../site.php';
require_once __DIR__.'/style.php';

const PH_SKIP = ['_site', '_trash'];
const PH_EXT = '/\.(jpe?g|png|webp|gif|avif|heic)$/i';
function ph_fmt(int $b): string { return $b >= 1073741824 ? round($b / 1073741824, 2) . ' GB' : ($b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB'); }

/** [rel => [size, mtime]] — travel-photos 의 모든 사진(_site·_trash 제외) */
function ph_scan(): array {
    $root = photo_root(); $out = [];
    if (!is_dir($root)) return $out;
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($f) use ($root) {
            if (!$f->isDir()) return true;
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            return !in_array(explode('/', $rel)[0], PH_SKIP, true);
        }));
    foreach ($it as $f) {
        if (!$f->isFile() || !preg_match(PH_EXT, $f->getFilename())) continue;
        $out[str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1))] = [(int)$f->getSize(), (int)$f->getMTime()];
    }
    return $out;
}
function ph_used(): array {
    global $TRAVEL_SITE; $used = [];
    foreach (store()->all() as $t) foreach (trip_photo_paths($t) as $p) $used[ltrim($p, '/')] = true;
    foreach (['hero_image', 'profile_photo'] as $k) { $h = (string)($TRAVEL_SITE[$k] ?? ''); if (str_starts_with($h, 'travel-photos/')) $used[substr($h, 14)] = true; }
    return $used;
}
function ph_clean_dirs(string $abs): void {
    $root = realpath(photo_root()); $d = dirname($abs);
    while ($root && ($rd = realpath($d)) && $rd !== $root && str_starts_with($rd, $root . DIRECTORY_SEPARATOR) && !glob($rd . '/*') && !glob($rd . '/.[!.]*')) { @rmdir($rd); $d = dirname($d); }
}
function ph_move(string $from, string $to): bool {
    $root = realpath(photo_root()); if (!$root) return false;
    foreach ([$from, $to] as $r) if ($r === '' || str_contains($r, '..') || preg_match('/[\x00-\x1F]/', $r)) return false;
    $src = realpath($root . '/' . $from); if (!$src || !is_file($src) || !str_starts_with($src, $root . DIRECTORY_SEPARATOR)) return false;
    $dst = $root . '/' . $to; if (!is_dir(dirname($dst)) && !@mkdir(dirname($dst), 0755, true)) return false;
    if (file_exists($dst)) $dst = preg_replace('/(\.[^.\/]+)$/', '-' . substr(md5((string)microtime(true)), 0, 5) . '$1', $dst);
    $ok = @rename($src, $dst); if ($ok) ph_clean_dirs($src); return $ok;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청입니다.'); }
    @set_time_limit(120);
    $act = (string)($_POST['act'] ?? ''); $sel = array_map('strval', (array)($_POST['sel'] ?? [])); $n = 0;
    if ($act === 'trash' || $act === 'trash_all') {
        $used = ph_used(); $scan = ph_scan();
        $list = $act === 'trash_all' ? array_keys(array_diff_key($scan, $used)) : array_values(array_filter($sel, fn($r) => isset($scan[$r]) && !isset($used[$r])));   // 사용 중인 사진은 절대 옮기지 않음
        foreach ($list as $r) if (ph_move($r, '_trash/' . $r)) $n++;
        $msg = "고아 사진 {$n}장을 휴지통으로 옮겼습니다.";
    } elseif ($act === 'restore') {
        foreach ($sel as $r) if (str_starts_with($r, '_trash/') && ph_move($r, substr($r, 7))) $n++;
        $msg = "{$n}장을 원래 위치로 복원했습니다.";
    } elseif ($act === 'empty') {
        $root = realpath(photo_root()); $tr = $root ? realpath($root . '/_trash') : false;
        if ($tr) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tr, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { if ($f->isDir()) @rmdir($f->getPathname()); elseif (@unlink($f->getPathname())) $n++; } }
        $msg = "휴지통을 비웠습니다({$n}장 영구 삭제).";
    }
    header('Location: photos.php?m=' . rawurlencode($msg)); exit;
}
if (isset($_GET['m'])) $msg = mb_substr((string)$_GET['m'], 0, 120);

$scan = ph_scan(); $used = ph_used();
$orph = array_diff_key($scan, $used);
$missing = array_diff_key($used, $scan);                 // 여행이 가리키지만 파일이 없는 사진
$folders = [];
foreach ($scan as $rel => [$sz]) { $d = dirname($rel); $d = $d === '.' ? '(루트)' : $d; $f = &$folders[$d]; $f = $f ?? ['n' => 0, 'b' => 0, 'o' => 0]; $f['n']++; $f['b'] += $sz; if (isset($orph[$rel])) $f['o']++; unset($f); }
ksort($folders);
$totalB = array_sum(array_column($scan, 0)); $orphB = 0; foreach ($orph as [$sz]) $orphB += $sz;
$tr = []; { $root = photo_root() . '/_trash'; if (is_dir($root)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) if ($f->isFile()) $tr['_trash/' . str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1))] = (int)$f->getSize(); } }
$free = @disk_free_space(photo_root()); $dtot = @disk_total_space(photo_root());
$show = array_slice($orph, 0, 240, true);

admin_head('사진 관리'); ?>
<div class="adm-wrap">
  <?php admin_nav('photos'); ?>
  <section>
    <p class="text-xs tracking-[.18em] text-stone-400">PHOTO MANAGER</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">사진 관리</h1>
    <p class="mt-2 text-sm text-stone-500">어느 여행에서도 쓰지 않는 사진을 찾아 휴지통으로 옮길 수 있습니다. 휴지통에 둔 사진은 언제든 복원됩니다.</p>
    <?php if ($msg): ?><div class="adm-note"><?=e($msg)?></div><?php endif; ?>

    <div class="adm-kpis">
      <div><small>전체 사진</small><b><?=number_format(count($scan))?>장</b><span><?=ph_fmt($totalB)?></span></div>
      <div><small>쓰이지 않는 사진</small><b><?=number_format(count($orph))?>장</b><span><?=ph_fmt($orphB)?> 절약 가능</span></div>
      <div><small>휴지통</small><b><?=number_format(count($tr))?>장</b><span><?=ph_fmt((int)array_sum($tr))?></span></div>
      <div><small>NAS 여유 공간</small><b><?=$free !== false ? ph_fmt((int)$free) : '—'?></b><span><?=$dtot ? '전체 ' . ph_fmt((int)$dtot) : ''?></span></div>
    </div>
    <?php if ($missing): ?><div class="adm-note" style="border-color:#fecaca;background:#fef2f2;color:#991b1b">⚠ 여행 글에서 가리키지만 파일이 없는 사진이 <b><?=count($missing)?>장</b> 있습니다(예: <code><?=e(array_key_first($missing))?></code>). 사진 폴더를 옮겼거나 일부 업로드가 빠졌을 수 있습니다.</div><?php endif; ?>

    <h2 class="adm-h2">쓰이지 않는 사진 <small><?=count($orph)?>장<?=count($orph) > count($show) ? ' · 앞 ' . count($show) . '장만 표시' : ''?></small></h2>
    <?php if (!$orph): ?><div class="adm-note">정리할 사진이 없습니다. 모든 사진이 여행에서 쓰이고 있어요 👍</div>
    <?php else: ?>
    <form method="post" id="orphForm">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="trash">
      <div class="adm-bulk" style="display:flex;margin-top:12px">
        <label style="display:inline-flex;gap:6px;align-items:center;font-size:12px"><input type="checkbox" id="orphAll"> 표시된 사진 전체 선택</label>
        <button class="adm-btn" type="submit">선택 항목 휴지통으로</button>
        <button class="adm-btn danger" type="button" id="orphEvery">고아 사진 <?=count($orph)?>장 모두 휴지통으로</button>
      </div>
      <div class="adm-photos">
        <?php foreach ($show as $rel => [$sz, $mt]): ?>
        <label class="adm-ph"><input type="checkbox" name="sel[]" value="<?=e($rel)?>"><img src="<?=e('../' . photo_thumb_url($rel, 240))?>" alt="" loading="lazy" width="120" height="120"><span title="<?=e($rel)?>"><?=e(basename($rel))?><br><small><?=ph_fmt($sz)?> · <?=e(dirname($rel))?></small></span></label>
        <?php endforeach; ?>
      </div>
    </form>
    <form method="post" id="orphEveryForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="trash_all"></form>
    <?php endif; ?>

    <?php if ($tr): ?>
    <h2 class="adm-h2">휴지통 <small><?=count($tr)?>장</small></h2>
    <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <div class="adm-bulk" style="display:flex;margin-top:12px">
        <label style="display:inline-flex;gap:6px;align-items:center;font-size:12px"><input type="checkbox" id="trAll"> 전체 선택</label>
        <button class="adm-btn" name="act" value="restore" type="submit">선택 항목 복원</button>
        <button class="adm-btn danger" name="act" value="empty" type="submit" onclick="return confirm('휴지통의 사진 <?=count($tr)?>장을 영구 삭제합니다. 되돌릴 수 없습니다. 계속할까요?')">휴지통 비우기</button>
      </div>
      <div class="adm-photos">
        <?php foreach (array_slice($tr, 0, 120, true) as $rel => $sz): ?>
        <label class="adm-ph"><input type="checkbox" name="sel[]" value="<?=e($rel)?>"><img src="<?=e('../' . photo_thumb_url($rel, 240))?>" alt="" loading="lazy" width="120" height="120"><span title="<?=e($rel)?>"><?=e(basename($rel))?><br><small><?=ph_fmt($sz)?> · <?=e(dirname(substr($rel, 7)))?></small></span></label>
        <?php endforeach; ?>
      </div>
    </form>
    <?php endif; ?>

    <h2 class="adm-h2">폴더별 현황</h2>
    <table class="adm-table"><thead><tr><th>폴더</th><th style="text-align:right">사진</th><th style="text-align:right">용량</th><th style="text-align:right">미사용</th></tr></thead><tbody>
      <?php foreach ($folders as $d => $f): ?><tr><td><?=e($d)?></td><td style="text-align:right"><?=$f['n']?></td><td style="text-align:right"><?=ph_fmt($f['b'])?></td><td style="text-align:right;<?=$f['o'] ? 'color:#b45309;font-weight:600' : 'color:#a8a29e'?>"><?=$f['o'] ?: '—'?></td></tr><?php endforeach; ?>
      <?php if (!$folders): ?><tr><td colspan="4" style="padding:30px;text-align:center;color:#a8a29e">사진이 없습니다.</td></tr><?php endif; ?>
    </tbody></table>
  </section>
</div>
<script>
(function(){
  function all(btn, form){ var b=document.getElementById(btn); if(!b) return; b.addEventListener('change',function(){ form.querySelectorAll('input[name="sel[]"]').forEach(function(x){x.checked=b.checked}); }); }
  var of=document.getElementById('orphForm'); if(of) all('orphAll', of);
  var tf=document.getElementById('trAll'); if(tf) all('trAll', tf.closest('form'));
  var ev=document.getElementById('orphEvery'); if(ev) ev.addEventListener('click',function(){ if(confirm('쓰이지 않는 사진 <?=count($orph)?>장을 모두 휴지통으로 옮길까요?\n(휴지통에서 복원할 수 있습니다)')) document.getElementById('orphEveryForm').submit(); });
})();
</script>
<?php admin_foot();

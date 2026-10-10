<?php
require_once __DIR__.'/auth.php';
require_admin();
require_once __DIR__.'/../includes/data.php';
require_once __DIR__.'/style.php';
require_once __DIR__.'/../includes/backup.php';
try { travel_migrate_legacy(); } catch (Throwable $e) {}
try { backup_snapshot_if_due(); } catch (Throwable $e) { error_log('snapshot: ' . $e->getMessage()); }
$__bd = backup_days_since_download();

const PER_PAGE = 10;
$all = load_trips_admin();
$cnt = ['public' => 0, 'scheduled' => 0, 'draft' => 0, 'hidden' => 0];
foreach ($all as $__t) $cnt[trip_state($__t)]++;
$nHidden = count($all) - $cnt['public'];

// ── 필터 · 검색 · 정렬 · 페이지 ─────────────────────────
$q      = trim((string)($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['public', 'scheduled', 'draft', 'hidden'], true) ? $_GET['status'] : 'all';
$sort   = in_array($_GET['sort'] ?? '', ['country', 'location', 'date', 'title'], true) ? $_GET['sort'] : 'date';
$dir    = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
if (!isset($_GET['sort'])) $dir = 'desc';

$rows = $all;
if ($status !== 'all') $rows = array_filter($rows, fn($t) => trip_state($t) === $status);
if ($q !== '') {
  $ids = array_flip(trip_search_ids($q));            // 본문까지 포함한 검색
  $rows = array_filter($rows, fn($t) => isset($ids[$t['id']]));
}
$rows = array_values($rows);
usort($rows, function($a, $b) use ($sort, $dir) {
  $c = strnatcasecmp((string)($a[$sort] ?? ''), (string)($b[$sort] ?? ''));
  if ($c === 0) $c = strcmp((string)$a['date'], (string)$b['date']);
  return $dir === 'asc' ? $c : -$c;
});
$total = count($rows);
$pages = max(1, (int)ceil($total / PER_PAGE));
$page  = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$slice = array_slice($rows, ($page - 1) * PER_PAGE, PER_PAGE);

function adm_url(array $over = []): string {
  global $q, $status, $sort, $dir, $page;
  $p = array_merge(['q' => $q, 'status' => $status === 'all' ? '' : $status, 'sort' => $_GET['sort'] ?? '', 'dir' => $_GET['dir'] ?? '', 'page' => $page > 1 ? $page : ''], $over);
  $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
  return 'index.php' . ($p ? '?' . http_build_query($p) : '') . '#list';
}
function adm_sort_link(string $key, string $label): string {
  global $sort, $dir;
  $next = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
  $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
  return '<a href="' . e(adm_url(['sort' => $key, 'dir' => $next, 'page' => ''])) . '">' . e($label) . $arrow . '</a>';
}

function web_path_of($abs){
  $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? ''); $real = realpath($abs);
  if (!$doc || !$real || strpos($real, $doc . DIRECTORY_SEPARATOR) !== 0) return null;
  return str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($doc)));
}
$probe = web_path_of(admin_file());
admin_head('여행 관리');
?>
<div id="exposeWarn" hidden class="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-xs leading-6 text-red-800">
  <b>보안 경고</b> — 관리자 계정 파일(<code><?=e($probe ?? '')?></code>)이 인터넷에서 직접 열립니다. 비밀번호 해시가 외부에 노출된 상태이니
  README의 <b>"2. 데이터 폴더 보호"</b>를 바로 적용하고 비밀번호를 다시 변경하세요.
</div>

<div class="adm-wrap">
  <?php admin_nav('list'); ?>

  <section id="list" style="scroll-margin-top:90px">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs tracking-[.18em] text-stone-400">TRAVEL MANAGER</p>
        <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">여행 관리</h1>
        <p class="mt-2 text-sm text-stone-500">등록된 여행 <?=count($all)?>개<?php if ($nHidden): ?> · 비공개 <?=$nHidden?>개<?php endif; ?></p>
      </div>
      <a href="trip-new.php" class="adm-primary"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>새 여행 작성</a>
    </div>

    <?php if (!empty($_SESSION['login_notice'])): $__ln = $_SESSION['login_notice']; unset($_SESSION['login_notice']); if (!empty($__ln['prev']) || !empty($__ln['fails'])): ?>
    <div class="adm-note" style="<?=!empty($__ln['fails']) ? 'border-color:#fde68a;background:#fffbeb;color:#92400e' : ''?>">🔐 <?php if (!empty($__ln['prev'])): ?>마지막 로그인 <?=e(date('Y-m-d H:i', (int)$__ln['prev']['t']))?> · <?=e(login_ua_label((string)$__ln['prev']['ua']))?> (<?=e($__ln['prev']['ip'])?>)<?php endif; ?><?php if (!empty($__ln['fails'])): ?><?=!empty($__ln['prev']) ? ' — ' : ''?>그 이후 <b>로그인 실패 <?=intval($__ln['fails'])?>회</b>가 있었습니다. 본인이 아니라면 <a class="underline" href="security.php">보안 메뉴</a>에서 기록을 확인하고 비밀번호를 바꾸세요.<?php endif; ?></div>
    <?php endif; endif; ?>
    <?php if ($all && ($__bd === null || $__bd >= 30)): ?><div class="adm-note" style="border-color:#fde68a;background:#fffbeb;color:#92400e">💾 <?=$__bd === null ? '아직 백업을 내려받은 적이 없습니다.' : "마지막 백업 내려받기가 {$__bd}일 전입니다."?> 서버 문제에 대비해 <a class="underline font-semibold" href="backup.php">지금 백업</a>해 두세요.</div><?php endif; ?>
    <?php if (isset($_GET['cache'])): ?><div class="adm-note">캐시 파일 <?=intval($_GET['cache'])?>개를 정리했습니다. 이미지 최적화 메뉴에서 미리 다시 만들어 둘 수 있습니다.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="adm-note">여행 기록을 저장했습니다.</div><?php endif; ?>
    <?php if (isset($_GET['welcome'])): ?><div class="adm-note">설치가 끝났습니다. <b>새 여행 기록</b>으로 첫 글을 써 보고, <a class="underline" href="site-settings.php">홈페이지 설정</a>에서 대표 사진·프로필을, <a class="underline" href="health.php">사이트 점검</a>에서 서버 설정을 확인하세요.</div><?php endif; ?>
    <?php if (isset($_GET['hid'])): ?><div class="adm-note"><?=$_GET['hid']==='1' ? '🙈 여행을 숨겼습니다. 방문자에게는 보이지 않습니다.' : '여행을 공개했습니다.'?></div><?php endif; ?>
    <?php if (isset($_GET['bulk'])): $__ba = ['publish' => '공개로 변경', 'hide' => '숨김 처리', 'draft' => '초안으로 변경', 'pin' => '홈 상단에 고정', 'unpin' => '고정 해제', 'delete' => '삭제(사진 파일은 보존)']; ?><div class="adm-note"><?=intval($_GET['bulk'])?>개 여행을 <?=$__ba[$_GET['act'] ?? ''] ?? '처리'?>했습니다.</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="adm-note">여행 페이지가 삭제되었습니다.<?=($_GET['photos'] ?? '') === '1' ? ' 사진 파일도 함께 삭제했습니다.' : ' 사진 파일은 보존했습니다.'?></div><?php endif; ?>

    <form class="adm-toolbar" method="get" action="index.php#list" role="search">
      <div class="adm-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" name="q" value="<?=e($q)?>" placeholder="제목·국가·장소·본문 검색" aria-label="여행 검색">
      </div>
      <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?=e($status)?>"><?php endif; ?>
      <?php if (isset($_GET['sort'])): ?><input type="hidden" name="sort" value="<?=e($sort)?>"><input type="hidden" name="dir" value="<?=e($dir)?>"><?php endif; ?>
      <button class="adm-btn" style="height:42px;padding:0 16px" type="submit">검색</button>
      <?php if ($q !== ''): ?><a class="adm-btn" style="height:42px" href="<?=e(adm_url(['q' => '', 'page' => '']))?>">초기화</a><?php endif; ?>
      <div class="adm-tabs ml-auto" role="tablist">
        <a href="<?=e(adm_url(['status' => '', 'page' => '']))?>" aria-current="<?=$status==='all'?'true':'false'?>">전체<small><?=count($all)?></small></a>
        <?php foreach (['public' => '공개', 'scheduled' => '예약', 'draft' => '초안', 'hidden' => '숨김'] as $__k => $__l): if ($__k !== 'public' && !$cnt[$__k] && $status !== $__k) continue; ?>
        <a href="<?=e(adm_url(['status' => $__k, 'page' => '']))?>" aria-current="<?=$status===$__k?'true':'false'?>"><?=$__l?><small><?=$cnt[$__k]?></small></a>
        <?php endforeach; ?>
      </div>
    </form>

    <form id="bulkForm" method="post" action="trip-bulk.php" class="adm-bulk" hidden>
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="">
      <?php foreach (['q'=>$q,'status'=>$status==='all'?'':$status,'sort'=>$_GET['sort']??'','dir'=>$_GET['dir']??'','page'=>$page>1?$page:''] as $k=>$v) if ($v!=='') echo '<input type="hidden" name="'.$k.'" value="'.e($v).'">'; ?>
      <b><span id="bulkN">0</span>개 선택</b>
      <button type="button" class="adm-btn" data-act="publish">공개</button>
      <button type="button" class="adm-btn" data-act="hide">숨김</button>
      <button type="button" class="adm-btn" data-act="draft">초안</button>
      <button type="button" class="adm-btn" data-act="pin">📌 고정</button>
      <button type="button" class="adm-btn" data-act="unpin">고정 해제</button>
      <button type="button" class="adm-btn danger" data-act="delete">삭제</button>
      <span class="adm-bulk-hint">삭제해도 사진 파일은 남습니다 · 사진 관리에서 정리</span>
    </form>

    <table class="adm-table">
      <thead><tr>
        <th style="width:34px;padding-right:0"><input type="checkbox" id="bulkAll" aria-label="이 페이지 전체 선택"></th>
        <th><?=adm_sort_link('country', '국가')?></th>
        <th><?=adm_sort_link('location', '장소')?></th>
        <th><?=adm_sort_link('date', '날짜')?></th>
        <th><?=adm_sort_link('title', '제목')?></th>
        <th style="text-align:right">관리</th>
      </tr></thead>
      <tbody>
      <?php foreach ($slice as $t): $ts = trip_state($t); $h = $ts !== 'public'; ?>
        <tr class="<?=$h?'is-hidden':''?>">
          <td style="padding-right:0"><input type="checkbox" class="bulk-cb" name="ids[]" value="<?=e($t['id'])?>" form="bulkForm" aria-label="<?=e($t['title'])?> 선택"></td>
          <td style="white-space:nowrap"><?=e($t['country'] ?: '—')?></td>
          <td style="max-width:150px"><span class="adm-title" style="max-width:150px;font-weight:400;color:#44403c"><?=e($t['location'] ?: '—')?></span></td>
          <td style="white-space:nowrap;color:#78716c"><?=e($t['date'])?></td>
          <td><span class="adm-title"><?=e($t['title'])?><?php if ($h): ?><span class="adm-badge <?=$ts?>"><?=trip_state_label($ts)?><?=$ts==='scheduled'?' '.e(substr($t['publish_at'],5,11)):''?></span><?php endif; ?><?php if (!empty($t['pinned'])): ?><span class="adm-pin" title="홈 상단 고정">📌</span><?php endif; ?></span></td>
          <td>
            <div class="adm-actions">
              <a class="adm-btn" href="../trip.php?id=<?=rawurlencode($t['id'])?>" target="_blank" rel="noopener">보기</a>
              <a class="adm-btn" href="trip-edit.php?id=<?=rawurlencode($t['id'])?>">수정</a>
              <form method="post" action="trip-hide.php">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e($t['id'])?>"><input type="hidden" name="hide" value="<?=$h?0:1?>">
                <?php foreach (['q'=>$q,'status'=>$status==='all'?'':$status,'sort'=>$_GET['sort']??'','dir'=>$_GET['dir']??'','page'=>$page>1?$page:''] as $k=>$v) if ($v!=='') echo '<input type="hidden" name="'.$k.'" value="'.e($v).'">'; ?>
                <button class="adm-btn" type="submit"><?=$h?'공개하기':'숨기기'?></button>
              </form>
              <form method="post" action="delete.php" onsubmit="return confirmDelete(this);">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e($t['id'])?>"><input type="hidden" name="delete_photos" value="1">
                <button type="submit" class="adm-btn danger" data-title="<?=e($t['title'])?>">삭제</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$slice): ?>
        <tr><td colspan="6" style="padding:44px;text-align:center;color:#a8a29e"><?=$q!==''||$status!=='all' ? '조건에 맞는 여행이 없습니다.' : '등록된 여행이 없습니다.'?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="adm-pager">
      <span><?=$total ? (($page-1)*PER_PAGE+1).'–'.min($total, $page*PER_PAGE) : 0?> / 총 <?=$total?>개</span>
      <?php if ($pages > 1): ?>
      <nav aria-label="페이지">
        <?php if ($page > 1): ?><a href="<?=e(adm_url(['page' => $page-1 > 1 ? $page-1 : '']))?>" aria-label="이전">‹</a><?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++):
          if ($pages > 7 && $i !== 1 && $i !== $pages && abs($i - $page) > 1) { if (abs($i - $page) === 2) echo '<span style="padding:0 4px;align-self:center">…</span>'; continue; }
          echo $i === $page ? '<span class="cur" aria-current="page">'.$i.'</span>' : '<a href="'.e(adm_url(['page' => $i > 1 ? $i : ''])).'">'.$i.'</a>';
        endfor; ?>
        <?php if ($page < $pages): ?><a href="<?=e(adm_url(['page' => $page+1]))?>" aria-label="다음">›</a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
(function(){
  var f=document.getElementById('bulkForm'), all=document.getElementById('bulkAll'), cbs=[].slice.call(document.querySelectorAll('.bulk-cb')), n=document.getElementById('bulkN');
  if(!f) return;
  function sync(){ var c=cbs.filter(function(x){return x.checked}).length; n.textContent=c; f.hidden=!c; if(all){ all.checked=c>0&&c===cbs.length; all.indeterminate=c>0&&c<cbs.length; } }
  cbs.forEach(function(x){ x.addEventListener('change',sync); });
  if(all) all.addEventListener('change',function(){ cbs.forEach(function(x){x.checked=all.checked}); sync(); });
  f.addEventListener('click',function(e){
    var b=e.target.closest('[data-act]'); if(!b) return;
    var act=b.dataset.act, c=cbs.filter(function(x){return x.checked}).length;
    if(act==='delete' && !confirm(c+'개 여행을 삭제할까요?\n\n여행 글은 복구할 수 없습니다(백업이 있다면 복원 가능). 사진 파일은 지워지지 않습니다.')) return;
    f.elements.act.value=act; f.submit();
  });
})();
</script>
<script>
function confirmDelete(form){
  const t = form.querySelector('[data-title]')?.dataset.title || '이 여행';
  return window.confirm('정말 삭제하시겠습니까?\n\n' + t + '\n\n여행 데이터와 연결된 사진도 함께 삭제되며 복구할 수 없습니다.\n(방문자에게만 감추려면 취소하고 "숨기기"를 사용하세요.)');
}
</script>
<script>window.BG_CSRF=<?=json_encode(csrf_token())?>;</script><script src="bg-thumbs.js?v=<?=@filemtime(__DIR__.'/bg-thumbs.js')?>"></script>
<?php if ($probe): ?>
<script>
fetch(<?=json_encode($probe)?>, {cache:'no-store', credentials:'omit'}).then(function(r){
  if (r.ok) document.getElementById('exposeWarn').hidden = false;
}).catch(function(){});
</script>
<?php endif; ?>
<?php admin_foot(); ?>

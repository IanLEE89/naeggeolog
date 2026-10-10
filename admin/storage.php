<?php
// 저장소(SQLite): 상태 · DB 파일 내려받기 · 최적화(VACUUM) · 무결성 검사. 여행 글 백업은 "백업 · 복원" 메뉴를 쓰세요.
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/data.php';
require_once __DIR__.'/style.php';

$db = store(); $path = SqliteStore::dbPath(); $msg = ''; $err = '';
function st_fmt(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청입니다.'); }
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'download') {
        $tmp = sys_get_temp_dir() . '/naeggeolog-' . bin2hex(random_bytes(6)) . '.sqlite';
        try { $db->pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $tmp) . "'"); } catch (Throwable $e) { $tmp = null; $err = '파일을 만들지 못했습니다: ' . $e->getMessage(); }
        if ($tmp && is_file($tmp)) {
            header('Content-Type: application/vnd.sqlite3'); header('Content-Length: ' . filesize($tmp));
            header('Content-Disposition: attachment; filename="naeggeolog-' . date('Ymd-His') . '.sqlite"');
            readfile($tmp); @unlink($tmp); exit;
        }
    } elseif ($act === 'optimize') {
        $before = (int)@filesize($path);
        try { $db->pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)'); $db->pdo()->exec('VACUUM'); $db->pdo()->exec('ANALYZE'); clearstatcache(); $msg = '최적화했습니다. ' . st_fmt($before) . ' → ' . st_fmt((int)filesize($path)); }
        catch (Throwable $e) { $err = '최적화하지 못했습니다: ' . $e->getMessage(); }
    } elseif ($act === 'check') {
        try { $r = (string)$db->pdo()->query('PRAGMA integrity_check')->fetchColumn(); if ($r === 'ok') $msg = '무결성 검사 결과: 이상 없음 ✓'; else $err = '무결성 문제가 발견되었습니다: ' . $r . ' — 최근 백업으로 복원하세요.'; }
        catch (Throwable $e) { $err = '검사하지 못했습니다: ' . $e->getMessage(); }
    }
}
clearstatcache();
$size = (int)@filesize($path); $wal = (int)@filesize($path . '-wal');
$count = $db->count(); $dirOk = is_writable(dirname($path)) && is_writable($path);
$ver = (string)$db->pdo()->query('select sqlite_version()')->fetchColumn();

admin_head('저장소 (SQLite)'); ?>
<div class="adm-wrap">
  <?php admin_nav('storage'); ?>
  <section>
    <p class="text-xs tracking-[.18em] text-stone-400">STORAGE</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">저장소 (SQLite)</h1>
    <p class="mt-2 text-sm text-stone-500">모든 여행 글은 하나의 데이터베이스 파일(<code>private/<?=e(basename($path))?></code>)에 저장됩니다. 글 백업(zip)은 <a class="underline" href="backup.php">백업 · 복원</a>에서 하세요.</p>
    <?php if ($msg): ?><div class="adm-note" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?=e($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="adm-note" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?=e($err)?></div><?php endif; ?>
    <div class="adm-kpis">
      <div><small>여행</small><b><?=number_format($count)?>건</b><span>SQLite <?=e($ver)?></span></div>
      <div><small>DB 파일</small><b><?=st_fmt($size)?></b><span><?=$wal ? '로그 ' . st_fmt($wal) : '로그 없음'?></span></div>
      <div><small>쓰기 권한</small><b class="<?=$dirOk ? 'adm-ok' : 'adm-bad'?>" style="font-family:inherit;font-size:18px"><?=$dirOk ? '정상' : '없음'?></b><span><?=$dirOk ? '' : 'private 폴더에 http 계정 쓰기 권한 필요'?></span></div>
    </div>
    <form method="post" class="adm-bulk" style="margin-top:18px;display:flex">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <button class="adm-btn" name="action" value="download">DB 파일 내려받기</button>
      <button class="adm-btn" name="action" value="optimize">최적화 (용량 정리)</button>
      <button class="adm-btn" name="action" value="check">무결성 검사</button>
      <span class="adm-bulk-hint">글을 많이 지운 뒤 "최적화"를 누르면 파일이 작아집니다</span>
    </form>
  </section>
</div>
<?php admin_foot();

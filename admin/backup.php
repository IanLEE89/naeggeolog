<?php
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/backup.php';
require_once __DIR__.'/style.php';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$msg = ''; $err = '';

// 스냅샷 내려받기 (GET, 관리자 로그인 필요)
if (isset($_GET['dl'])) {
    $n = basename((string)$_GET['dl']);
    $f = backup_dir() . '/' . $n;
    if (!preg_match('/^(auto|pre-restore)-\d{8}-\d{6}-[a-f0-9]{6}\.zip$/', $n) || !is_file($f)) { http_response_code(404); exit('파일을 찾을 수 없습니다.'); }
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($f)); header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="naeggeolog-' . $n . '"'); readfile($f); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청입니다.'); }
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'download') {
        $tmp = sys_get_temp_dir() . '/naeggeolog-bk-' . bin2hex(random_bytes(6)) . '.zip';
        [$ok, $m] = backup_build($tmp);
        if ($ok && is_file($tmp)) {
            backup_meta_set('last_download', date('c'));
            header('Content-Type: application/zip'); header('Content-Length: ' . filesize($tmp)); header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment; filename="naeggeolog-backup-' . date('Ymd-His') . '.zip"');
            readfile($tmp); @unlink($tmp); exit;
        }
        @unlink($tmp); $err = $m ?: '백업을 만들지 못했습니다.';
    }
    if ($act === 'snapshot') { $n = backup_snapshot_if_due(true); $msg = $n ? '스냅샷을 만들었습니다.' : ''; $err = $n ? '' : '스냅샷을 만들지 못했습니다(zip 확장 또는 쓰기 권한 확인).'; }
    if ($act === 'delete_snapshot') {
        $n = basename((string)($_POST['name'] ?? '')); $f = backup_dir() . '/' . $n;
        if (preg_match('/^(auto|pre-restore)-\d{8}-\d{6}-[a-f0-9]{6}\.zip$/', $n) && is_file($f) && @unlink($f)) $msg = '스냅샷을 삭제했습니다.';
    }
    if ($act === 'restore') {
        $f = $_FILES['zip'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) $err = '백업 zip 파일을 선택해 주세요.';
        elseif (empty($_POST['confirm'])) $err = '"덮어써도 됩니다"에 체크해 주세요.';
        else { [$ok, $m] = backup_restore($f['tmp_name'], !empty($_POST['settings'])); if ($ok) $msg = $m; else $err = $m; }
    }
}
$days = backup_days_since_download(); $snaps = backup_list(); $zipOk = backup_zip_ok(); $meta = backup_meta();
admin_head('백업 · 복원');
?>
<div class="adm-wrap">
  <?php admin_nav('backup'); ?>
  <section>
    <p class="text-xs tracking-[.18em] text-stone-400">BACKUP</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">백업 · 복원</h1>
    <p class="mt-2 text-sm leading-6 text-stone-500">글·설정·테마를 zip 한 파일로 보관합니다. <b>사진(travel-photos)은 포함되지 않으니</b> Synology Hyper Backup 이나 폴더 복사로 따로 백업하세요.</p>
    <?php if ($msg): ?><div class="adm-note" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?=$h($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="adm-note" style="border-color:#fecaca;background:#fef2f2;color:#b91c1c"><?=$h($err)?></div><?php endif; ?>
    <?php if (!$zipOk): ?><div class="adm-note" style="border-color:#fde68a;background:#fffbeb;color:#92400e">PHP <b>zip</b> 확장이 꺼져 있습니다. Web Station → PHP 프로필 → 확장 모듈에서 zip 을 켜세요. (그 전에는 <a class="underline" href="storage.php">저장소</a> 화면의 JSON 백업을 쓸 수 있습니다.)</div><?php endif; ?>

    <div class="section-card mt-6 p-5 sm:p-6">
      <h2 class="section-heading">내 컴퓨터로 백업 내려받기</h2>
      <p class="section-description"><?php if ($days === null): ?>아직 내려받은 적이 없습니다.<?php else: ?>마지막으로 내려받은 지 <b><?=$days?>일</b> 지났습니다.<?php endif; ?> 서버가 고장나도 복구할 수 있도록, 가끔 PC나 클라우드에 보관해 두세요.</p>
      <form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?=$h(csrf_token())?>"><input type="hidden" name="action" value="download"><button class="adm-primary" <?=$zipOk?'':'disabled'?>>백업 내려받기 (.zip)</button></form>
    </div>

    <div class="section-card mt-5 p-5 sm:p-6">
      <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="section-heading">자동 스냅샷</h2><p class="section-description">관리자 화면에 접속할 때 7일이 지났으면 서버 안(<code>private/backups</code>)에 자동으로 한 개씩 만들고 최근 8개를 보관합니다. 실수로 여행을 지웠을 때 되살리는 용도입니다(서버 디스크가 고장나면 함께 사라지므로 위의 내려받기도 병행하세요).</p></div>
        <form method="post"><input type="hidden" name="csrf" value="<?=$h(csrf_token())?>"><input type="hidden" name="action" value="snapshot"><button class="adm-btn" <?=$zipOk?'':'disabled'?>>지금 만들기</button></form></div>
      <?php if ($snaps): ?>
      <table class="adm-table"><thead><tr><th>시각</th><th>크기</th><th style="text-align:right">관리</th></tr></thead><tbody>
      <?php foreach ($snaps as $s): ?><tr><td><?=$h(date('Y-m-d H:i', $s['time']))?><?=str_starts_with($s['name'], 'pre-')?' <span class="adm-badge">복원 직전</span>':''?></td><td><?=$h(backup_fmt_size($s['size']))?></td>
        <td><div class="adm-actions"><a class="adm-btn" href="backup.php?dl=<?=$h(rawurlencode($s['name']))?>">내려받기</a>
        <form method="post" onsubmit="return confirm('이 스냅샷을 삭제할까요?')"><input type="hidden" name="csrf" value="<?=$h(csrf_token())?>"><input type="hidden" name="action" value="delete_snapshot"><input type="hidden" name="name" value="<?=$h($s['name'])?>"><button class="adm-btn danger">삭제</button></form></div></td></tr>
      <?php endforeach; ?></tbody></table>
      <?php else: ?><p class="mt-4 text-xs text-stone-400">아직 스냅샷이 없습니다.</p><?php endif; ?>
    </div>

    <div class="section-card mt-5 p-5 sm:p-6">
      <h2 class="section-heading">zip 으로 복원</h2>
      <p class="section-description">같은 ID 의 여행은 <b>백업 내용으로 덮어씁니다</b>. 백업에 없는 여행은 그대로 둡니다. 복원 직전 상태는 자동으로 스냅샷에 보관됩니다.</p>
      <form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3">
        <input type="hidden" name="csrf" value="<?=$h(csrf_token())?>"><input type="hidden" name="action" value="restore">
        <input type="file" name="zip" accept=".zip,application/zip" class="text-sm">
        <label class="flex items-center gap-2 text-xs text-stone-600"><input type="checkbox" name="settings" value="1"> 사이트 설정·테마도 함께 복원</label>
        <label class="flex items-center gap-2 text-xs text-stone-600"><input type="checkbox" name="confirm" value="1"> 같은 ID 의 여행을 덮어써도 됩니다</label>
        <div><button class="adm-btn" style="height:38px;padding:0 16px" <?=$zipOk?'':'disabled'?>>복원하기</button></div>
      </form>
    </div>
  </section>
</div>
<?php admin_foot(); ?>

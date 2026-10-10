<?php
// 회원 비밀번호 변경 (첫 로그인 시 임시 비밀번호 → 본인 비밀번호로 강제 변경)
require_once __DIR__ . '/_boot.php';
$u = hub_require_login();
if (hub_is_owner($u)) { header('Location: ../admin/change-password.php'); exit; }

$error = ''; $ok = false; $first = !empty($u['must_change']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur = (string)($_POST['current'] ?? ''); $a = (string)($_POST['password'] ?? ''); $b = (string)($_POST['password2'] ?? '');
    if (!hub_csrf_ok($_POST['csrf'] ?? '')) $error = '페이지가 오래되었습니다. 다시 시도해주세요.';
    elseif (!password_verify($cur, (string)$u['password_hash'])) $error = '현재 비밀번호가 올바르지 않습니다.';
    elseif (mb_strlen($a) < 8) $error = '새 비밀번호는 8자 이상으로 입력해주세요.';
    elseif ($a !== $b) $error = '두 비밀번호가 일치하지 않습니다.';
    elseif ($a === $cur) $error = '현재와 다른 비밀번호를 사용해주세요.';
    else {
        $ver = (int)$u['pw_ver'] + 1;
        hub_db()->prepare('UPDATE users SET password_hash = ?, must_change = 0, pw_ver = ? WHERE id = ?')->execute([password_hash($a, PASSWORD_DEFAULT), $ver, (int)$u['id']]);
        session_regenerate_id(true);
        $_SESSION['hub_pw_ver'] = $ver;   // 다른 기기의 로그인은 끊기고 지금 기기만 유지
        $ok = true;
    }
}
hub_head('비밀번호 변경', $u, 'account');
?>
<div class="hb-narrow">
  <p class="hb-eyebrow">ACCOUNT</p>
  <h1 class="hb-h1"><?=$first ? '비밀번호 설정' : '비밀번호 변경'?></h1>
  <?php if ($first && !$ok): ?><p class="hb-sub">처음 로그인하셨네요. 받은 임시 비밀번호 대신 본인만 아는 비밀번호로 바꿔 주세요.</p><?php endif; ?>
  <?php if ($ok): ?>
    <div class="hb-card hb-pad"><p>변경되었습니다. 다른 기기에 로그인되어 있었다면 그곳은 로그아웃됩니다.</p><p style="margin-top:14px"><a class="hb-btn hb-btn-primary" href="./">내 공간으로</a></p></div>
  <?php else: ?>
  <form method="post" class="hb-card hb-form" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=e(hub_csrf())?>">
    <input type="text" name="username" value="<?=e($u['username'])?>" autocomplete="username" hidden>
    <label><?=$first ? '임시 비밀번호' : '현재 비밀번호'?><input type="password" name="current" autocomplete="current-password" required></label>
    <label>새 비밀번호 (8자 이상)<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
    <label>새 비밀번호 확인<input type="password" name="password2" autocomplete="new-password" minlength="8" required></label>
    <?php if ($error): ?><p class="hb-err" role="alert"><?=e($error)?></p><?php endif; ?>
    <button class="hb-btn hb-btn-primary">저장</button>
  </form>
  <?php endif; ?>
</div>
<?php hub_foot();

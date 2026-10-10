<?php
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../admin/auth.php';   // 로그인 시도 제한(IP당 5회 실패 → 15분 잠금)을 관리자와 함께 사용
hub_private_headers();

/** 로그인 후 돌아갈 주소: 같은 사이트의 경로만 허용 */
function hub_safe_next(string $n): string {
    return (preg_match('~^/(?!/)[^\\\\\s]*$~', $n) && strpos($n, '/hub/') !== false && strpos($n, 'logout') === false) ? $n : '';
}
$next = hub_safe_next((string)($_GET['next'] ?? $_POST['next'] ?? ''));
if (hub_current_user()) { header('Location: ' . ($next ?: hub_url(''))); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string)($_POST['username'] ?? '')); $pass = (string)($_POST['password'] ?? '');
    $wait = login_locked_seconds();
    if ($wait > 0) {
        $error = '로그인 시도가 너무 많습니다. ' . ceil($wait / 60) . '분 후 다시 시도해주세요.';
        hub_log_login($user, 'locked');
    } elseif (!hub_csrf_ok($_POST['csrf'] ?? '')) {
        $error = '페이지가 오래되었습니다. 다시 시도해주세요.';
    } else {
        $u = hub_valid_username($user) ? hub_user_by_name($user) : null;
        // 없는 아이디여도 같은 시간이 걸리게 검증을 수행
        $ok = password_verify($pass, $u['password_hash'] ?? '$2y$10$r6upqgVwOlbkcX/4iemV9u4ac6W6Frt9R4VI0mlRKyw10.Jo8EP7q');
        if ($u && $ok && $u['active']) {
            login_register_success();
            session_regenerate_id(true);
            $_SESSION['hub_uid'] = (int)$u['id']; $_SESSION['hub_pw_ver'] = (int)$u['pw_ver']; $_SESSION['hub_seen'] = time();
            hub_db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([date('c'), (int)$u['id']]);
            if (password_needs_rehash((string)$u['password_hash'], PASSWORD_DEFAULT)) hub_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), (int)$u['id']]);
            hub_log_login($user, 'ok');
            header('Location: ' . (!empty($u['must_change']) ? hub_url('account.php') : ($next ?: hub_url(''))));
            exit;
        }
        login_register_fail();
        hub_log_login($user, $u && $ok ? 'disabled' : 'fail');
        $error = ($u && $ok) ? '사용이 중지된 계정입니다. 관리자에게 문의하세요.' : '아이디 또는 비밀번호가 올바르지 않습니다.';
    }
}
hub_head('로그인');
?>
<div class="hb-login">
  <a class="hb-login-logo" href="../index.php"><img src="../assets/logo_icon.png" alt="" width="52" height="52"></a>
  <p class="hb-eyebrow"><?=e(SITE_TITLE)?></p>
  <h1>내 공간 로그인</h1>
  <form method="post" class="hb-card hb-form" autocomplete="on">
    <input type="hidden" name="csrf" value="<?=e(hub_csrf())?>">
    <input type="hidden" name="next" value="<?=e($next)?>">
    <label>아이디<input name="username" autocomplete="username" required autofocus value="<?=e($_POST['username'] ?? '')?>"></label>
    <label>비밀번호<input type="password" name="password" autocomplete="current-password" required></label>
    <?php if ($error): ?><p class="hb-err" role="alert"><?=e($error)?></p><?php endif; ?>
    <button class="hb-btn hb-btn-primary">로그인</button>
  </form>
  <p class="hb-login-alt">사이트 주인이신가요? <a href="../admin/login.php?next=hub">관리자 계정으로 로그인 →</a></p>
  <p class="hb-login-alt"><a href="../index.php">← 여행 블로그로 돌아가기</a></p>
</div>
<?php hub_foot();

<?php
// 로그아웃 — 회원·주인 모두 세션을 끝냅니다(POST + CSRF 로만 동작해 링크로 강제 로그아웃시키는 장난을 막음).
require_once __DIR__ . '/_boot.php';
hub_private_headers();
hub_session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hub_csrf_ok($_POST['csrf'] ?? '')) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']); }
    session_destroy();
}
header('Location: login.php');
exit;

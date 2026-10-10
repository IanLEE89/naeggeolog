<?php
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');   // Synology 리버스 프록시 대응
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_name('NAEGGEOLOGSESS');
    session_start();
}

function admin_file(): string { return travel_private_file('admin.json'); }
function admin_record() {
    $file = admin_file();
    if (!is_file($file)) return null;
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}
function admin_save_record(array $r): bool { return travel_write_json(admin_file(), $r); }
function admin_logged_in(): bool { return !empty($_SESSION['admin_logged_in']); }

/** 로그인 필수. 초기 비밀번호 변경이 안 끝났다면 어느 관리자 페이지로 가도 변경 화면으로 보냄. */
function require_admin(): void {
    if (!admin_logged_in()) { header('Location: login.php'); exit; }
    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($self, ['change-password.php', 'logout.php'], true)) {
        $r = admin_record();
        if ($r && !empty($r['must_change'])) { header('Location: change-password.php'); exit; }
    }
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check($token): bool { return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token); }
function admin_redirect($path = 'index.php'): void { header('Location: ' . $path); exit; }

// ── 로그인 시도 제한 (IP당 10분 안에 5회 실패 → 15분 잠금) ──
const LOGIN_MAX_FAILS = 5;
const LOGIN_WINDOW    = 600;
const LOGIN_LOCK      = 900;
function login_throttle_file(): string { return rtrim(PRIVATE_DIR, '/\\') . '/login-attempts.json'; }
function login_throttle_load(): array {
    $f = login_throttle_file();
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    return is_array($d) ? $d : [];
}
function login_throttle_key(): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|naeggeolog'); }
/** 잠겨 있으면 남은 초, 아니면 0 */
function login_locked_seconds(): int {
    $d = login_throttle_load(); $k = login_throttle_key();
    $until = (int)($d[$k]['locked_until'] ?? 0);
    return $until > time() ? $until - time() : 0;
}
function login_register_fail(): void {
    $d = login_throttle_load(); $k = login_throttle_key(); $now = time();
    // 오래된 기록 정리
    foreach ($d as $key => $row) { if (($row['last'] ?? 0) < $now - 86400) unset($d[$key]); }
    $row = $d[$k] ?? ['fails' => [], 'locked_until' => 0];
    $row['fails'] = array_values(array_filter((array)$row['fails'], fn($t) => $t > $now - LOGIN_WINDOW));
    $row['fails'][] = $now; $row['last'] = $now;
    if (count($row['fails']) >= LOGIN_MAX_FAILS) { $row['locked_until'] = $now + LOGIN_LOCK; $row['fails'] = []; }
    $d[$k] = $row;
    travel_write_json(login_throttle_file(), $d);
}
function login_register_success(): void {
    $d = login_throttle_load(); $k = login_throttle_key();
    if (isset($d[$k])) { unset($d[$k]); travel_write_json(login_throttle_file(), $d); }
}

// ── 로그인 기록 (최근 60건, private/login-log.json) ──
function login_log_file(): string { return rtrim(PRIVATE_DIR, '/\\') . '/login-log.json'; }
function login_log_load(): array { $f = login_log_file(); $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : []; return is_array($d) ? $d : []; }
/** $result: ok | fail_pw | fail_2fa | locked */
function login_log(string $result, string $user = ''): void {
    $d = login_log_load();
    array_unshift($d, ['t' => time(), 'r' => $result, 'u' => mb_substr($user, 0, 32), 'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160)]);
    travel_write_json(login_log_file(), array_slice($d, 0, 60));
}
function login_ua_label(string $ua): string {
    $b = stripos($ua, 'Edg') !== false ? 'Edge' : (stripos($ua, 'Chrome') !== false ? 'Chrome' : (stripos($ua, 'Firefox') !== false ? 'Firefox' : (stripos($ua, 'Safari') !== false ? 'Safari' : '브라우저')));
    $o = stripos($ua, 'Windows') !== false ? 'Windows' : (stripos($ua, 'Android') !== false ? 'Android' : (preg_match('/iPhone|iPad/', $ua) ? 'iOS' : (stripos($ua, 'Mac') !== false ? 'Mac' : (stripos($ua, 'Linux') !== false ? 'Linux' : ''))));
    return trim($b . ' · ' . $o, ' ·');
}
/** 이번 로그인 직전의 마지막 성공 기록과, 그 이후의 실패 횟수 — 로그인 직후 세션에 담아 알림용으로 쓴다 */
function login_prev_summary(): array {
    $fails = 0; $prev = null;
    foreach (login_log_load() as $e) { if ($e['r'] === 'ok') { $prev = $e; break; } if (in_array($e['r'], ['fail_pw', 'fail_2fa', 'locked'], true)) $fails++; }
    return ['prev' => $prev, 'fails' => $fails];
}

<?php
/**
 * 내 공간(hub) 공통 라이브러리 — 회원 계정 · 공유 폴더 · 모듈 목록.
 *   데이터: private/hub.sqlite  (웹에서 직접 열 수 없음)
 *   주인(관리자)은 admin.json 계정 그대로 사용하며, 모든 공유 폴더에 접근합니다.
 *   가족·지인(회원)은 관리자 → "사용자 관리"에서 만든 계정으로 /hub/login.php 에서 로그인합니다.
 * PHP 8.0 호환(8.1 이상 전용 문법 사용 안 함).
 */
require_once __DIR__ . '/../config.php';

if (!defined('HUB_IDLE_SECONDS')) define('HUB_IDLE_SECONDS', 8 * 3600);   // 회원 세션: 이 시간 동안 아무 활동이 없으면 로그아웃
if (!defined('HUB_LIST_LIMIT'))   define('HUB_LIST_LIMIT', 500);          // 한 폴더에서 처음 보여 줄 항목 수(더 보기로 늘림)

/* ───────────────────────── DB ───────────────────────── */
function hub_db_path(): string { return rtrim(PRIVATE_DIR, '/\\') . '/hub.sqlite'; }
function hub_db(): PDO {
    static $db = null;
    if ($db) return $db;
    $db = new PDO('sqlite:' . hub_db_path(), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA foreign_keys = ON');
    if ((int)$db->query('PRAGMA user_version')->fetchColumn() < 1) {
        try { $db->exec('PRAGMA journal_mode = WAL'); } catch (Throwable $e) { /* 일부 파일시스템은 WAL 불가 */ }
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE COLLATE NOCASE, display_name TEXT NOT NULL DEFAULT '',
            password_hash TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, must_change INTEGER NOT NULL DEFAULT 1,
            pw_ver INTEGER NOT NULL DEFAULT 1, note TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, last_login TEXT)");
        $db->exec("CREATE TABLE IF NOT EXISTS shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, path TEXT NOT NULL UNIQUE, note TEXT NOT NULL DEFAULT '',
            active INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS share_access (
            share_id INTEGER NOT NULL REFERENCES shares(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, PRIMARY KEY (share_id, user_id))");
        $db->exec("CREATE TABLE IF NOT EXISTS downloads (
            id INTEGER PRIMARY KEY AUTOINCREMENT, t INTEGER NOT NULL, user TEXT NOT NULL, share_id INTEGER, path TEXT NOT NULL,
            bytes INTEGER NOT NULL DEFAULT 0, ip TEXT NOT NULL DEFAULT '')");
        $db->exec("CREATE TABLE IF NOT EXISTS logins (
            id INTEGER PRIMARY KEY AUTOINCREMENT, t INTEGER NOT NULL, user TEXT NOT NULL, result TEXT NOT NULL, ip TEXT NOT NULL DEFAULT '', ua TEXT NOT NULL DEFAULT '')");
        $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
        $db->exec('PRAGMA user_version = 1');
    }
    if ((int)$db->query('PRAGMA user_version')->fetchColumn() < 2) {   // 16단계: 주인 업로드
        $cols = array_column($db->query('PRAGMA table_info(shares)')->fetchAll(), 'name');
        if (!in_array('allow_upload', $cols, true)) $db->exec("ALTER TABLE shares ADD COLUMN allow_upload INTEGER NOT NULL DEFAULT 0");
        $cols = array_column($db->query('PRAGMA table_info(downloads)')->fetchAll(), 'name');
        if (!in_array('kind', $cols, true)) $db->exec("ALTER TABLE downloads ADD COLUMN kind TEXT NOT NULL DEFAULT 'down'");
        $db->exec('PRAGMA user_version = 2');
    }
    return $db;
}
function hub_available(): bool { return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true); }
function hub_setting(string $k, ?string $default = null): ?string {
    $s = hub_db()->prepare('SELECT value FROM settings WHERE key = ?'); $s->execute([$k]); $v = $s->fetchColumn();
    return $v === false ? $default : (string)$v;
}
function hub_set_setting(string $k, string $v): void { hub_db()->prepare('INSERT OR REPLACE INTO settings(key,value) VALUES(?,?)')->execute([$k, $v]); }

/* ───────────────────────── 회원 ───────────────────────── */
function hub_users(): array { return hub_db()->query('SELECT * FROM users ORDER BY active DESC, username COLLATE NOCASE')->fetchAll(); }
function hub_user(int $id): ?array { $s = hub_db()->prepare('SELECT * FROM users WHERE id = ?'); $s->execute([$id]); $r = $s->fetch(); return $r ?: null; }
function hub_user_by_name(string $u): ?array { $s = hub_db()->prepare('SELECT * FROM users WHERE username = ?'); $s->execute([$u]); $r = $s->fetch(); return $r ?: null; }
function hub_valid_username(string $u): bool { return (bool)preg_match('/^[A-Za-z0-9._-]{3,32}$/', $u); }
/** 사람이 읽기 쉬운 임시 비밀번호(헷갈리는 글자 제외) */
function hub_temp_password(int $len = 12): string {
    $a = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $o = '';
    for ($i = 0; $i < $len; $i++) $o .= $a[random_int(0, strlen($a) - 1)];
    return substr($o, 0, 4) . '-' . substr($o, 4, 4) . '-' . substr($o, 8);
}
function hub_log_login(string $user, string $result): void {
    try {
        hub_db()->prepare('INSERT INTO logins(t,user,result,ip,ua) VALUES(?,?,?,?,?)')
            ->execute([time(), mb_substr($user, 0, 32), $result, (string)($_SERVER['REMOTE_ADDR'] ?? ''), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160)]);
        hub_db()->exec('DELETE FROM logins WHERE id NOT IN (SELECT id FROM logins ORDER BY id DESC LIMIT 300)');
    } catch (Throwable $e) { /* 기록 실패는 로그인을 막지 않음 */ }
}

/* ───────────────────────── 세션 · 현재 사용자 ───────────────────────── */
/** 관리자 화면과 같은 세션 쿠키(NAEGGEOLOGSESS)를 사용 — 한 번 로그인하면 양쪽 모두 인식 */
function hub_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_name('NAEGGEOLOGSESS');
    session_start();
}
/**
 * 현재 사용자. 주인(관리자 로그인)이면 role=owner, 회원이면 role=member, 아니면 null.
 * 회원은 매 요청마다 DB 와 대조 — 정지되거나 관리자가 비밀번호를 바꾸면 즉시 로그아웃.
 */
function hub_current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    hub_session_start();
    if (!empty($_SESSION['admin_logged_in'])) {
        $name = (string)($_SESSION['admin_user'] ?? 'owner');
        return $cache = ['id' => 0, 'username' => $name, 'display_name' => '주인', 'role' => 'owner', 'must_change' => 0];
    }
    $uid = (int)($_SESSION['hub_uid'] ?? 0);
    if ($uid <= 0) return $cache = null;
    $u = hub_user($uid);
    $idle = time() - (int)($_SESSION['hub_seen'] ?? 0);
    if (!$u || !$u['active'] || (int)$u['pw_ver'] !== (int)($_SESSION['hub_pw_ver'] ?? -1) || $idle > HUB_IDLE_SECONDS) {
        unset($_SESSION['hub_uid'], $_SESSION['hub_pw_ver'], $_SESSION['hub_seen']);
        return $cache = null;
    }
    $_SESSION['hub_seen'] = time();
    $u['role'] = 'member';
    if ($u['display_name'] === '') $u['display_name'] = $u['username'];
    return $cache = $u;
}
function hub_is_owner(?array $u): bool { return $u && ($u['role'] ?? '') === 'owner'; }
function hub_csrf(): string { hub_session_start(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function hub_csrf_ok($t): bool { return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

/* ───────────────────────── 공유 폴더 ───────────────────────── */
function hub_shares_all(): array { return hub_db()->query('SELECT * FROM shares ORDER BY sort, name COLLATE NOCASE')->fetchAll(); }
function hub_share(int $id): ?array { $s = hub_db()->prepare('SELECT * FROM shares WHERE id = ?'); $s->execute([$id]); $r = $s->fetch(); return $r ?: null; }
/** 사용자가 볼 수 있는 (켜진) 공유 폴더 */
function hub_shares_for(array $u): array {
    if (hub_is_owner($u)) return hub_db()->query('SELECT * FROM shares WHERE active = 1 ORDER BY sort, name COLLATE NOCASE')->fetchAll();
    $s = hub_db()->prepare('SELECT s.* FROM shares s JOIN share_access a ON a.share_id = s.id WHERE a.user_id = ? AND s.active = 1 ORDER BY s.sort, s.name COLLATE NOCASE');
    $s->execute([(int)$u['id']]);
    return $s->fetchAll();
}
function hub_can_access(array $u, int $shareId): ?array {
    foreach (hub_shares_for($u) as $s) if ((int)$s['id'] === $shareId) return $s;
    return null;
}
function hub_share_user_ids(int $shareId): array {
    $s = hub_db()->prepare('SELECT user_id FROM share_access WHERE share_id = ?'); $s->execute([$shareId]);
    return array_map('intval', array_column($s->fetchAll(), 'user_id'));
}
function hub_user_share_ids(int $userId): array {
    $s = hub_db()->prepare('SELECT share_id FROM share_access WHERE user_id = ?'); $s->execute([$userId]);
    return array_map('intval', array_column($s->fetchAll(), 'share_id'));
}
/** 공유 폴더 경로 정리: 절대 경로, 끝 / 제거 */
function hub_norm_abs(string $p): string {
    $p = str_replace('\\', '/', trim($p));
    $p = preg_replace('~/+~', '/', $p);
    return $p === '/' ? '/' : rtrim($p, '/');
}

/** Synology 시스템 폴더·숨김 파일 — 목록에서 숨기고 직접 접근도 막음 */
function hub_is_hidden_name(string $n): bool {
    if ($n === '' || $n[0] === '.' || $n[0] === '@' || $n[0] === '#') return true;   // .DS_Store, @eaDir, #recycle, #snapshot …
    return in_array(strtolower($n), ['thumbs.db', 'desktop.ini', '$recycle.bin', 'system volume information'], true);
}
/** 사용자 입력 상대 경로 정리. 위험한 경로면 null */
function hub_clean_rel(string $rel): ?string {
    $rel = str_replace('\\', '/', $rel);
    if (strpos($rel, "\0") !== false) return null;
    $parts = [];
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..' || hub_is_hidden_name($seg)) return null;
        $parts[] = $seg;
    }
    return implode('/', $parts);
}
/**
 * 공유 폴더 안의 실제 경로. 바깥으로 빠져나가는 경로(.., 심볼릭 링크 탈출)는 null.
 * 반환: ['abs' => 실제 경로, 'rel' => 정리된 상대 경로, 'root' => 공유 폴더 실제 경로]
 */
function hub_resolve(array $share, string $rel): ?array {
    $root = realpath((string)$share['path']);
    if ($root === false || !is_dir($root)) return null;
    $clean = hub_clean_rel($rel);
    if ($clean === null) return null;
    $target = $clean === '' ? $root : realpath($root . '/' . $clean);
    if ($target === false) return null;
    $r = rtrim(str_replace('\\', '/', $root), '/'); $t = str_replace('\\', '/', $target);
    if ($t !== $r && strpos($t, $r . '/') !== 0) return null;
    return ['abs' => $target, 'rel' => $clean, 'root' => $root];
}
/** 폴더 목록: [폴더들, 파일들] (숨김 제외, 공유 폴더 밖을 가리키는 바로가기(심볼릭 링크) 제외) */
function hub_list_dir(string $abs, string $root = ''): array {
    $dirs = $files = [];
    $h = @opendir($abs);
    if (!$h) return [null, null];
    $root = rtrim(str_replace('\\', '/', $root), '/');
    while (($n = readdir($h)) !== false) {
        if ($n === '.' || $n === '..' || hub_is_hidden_name($n)) continue;
        $p = $abs . '/' . $n;
        if ($root !== '' && is_link($p)) { $rp = realpath($p); if ($rp === false || strpos(str_replace('\\', '/', $rp) . '/', $root . '/') !== 0) continue; }
        $isDir = @is_dir($p);
        $st = @stat($p);
        $row = ['name' => $n, 'mtime' => $st ? (int)$st['mtime'] : 0, 'size' => ($st && !$isDir) ? (float)$st['size'] : 0];
        if ($isDir) $dirs[] = $row; else $files[] = $row;
    }
    closedir($h);
    return [$dirs, $files];
}
function hub_fmt_size(float $b): string {
    if ($b < 1024) return (int)$b . ' B';
    foreach (['KB', 'MB', 'GB', 'TB'] as $i => $u) { $b /= 1024; if ($b < 1024 || $u === 'TB') return ($b >= 100 ? round($b) : round($b, 1)) . ' ' . $u; }
    return '';
}
/** 확장자 → 종류(아이콘·미리보기용) */
function hub_kind(string $name): string {
    $x = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'bmp', 'tif', 'tiff', 'avif', 'svg', 'raw', 'cr2', 'cr3', 'nef', 'arw', 'dng'],
        'video' => ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'avi', 'wmv', 'mts', 'm2ts', '3gp'],
        'audio' => ['mp3', 'm4a', 'aac', 'flac', 'wav', 'ogg', 'opus', 'wma'],
        'pdf'   => ['pdf'],
        'doc'   => ['doc', 'docx', 'hwp', 'hwpx', 'odt', 'rtf', 'txt', 'md', 'pages'],
        'sheet' => ['xls', 'xlsx', 'csv', 'ods', 'numbers'],
        'slide' => ['ppt', 'pptx', 'odp', 'key'],
        'zip'   => ['zip', '7z', 'rar', 'tar', 'gz', 'tgz', 'bz2', 'xz', 'iso', 'dmg'],
    ];
    foreach ($map as $k => $list) if (in_array($x, $list, true)) return $k;
    return 'file';
}
/** 브라우저에서 바로 열어도 안전한 형식(HTML·SVG 같은 스크립트 실행 가능 형식은 항상 다운로드) */
function hub_inline_mime(string $name): ?string {
    $x = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $safe = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'bmp' => 'image/bmp',
             'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
             'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'opus' => 'audio/ogg', 'flac' => 'audio/flac',
             'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'md' => 'text/plain; charset=utf-8', 'csv' => 'text/plain; charset=utf-8'];
    return $safe[$x] ?? null;
}
/** Synology 가 미리 만들어 둔 썸네일(@eaDir) — 있으면 그 경로, 없으면 null. (사진 색인/Synology Photos 사용 시 생김) */
function hub_syno_thumb(string $fileAbs, bool $big = false): ?string {
    $dir = dirname($fileAbs); $base = basename($fileAbs);
    $order = $big ? ['SYNOPHOTO_THUMB_XL.jpg', 'SYNOPHOTO_THUMB_L.jpg', 'SYNOPHOTO_THUMB_M.jpg']
                  : ['SYNOPHOTO_THUMB_M.jpg', 'SYNOPHOTO_THUMB_SM.jpg', 'SYNOPHOTO_THUMB_S.jpg', 'SYNOPHOTO_THUMB_L.jpg'];
    foreach ($order as $t) {
        $p = $dir . '/@eaDir/' . $base . '/' . $t;
        if (is_file($p)) return $p;
    }
    return null;
}
/**
 * Synology 썸네일이 없을 때 직접 만드는 작은 미리보기(긴 변 360px JPEG). JPEG·PNG·WebP 만, GD 필요.
 * 웹에서 열 수 없는 private/hub-thumbs 에 저장(원본 경로·수정시각 기준이라 파일이 바뀌면 새로 만듦). 실패하면 null.
 */
function hub_gd_thumb(string $fileAbs): ?string {
    require_once __DIR__ . '/images.php';
    if (!photo_gd_ok()) return null;
    $st = @stat($fileAbs); if (!$st || $st['size'] > 60 * 1048576) return null;
    $dir = rtrim(PRIVATE_DIR, '/\\') . '/hub-thumbs';
    $out = $dir . '/' . sha1($fileAbs . '|' . $st['mtime'] . '|' . $st['size']) . '.jpg';
    if (is_file($out)) return $out;
    $info = @getimagesize($fileAbs);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || !photo_mem_ok((int)$info[0], (int)$info[1])) return null;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
    $lock = photo_lock(10);                         // 낮은 사양 NAS 보호: 한 번에 한 장씩
    if ($lock === false) return null;
    if (is_file($out)) { photo_unlock($lock); return $out; }
    $im = photo_load($fileAbs, (int)$info[2]);
    if (!$im) { photo_unlock($lock); return null; }
    $im = photo_orient($im, $fileAbs, (int)$info[2]);
    $w = imagesx($im); $h = imagesy($im);
    $im = photo_resample($im, $w >= $h ? 360 : max(1, (int)round(360 * $w / $h)));
    $ok = photo_save($im, $out, 'jpg', 78);
    imagedestroy($im); photo_unlock($lock);
    return $ok ? $out : null;
}

function hub_log_download(array $u, int $shareId, string $rel, float $bytes, string $kind = 'down'): void {
    try {
        hub_db()->prepare('INSERT INTO downloads(t,user,share_id,path,bytes,ip,kind) VALUES(?,?,?,?,?,?,?)')
            ->execute([time(), (string)$u['username'] . (hub_is_owner($u) ? ' (주인)' : ''), $shareId, mb_substr($rel, 0, 500), (int)$bytes, (string)($_SERVER['REMOTE_ADDR'] ?? ''), $kind]);
        hub_db()->exec('DELETE FROM downloads WHERE id NOT IN (SELECT id FROM downloads ORDER BY id DESC LIMIT 1000)');
    } catch (Throwable $e) { /* 기록 실패는 다운로드를 막지 않음 */ }
}

/* ───────────────────────── 업로드 (주인 전용) ───────────────────────── */
/** 이 공유 폴더에 지금 이 사용자가 올릴 수 있는지: 주인 + 폴더 설정에서 허용 + 웹 서버 쓰기 권한 */
function hub_can_upload(?array $u, array $share): bool { return hub_is_owner($u) && !empty($share['allow_upload']); }
/** 올릴 파일·새 폴더 이름 정리. 쓸 수 없는 이름이면 null */
function hub_clean_name(string $n): ?string {
    $n = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace(['/', '\\'], '_', $n)));
    $n = rtrim($n, ". ");                                     // 끝 점·공백은 Windows/SMB 에서 문제
    if ($n === '' || $n === '.' || $n === '..' || hub_is_hidden_name($n)) return null;
    if (strlen($n) > 240) {                                   // 대부분 파일시스템 한도 255바이트 — 확장자는 살림
        $ext = pathinfo($n, PATHINFO_EXTENSION); $ext = $ext !== '' && strlen($ext) <= 10 ? '.' . $ext : '';
        $n = mb_strcut(substr($n, 0, strlen($n) - strlen($ext)), 0, 240 - strlen($ext), 'UTF-8') . $ext;
    }
    return $n;
}
/** 같은 이름이 있으면 "이름 (1).확장자" 처럼 비어 있는 이름을 찾음 */
function hub_unique_name(string $dir, string $name): string {
    if (!file_exists($dir . '/' . $name)) return $name;
    $ext = pathinfo($name, PATHINFO_EXTENSION); $base = $ext !== '' ? substr($name, 0, -strlen($ext) - 1) : $name;
    for ($i = 1; $i < 10000; $i++) { $c = $base . ' (' . $i . ')' . ($ext !== '' ? '.' . $ext : ''); if (!file_exists($dir . '/' . $c)) return $c; }
    return $base . ' (' . bin2hex(random_bytes(3)) . ')' . ($ext !== '' ? '.' . $ext : '');
}
/** 홈페이지(웹) 폴더 안이면 PHP 등 실행 가능한 파일은 막음 — 올린 파일이 서버에서 실행되지 않게 */
function hub_upload_ext_blocked(string $dirAbs, string $name): bool {
    $x = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($x, ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps', 'pht', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'shtml', 'htaccess'], true)) return false;
    $d = str_replace('\\', '/', $dirAbs) . '/';
    foreach ([realpath(dirname(__DIR__)), realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/volume1/web', '/var/services/web'] as $w) {
        if ($w && strpos($d, rtrim(str_replace('\\', '/', $w), '/') . '/') === 0) return true;
    }
    return false;
}
/** PHP 가 한 번에 받을 수 있는 크기(바이트) → 조각 크기를 이보다 작게 */
function hub_ini_bytes(string $k): int {
    $v = trim((string)ini_get($k)); if ($v === '' || $v === '0' || $v === '-1') return PHP_INT_MAX;
    $n = (int)$v; $u = strtolower(substr($v, -1));
    if ($u === 'g') $n *= 1073741824; elseif ($u === 'm') $n *= 1048576; elseif ($u === 'k') $n *= 1024;
    return $n;
}
function hub_chunk_size(): int {
    $lim = min(hub_ini_bytes('upload_max_filesize'), hub_ini_bytes('post_max_size'));
    return (int)max(256 * 1024, min(8 * 1048576, $lim - 512 * 1024));   // 최대 8MB, 한도보다 여유 있게
}

/** NAS 폴더 탐색 시작점(관리자 폴더 선택기). config.local.php 에서 define('HUB_BROWSE_ROOTS', ['/volume1','/volume2']) 로 바꿀 수 있음 */
function hub_browse_roots(): array {
    if (defined('HUB_BROWSE_ROOTS')) return array_values(array_filter(array_map('hub_norm_abs', (array)HUB_BROWSE_ROOTS)));
    $v = @glob('/volume[0-9]*', GLOB_ONLYDIR) ?: [];                 // Synology NAS
    if ($v) return $v;
    if (DIRECTORY_SEPARATOR === '\\') {                               // Windows: 연결된 드라이브
        foreach (range('C', 'Z') as $d) if (@is_dir($d . ':/')) $v[] = $d . ':';
        return $v ?: ['C:'];
    }
    foreach (['/home', '/mnt', '/media', '/srv', '/data'] as $d) if (@is_dir($d)) $v[] = $d;   // 일반 Linux 서버
    return $v ?: [hub_norm_abs(dirname(__DIR__, 2))];
}
/** 관리자 폴더 선택기에서 이 경로를 탐색해도 되는지(시작점 아래만) */
function hub_browse_allowed(string $abs): bool {
    $abs = hub_norm_abs($abs);
    foreach (hub_browse_roots() as $r) if ($abs === $r || strpos($abs, $r . '/') === 0) return true;
    return false;
}

/* ───────────────────────── 모듈 ─────────────────────────
 * hub/modules/<이름>/module.php 가 배열을 return 하면 대시보드에 카드가 생깁니다.
 *   'title' => '파일', 'desc' => '설명', 'url' => 'modules/files/'(hub 기준 상대 경로 또는 ../ 로 시작),
 *   'order' => 20, 'icon' => '<path d="…"/>'(24x24 SVG 내용),
 *   'visible' => function (array $user): bool { … }   // 선택: 이 사용자에게 보일지
 *   'badge'   => function (array $user): string { … } // 선택: 카드 아래 작은 글씨
 * 관리자 → "내 공간 · 기능"에서 끄고 켤 수 있습니다.
 */
function hub_modules_all(): array {
    static $mods = null;
    if ($mods !== null) return $mods;
    $mods = [];
    foreach (glob(dirname(__DIR__) . '/hub/modules/*/module.php') ?: [] as $f) {
        $id = basename(dirname($f));
        if (!preg_match('/^[a-z0-9_-]+$/', $id)) continue;
        $m = (static function ($__f) { return include $__f; })($f);
        if (!is_array($m) || empty($m['title'])) continue;
        $m['id'] = $id;
        $m += ['desc' => '', 'url' => 'modules/' . $id . '/', 'order' => 50, 'icon' => '<rect x="4" y="4" width="16" height="16" rx="3"/>'];
        $mods[$id] = $m;
    }
    uasort($mods, fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcmp($a['title'], $b['title']));
    return $mods;
}
function hub_module_disabled_ids(): array { $v = json_decode((string)hub_setting('modules_off', '[]'), true); return is_array($v) ? $v : []; }
function hub_module_enabled(string $id): bool { return !in_array($id, hub_module_disabled_ids(), true); }
/** 이 사용자에게 보이는 모듈 */
function hub_modules_for(array $u): array {
    $out = [];
    foreach (hub_modules_all() as $id => $m) {
        if (!hub_module_enabled($id)) continue;
        if (isset($m['visible']) && is_callable($m['visible']) && !$m['visible']($u)) continue;
        $out[$id] = $m;
    }
    return $out;
}

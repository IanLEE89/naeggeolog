<?php
/**
 * NAS(시놀로지) 폴더에서 여행 사진 고르기 — 관리자 전용 JSON/이미지 API.
 *   "내 공간 → 공유 폴더"에 연결해 둔 폴더(예: 사진 백업 폴더)를 그대로 사용합니다.
 *   NAS 원본은 읽기만 합니다(지우거나 바꾸지 않음). 가져온 사진은 travel-photos/<여행 폴더> 에 복사·축소되어 저장됩니다.
 *
 *   GET  ?a=shares                       연결된 공유 폴더 목록
 *   GET  ?a=list&s=ID&p=상대경로&o=시작&sort=new|old|name   폴더 안의 하위 폴더 + 사진
 *   GET  ?a=thumb&s=ID&p=파일            미리보기(Synology 썸네일 → 없으면 작은 JPG 생성) — 없으면 404
 *   GET  ?a=raw&s=ID&p=파일              원본(HEIC 을 브라우저에서 JPG 로 바꿀 때 사용)
 *   POST a=import, csrf, s, p, photo_folder   JPG/PNG/WEBP 를 서버에서 바로 여행 폴더로 복사
 * PHP 8.0 호환.
 */
require_once __DIR__ . '/auth.php'; require_admin();
require_once __DIR__ . '/../includes/images.php';
require_once __DIR__ . '/../includes/hub.php';

const NASP_PAGE = 240;                                            // 한 번에 보여 줄 사진 수(더 보기로 이어서)
const NASP_IMG  = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

function nasp_json(array $d, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function nasp_fail(int $code, string $msg): void { nasp_json(['ok' => false, 'message' => $msg], $code); }
function nasp_ext(string $n): string { return strtolower(pathinfo($n, PATHINFO_EXTENSION)); }
/** 공유 폴더 + 그 안의 경로를 안전하게 찾기. 실패하면 응답하고 끝냄 */
function nasp_target(bool $wantFile): array {
    $sid = (int)($_REQUEST['s'] ?? 0);
    $share = $sid > 0 ? hub_share($sid) : null;
    if (!$share) nasp_fail(404, '공유 폴더를 찾을 수 없습니다.');
    $t = hub_resolve($share, (string)($_REQUEST['p'] ?? ''));
    if (!$t) nasp_fail(404, '폴더에 접근할 수 없습니다. (경로가 바뀌었거나, NAS 공유 폴더 권한·open_basedir 설정을 확인하세요)');
    if ($wantFile && (!is_file($t['abs']) || !in_array(nasp_ext($t['abs']), NASP_IMG, true))) nasp_fail(404, '사진 파일이 아닙니다.');
    if (!$wantFile && !is_dir($t['abs'])) nasp_fail(404, '폴더가 아닙니다.');
    return [$share, $t];
}
function nasp_send_file(string $abs, string $mime, int $maxAge): void {
    $st = @stat($abs); if (!$st) { http_response_code(404); exit; }
    $etag = '"' . substr(sha1($abs . '|' . $st['mtime'] . '|' . $st['size']), 0, 20) . '"';
    header('Cache-Control: private, max-age=' . $maxAge);
    header('ETag: ' . $etag);
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $st['size']);
    header('X-Content-Type-Options: nosniff');
    while (ob_get_level()) ob_end_clean();
    readfile($abs);
    exit;
}

if (!hub_available()) nasp_fail(500, 'PHP SQLite(pdo_sqlite) 확장이 필요합니다.');
$a = (string)($_REQUEST['a'] ?? '');

/* ── 공유 폴더 목록 ── */
if ($a === 'shares') {
    session_write_close();
    $out = [];
    foreach (hub_shares_all() as $s) {
        $ok = is_dir((string)$s['path']) && is_readable((string)$s['path']);
        $out[] = ['id' => (int)$s['id'], 'name' => (string)$s['name'], 'path' => (string)$s['path'], 'hidden' => !(int)$s['active'], 'ok' => $ok];
    }
    // heic_server: 서버가 HEIC 을 직접 바꿀 수 있고(Imagick) 촬영 정보도 읽을 수 있으면(PHP 8.2+) 원본을 내려받지 않고 서버에서 처리
    nasp_json(['ok' => true, 'shares' => $out, 'heic_server' => photo_heic_server_ok() && PHP_VERSION_ID >= 80200]);
}

/* ── 폴더 안 보기 ── */
if ($a === 'list') {
    session_write_close();
    [$share, $t] = nasp_target(false);
    [$dirs, $files] = hub_list_dir($t['abs'], $t['root']);
    if ($dirs === null) nasp_fail(403, '이 폴더를 읽을 수 없습니다. NAS 공유 폴더 권한에서 http 사용자에게 읽기 권한이 있는지 확인하세요.');
    $sort = (string)($_GET['sort'] ?? 'new');
    $imgs = []; $other = 0;
    foreach ($files as $f) {
        $x = nasp_ext($f['name']);
        if (!in_array($x, NASP_IMG, true)) { $other++; continue; }
        $imgs[] = ['name' => $f['name'], 'size' => (int)$f['size'], 'mtime' => (int)$f['mtime'], 'heic' => in_array($x, ['heic', 'heif'], true)];
    }
    $byName = fn($a, $b) => strnatcasecmp($a['name'], $b['name']);
    // 백업 폴더는 보통 연도·월 폴더라 최신 폴더를 위로. 사진은 고른 정렬대로
    usort($dirs, fn($a, $b) => $sort === 'old' ? strnatcasecmp($a['name'], $b['name']) : strnatcasecmp($b['name'], $a['name']));
    if ($sort === 'name') usort($imgs, $byName);
    elseif ($sort === 'old') usort($imgs, fn($a, $b) => ($a['mtime'] <=> $b['mtime']) ?: $byName($a, $b));
    else usort($imgs, fn($a, $b) => ($b['mtime'] <=> $a['mtime']) ?: $byName($b, $a));
    $o = max(0, (int)($_GET['o'] ?? 0));
    $crumbs = []; $acc = '';
    foreach ($t['rel'] === '' ? [] : explode('/', $t['rel']) as $seg) { $acc = ltrim($acc . '/' . $seg, '/'); $crumbs[] = ['name' => $seg, 'path' => $acc]; }
    nasp_json([
        'ok' => true, 'share' => ['id' => (int)$share['id'], 'name' => (string)$share['name']],
        'path' => $t['rel'], 'crumbs' => $crumbs,
        'dirs' => $o === 0 ? array_map(fn($d) => ['name' => $d['name'], 'mtime' => (int)$d['mtime']], $dirs) : [],
        'files' => array_slice($imgs, $o, NASP_PAGE), 'total' => count($imgs), 'offset' => $o, 'more' => $o + NASP_PAGE < count($imgs),
        'other' => $other,
    ]);
}

/* ── 미리보기 ── */
if ($a === 'thumb') {
    session_write_close();
    [, $t] = nasp_target(true);
    $p = hub_syno_thumb($t['abs']) ?: hub_gd_thumb($t['abs']);    // HEIC 은 Synology 썸네일(@eaDir)이 있을 때만
    if (!$p) { http_response_code(404); header('Cache-Control: private, max-age=300'); exit; }
    nasp_send_file($p, 'image/jpeg', 86400);
}

/* ── 원본(브라우저에서 HEIC → JPG 변환용) ── */
if ($a === 'raw') {
    session_write_close();
    [, $t] = nasp_target(true);
    if ((int)@filesize($t['abs']) > 80 * 1048576) nasp_fail(413, '80MB 가 넘는 사진은 가져올 수 없습니다.');
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][nasp_ext($t['abs'])] ?? 'application/octet-stream';
    header('Content-Disposition: attachment; filename="photo.' . nasp_ext($t['abs']) . '"');
    nasp_send_file($t['abs'], $mime, 600);
}

/* ── 서버에서 바로 가져오기(JPG/PNG/WEBP) ── */
if ($a === 'import') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) nasp_fail(403, '잘못된 요청입니다. 화면을 새로고침한 뒤 다시 시도하세요.');
    session_write_close();
    [, $t] = nasp_target(true);
    $r = photo_ingest($t['abs'], trim((string)($_POST['photo_folder'] ?? '')), [], basename($t['abs']));
    if (!$r['ok']) nasp_fail($r['code'], $r['message']);
    $r['source'] = $t['rel'];
    nasp_json($r);
}

nasp_fail(400, '알 수 없는 요청입니다.');

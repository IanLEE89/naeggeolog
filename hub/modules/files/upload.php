<?php
/**
 * 파일 올리기 · 새 폴더 (주인 전용, 폴더 설정에서 "업로드 허용"을 켠 공유 폴더만)
 * 큰 파일은 브라우저가 조각(최대 8MB)으로 나눠 보내고, 여기서 이어 붙입니다 → PHP 업로드 한도와 상관없이 수 GB 도 가능.
 * 받는 중인 파일은 같은 폴더의 숨김 파일(.hubpart-…)로 쌓이고, 다 받으면 원래 이름으로 바뀝니다. 끊겨도 같은 파일을 다시 올리면 이어서 올라갑니다.
 *   POST action=status  s, p, sub, token            → {bytes}
 *   POST action=chunk   s, p, sub, token, name, size, offset, chunk(파일) → {bytes} 또는 {done, name}
 *   POST action=cancel  s, p, sub, token            → {ok}
 *   POST action=mkdir   s, p, name                  → {ok, name}
 * 모든 요청에 csrf 필요. 응답은 JSON.
 */
require_once dirname(__DIR__, 2) . '/_boot.php';
hub_private_headers();
header('Content-Type: application/json; charset=utf-8');

function up_out(int $code, array $data): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function up_meta_load(string $f): ?array { if (!is_file($f)) return null; $d = json_decode((string)file_get_contents($f), true); return is_array($d) ? $d : null; }
function up_meta_save(string $f, array $d): bool { return @file_put_contents($f, json_encode($d), LOCK_EX) !== false; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') up_out(405, ['error' => 'POST 만 가능합니다.']);
if (!hub_available()) up_out(500, ['error' => 'SQLite 확장이 필요합니다.']);
$u = hub_current_user();
if (!$u) up_out(401, ['error' => '로그인이 끊어졌습니다. 다시 로그인해 주세요.']);
if (!hub_is_owner($u)) up_out(403, ['error' => '업로드는 주인 계정만 할 수 있습니다.']);
if (!hub_csrf_ok($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''))) up_out(403, ['error' => '페이지가 오래되었습니다. 새로고침 후 다시 시도하세요.']);
session_write_close();   // 올리는 동안 다른 화면이 막히지 않게

$share = hub_can_access($u, (int)($_POST['s'] ?? 0));
if (!$share) up_out(404, ['error' => '폴더를 찾을 수 없습니다.']);
if (!hub_can_upload($u, $share)) up_out(403, ['error' => '이 폴더는 업로드가 꺼져 있습니다. 관리자 → 공유 폴더 → 설정에서 켜 주세요.']);
$base = hub_resolve($share, (string)($_POST['p'] ?? ''));
if (!$base || !is_dir($base['abs'])) up_out(404, ['error' => '올릴 위치를 찾을 수 없습니다.']);

$act = (string)($_POST['action'] ?? '');

/* ── 새 폴더 ── */
if ($act === 'mkdir') {
    $name = hub_clean_name((string)($_POST['name'] ?? ''));
    if ($name === null) up_out(400, ['error' => '쓸 수 없는 폴더 이름입니다.']);
    if (!is_writable($base['abs'])) up_out(403, ['error' => '웹 서버(http)에 이 폴더의 쓰기 권한이 없습니다.']);
    if (file_exists($base['abs'] . '/' . $name)) up_out(409, ['error' => '같은 이름이 이미 있습니다.']);
    if (!@mkdir($base['abs'] . '/' . $name, 0777)) up_out(500, ['error' => '폴더를 만들지 못했습니다.']);
    up_out(200, ['ok' => true, 'name' => $name]);
}

/* ── 올릴 폴더(하위 폴더 포함: 폴더째 올릴 때) ── */
$token = (string)($_POST['token'] ?? '');
if (!preg_match('/^[a-f0-9]{16,64}$/', $token)) up_out(400, ['error' => '잘못된 요청입니다.']);
$sub = (string)($_POST['sub'] ?? '');
$segs = [];
foreach (array_filter(explode('/', str_replace('\\', '/', $sub)), 'strlen') as $sg) {
    $c = hub_clean_name($sg);
    if ($c === null) up_out(400, ['error' => '쓸 수 없는 폴더 이름이 들어 있습니다: ' . $sg]);
    $segs[] = $c;
}
if (count($segs) > 20) up_out(400, ['error' => '폴더가 너무 깊습니다.']);
$dir = $base['abs'] . ($segs ? '/' . implode('/', $segs) : '');
$part = $dir . '/.hubpart-' . $token; $metaF = $part . '.json';

if ($act === 'status') { $m = is_dir($dir) ? up_meta_load($metaF) : null; up_out(200, ['bytes' => $m ? (float)$m['bytes'] : 0]); }
if ($act === 'cancel') { @unlink($part); @unlink($metaF); up_out(200, ['ok' => true]); }
if ($act !== 'chunk') up_out(400, ['error' => '알 수 없는 요청입니다.']);

/* ── 조각 받기 ── */
$name = hub_clean_name((string)($_POST['name'] ?? ''));
if ($name === null) up_out(400, ['error' => '쓸 수 없는 파일 이름입니다.']);
if (hub_upload_ext_blocked($dir, $name)) up_out(400, ['error' => '홈페이지 폴더 안에는 실행 파일(.php 등)을 올릴 수 없습니다.']);
$size = (float)($_POST['size'] ?? -1); $offset = (float)($_POST['offset'] ?? -1);
if ($size < 0 || $offset < 0 || $offset > $size) up_out(400, ['error' => '잘못된 요청입니다.']);
$f = $_FILES['chunk'] ?? null;
if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
    $why = [UPLOAD_ERR_INI_SIZE => '조각이 PHP 업로드 한도보다 큽니다', UPLOAD_ERR_FORM_SIZE => '조각이 너무 큽니다', UPLOAD_ERR_PARTIAL => '전송이 중간에 끊겼습니다', UPLOAD_ERR_NO_TMP_DIR => '서버 임시 폴더가 없습니다', UPLOAD_ERR_CANT_WRITE => '서버 임시 폴더에 쓸 수 없습니다'][$f['error'] ?? 0] ?? '조각을 받지 못했습니다';
    up_out(400, ['error' => $why, 'retry' => ($f['error'] ?? 0) === UPLOAD_ERR_PARTIAL]);
}
$len = (float)$f['size'];

// 하위 폴더 만들기(폴더째 올리기)
if (!is_dir($dir)) {
    $cur = $base['abs'];
    foreach ($segs as $sg) { $cur .= '/' . $sg; if (!is_dir($cur) && !@mkdir($cur, 0777)) up_out(500, ['error' => '폴더를 만들지 못했습니다: ' . $sg]); }
}
if (!is_writable($dir)) up_out(403, ['error' => '웹 서버(http)에 이 폴더의 쓰기 권한이 없습니다. DSM → 공유 폴더 → 권한에서 http 에 읽기/쓰기를 주세요.']);
$real = realpath($dir); $root = rtrim(str_replace('\\', '/', $base['root']), '/');
if ($real === false || strpos(str_replace('\\', '/', $real) . '/', $root . '/') !== 0) up_out(400, ['error' => '잘못된 위치입니다.']);

$lock = @fopen($part . '.lock', 'c');
if ($lock) flock($lock, LOCK_EX);
$unlock = function () use ($lock, $part) { if ($lock) { flock($lock, LOCK_UN); fclose($lock); @unlink($part . '.lock'); } };

$m = up_meta_load($metaF);
if (!$m) {
    if ($offset > 0) { $unlock(); up_out(409, ['bytes' => 0]); }
    // 첫 조각: 공간 확인, 하루 지난 미완성 조각 정리
    $free = @disk_free_space($dir);
    if ($free !== false && $free < $size + 268435456) { $unlock(); up_out(507, ['error' => 'NAS 남은 공간이 부족합니다 (남은 공간 ' . hub_fmt_size((float)$free) . ').']); }
    foreach (glob($dir . '/.hubpart-*.json') ?: [] as $old) { if (filemtime($old) < time() - 86400) { @unlink(substr($old, 0, -5)); @unlink($old); } }
    $m = ['name' => $name, 'size' => $size, 'bytes' => 0, 't' => time()];
    @unlink($part);
}
if ((float)$m['bytes'] !== $offset) { $unlock(); up_out(409, ['bytes' => (float)$m['bytes']]); }   // 이어올리기 위치가 다르면 맞춰 달라고 알려 줌

$out = @fopen($part, $offset > 0 ? 'ab' : 'wb'); $in = @fopen($f['tmp_name'], 'rb');
if (!$out || !$in) { $unlock(); up_out(500, ['error' => '파일을 쓰지 못했습니다.']); }
$copied = stream_copy_to_stream($in, $out);
fclose($in); fclose($out);
if ($copied === false || (float)$copied !== $len) { $unlock(); up_out(500, ['error' => '파일을 쓰는 중 실패했습니다 (공간·권한 확인).', 'retry' => true]); }
$m['bytes'] = (float)$m['bytes'] + $len; $m['t'] = time();

if ($m['bytes'] >= $size) {
    $final = hub_unique_name($dir, $name);
    if (!@rename($part, $dir . '/' . $final)) { $unlock(); up_out(500, ['error' => '마무리하지 못했습니다(이름 바꾸기 실패).']); }
    @unlink($metaF); $unlock();
    $rel = ltrim($base['rel'] . '/' . implode('/', $segs), '/');
    hub_log_download($u, (int)$share['id'], $share['name'] . '/' . ltrim($rel . '/' . $final, '/'), $size, 'up');
    up_out(200, ['done' => true, 'name' => $final, 'renamed' => $final !== $name]);
}
if (!up_meta_save($metaF, $m)) { $unlock(); up_out(500, ['error' => '진행 정보를 저장하지 못했습니다(쓰기 권한 확인).']); }
$unlock();
up_out(200, ['bytes' => $m['bytes']]);

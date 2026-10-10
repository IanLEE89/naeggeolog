<?php
// 내 공간 홈 위젯 데이터 저장/불러오기 (JSON). 로그인한 본인 데이터만 다룹니다.
//   GET            → {"ok":true,"data":{…}}
//   POST (JSON)    → {"key":"todo","value":[…]}  · 헤더 X-CSRF 필수
require_once __DIR__ . '/_desk.php';
hub_private_headers();
if (!hub_available()) desk_json(['ok' => false, 'error' => 'sqlite'], 500);
$u = hub_current_user();
if (!$u) desk_json(['ok' => false, 'error' => 'login'], 401);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    desk_json(['ok' => true, 'data' => (object)desk_load($u)]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') desk_json(['ok' => false, 'error' => 'method'], 405);
if (!hub_csrf_ok($_SERVER['HTTP_X_CSRF'] ?? '')) desk_json(['ok' => false, 'error' => 'csrf'], 403);

$raw = (string)file_get_contents('php://input', false, null, 0, 300000);
$in = json_decode($raw, true);
$key = is_array($in) ? (string)($in['key'] ?? '') : '';
if (!isset(DESK_KEYS[$key]) || !array_key_exists('value', $in)) desk_json(['ok' => false, 'error' => 'key'], 400);
$json = json_encode($in['value'], JSON_UNESCAPED_UNICODE);
if ($json === false || strlen($json) > DESK_KEYS[$key]) desk_json(['ok' => false, 'error' => 'too_large'], 413);
desk_save($u, $key, $json);
desk_json(['ok' => true]);

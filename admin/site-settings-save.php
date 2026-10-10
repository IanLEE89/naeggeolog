<?php
require_once __DIR__.'/auth.php';
require_admin();
require_once __DIR__.'/../site.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: site-settings.php'); exit; }
if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('잘못된 요청입니다.'); }

$keys = ['site_name','hero_image','hero_title','hero_subtitle','profile_photo','profile_nickname','profile_email','profile_intro','footer_copyright'];
$out = [];
foreach ($keys as $key) $out[$key] = trim((string)($_POST[$key] ?? ''));
$out['site_name'] = mb_substr($out['site_name'], 0, 60);
if ($out['site_name'] === '') $out['site_name'] = SITE_TITLE;

// 이미지 값은 비어 있거나, 기존 값 그대로이거나, 업로드 폴더(travel-photos/_site) · assets 안의 경로만 허용
$cur = $TRAVEL_SITE;
foreach (['hero_image', 'profile_photo'] as $k) {
    $v = $out[$k];
    if ($v === '' || $v === ($cur[$k] ?? null)) continue;
    if (!preg_match('~^(travel-photos/_site/[A-Za-z0-9._-]+|assets/[A-Za-z0-9._/-]+)$~', $v) || str_contains($v, '..')) $out[$k] = (string)($cur[$k] ?? '');
}
if ($out['profile_email'] !== '' && !filter_var($out['profile_email'], FILTER_VALIDATE_EMAIL)) {
    header('Location: site-settings.php?error=email'); exit;
}

// Google Maps 키 (선택) — 값이 바뀌었을 때만 private/google-maps.php 를 다시 쓴다
$mapsKey = trim((string)($_POST['maps_key'] ?? ''));
$mapsId  = trim((string)($_POST['maps_id'] ?? ''));
if (($mapsKey !== '' && !preg_match('/^[A-Za-z0-9_-]{20,80}$/', $mapsKey)) || ($mapsId !== '' && !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $mapsId))) {
    header('Location: site-settings.php?error=maps'); exit;
}
$mapsSaveFailed = false;
if (array_key_exists('maps_key', $_POST)) {
    $gm = travel_google_maps_config();
    if ($mapsKey !== $gm['key'] || $mapsId !== $gm['map_id']) {
        $mapsFile = rtrim(PRIVATE_DIR, '/\\') . '/google-maps.php';
        $php = "<?php\n// Google Maps Platform 설정 (관리자 → 홈페이지 설정에서 저장)\n"
             . "return ['api_key' => " . var_export($mapsKey, true) . ", 'map_id' => " . var_export($mapsId, true) . "];\n";
        if (@file_put_contents($mapsFile, $php, LOCK_EX) === false) $mapsSaveFailed = true;
        elseif (function_exists('opcache_invalidate')) @opcache_invalidate($mapsFile, true);
    }
}

if (!travel_write_json(rtrim(PRIVATE_DIR, '/\\') . '/site.json', $out)) {
    http_response_code(500); exit('사이트 설정 파일을 저장할 수 없습니다. private 폴더 쓰기 권한을 확인하세요.');
}
if ($mapsSaveFailed) { header('Location: site-settings.php?error=mapsave'); exit; }

// 더 이상 쓰지 않는 업로드 파일 정리 (방금 올린 것은 120초 보호)
$siteDir = realpath(dirname(__DIR__) . '/travel-photos/_site');
if ($siteDir) {
    $used = array_map('basename', array_filter([$out['hero_image'], $out['profile_photo']]));
    foreach (glob($siteDir . '/*') ?: [] as $f) {
        if (is_file($f) && !in_array(basename($f), $used, true) && time() - (int)@filemtime($f) > 120) @unlink($f);
    }
}
// 대표 이미지에 쓰던 썸네일은 파일명이 바뀌면 자연히 쓰이지 않음(캐시 비우기로 정리)
header('Location: site-settings.php?saved=1'); exit;

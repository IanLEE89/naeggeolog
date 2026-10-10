<?php
// ─────────────────────────────────────────────────────────────
// Naeggeolog 공통 설정
// 서버별 설정을 바꾸려면 이 파일을 직접 고치지 말고 config.local.php 를 만드세요.
// (config.local.php 는 업데이트 zip에 포함되지 않으므로 덮어써지지 않습니다.)
// ─────────────────────────────────────────────────────────────
if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';

// 시간대(예약 공개·날짜 계산에 사용) · 사이트 주소(비워 두면 접속한 주소를 자동 사용. 예: https://example.com)
if (!defined('SITE_TZ'))           define('SITE_TZ', 'Asia/Seoul');
@date_default_timezone_set(SITE_TZ);
if (!defined('SITE_URL'))          define('SITE_URL', '');
if (!defined('TRAVEL_PHOTO_URL'))  define('TRAVEL_PHOTO_URL', '/travel-photos');
if (!defined('ADMIN_DIR'))         define('ADMIN_DIR', __DIR__ . '/admin/data');   // 구버전 호환(예전 위치)
// 비밀 파일(관리자 계정, API 키, 로그인 시도 기록) 보관 폴더.
// 가능하면 웹 루트 밖(예: /volume1/travel_private)으로 지정하세요. → config.local.php 에서 재정의
if (!defined('PRIVATE_DIR'))       define('PRIVATE_DIR', __DIR__ . '/private');
if (!defined('CACHE_DIR'))         define('CACHE_DIR', __DIR__ . '/cache');             // 썸네일·정화된 본문 캐시
// 업로드 사진의 긴 변 최대 픽셀. 0 이면 원본 그대로 저장(EXIF 포함). 기본 3200px, JPEG 재압축 + 위치정보(GPS) 제거
if (!defined('PHOTO_MAX_EDGE'))    define('PHOTO_MAX_EDGE', 3200);
// 사이트 이름: 관리자 → 홈페이지 설정의 "사이트 이름"(private/site.json) → 없으면 기본값
if (!defined('SITE_TITLE')) {
    $__sj = rtrim(PRIVATE_DIR, '/\\') . '/site.json';
    $__sd = is_file($__sj) ? json_decode((string)@file_get_contents($__sj), true) : null;
    $__sn = is_array($__sd) ? ($__sd['site_name'] ?? '') : '';
    define('SITE_TITLE', (is_string($__sn) && trim($__sn) !== '') ? trim($__sn) : 'Naeggeolog');
    unset($__sj, $__sd, $__sn);
}

/**
 * 비밀 파일의 실제 경로.
 * 1) PRIVATE_DIR 에 파일이 있으면 그 경로
 * 2) 없으면 구버전 위치(admin/data/admin.json, data/*) — 관리자에 들어가면 자동으로 private 로 옮겨짐
 * 3) 둘 다 없으면 PRIVATE_DIR 쪽 경로(새로 만들 때 사용)
 */
function travel_private_file(string $name): string {
    $new = rtrim(PRIVATE_DIR, '/\\') . '/' . $name;
    if (is_file($new)) return $new;
    $legacy = [
        'admin.json'      => ADMIN_DIR . '/admin.json',
        'google-maps.php' => __DIR__ . '/data/google-maps.php',
        'site.json'       => __DIR__ . '/data/site.json',      // 예전(8단계 이전) 위치 — 읽기 전용 대체 경로
        'theme.json'      => __DIR__ . '/data/theme.json',
    ];
    if (isset($legacy[$name]) && is_file($legacy[$name])) return $legacy[$name];
    return $new;
}

/** 예전 위치(data/, admin/data/)의 설정 파일을 PRIVATE_DIR 로 복사한다(이미 있으면 건드리지 않음). 복사한 파일 수 반환 */
function travel_migrate_legacy(): int {
    $n = 0; $dir = rtrim(PRIVATE_DIR, '/\\');
    foreach (['admin.json' => ADMIN_DIR . '/admin.json', 'google-maps.php' => __DIR__ . '/data/google-maps.php', 'site.json' => __DIR__ . '/data/site.json', 'theme.json' => __DIR__ . '/data/theme.json'] as $name => $old) {
        if (!is_file($old) || is_file("$dir/$name")) continue;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return $n;
        if (@copy($old, "$dir/$name")) $n++;
    }
    return $n;
}

/** JSON 을 원자적으로 저장하고 성공 여부를 반환 (실패를 조용히 넘기지 않음). */
function travel_write_json(string $file, $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) { @unlink($tmp); return false; }
    // Synology 에서는 rename 이 권한 문제로 실패할 수 있어 copy 로 한 번 더 시도
    if (!@rename($tmp, $file)) {
        $ok = @copy($tmp, $file);
        @unlink($tmp);
        return $ok;
    }
    return true;
}

/**
 * Google Maps 설정 로드. data/google-maps.php 는 아래 두 형식 모두 지원:
 *   define('GOOGLE_MAPS_API_KEY', '...');  [+ define('GOOGLE_MAPS_MAP_ID','...');]
 *   return ['api_key' => '...', 'map_id' => '...'];
 */
function travel_google_maps_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = ['key' => '', 'map_id' => ''];
    $file = travel_private_file('google-maps.php');
    if (is_file($file)) {
        $ret = (function () use ($file) { return require_once $file; })();
        if (is_array($ret)) {
            $cfg['key']    = trim((string)($ret['api_key'] ?? ''));
            $cfg['map_id'] = trim((string)($ret['map_id'] ?? ''));
        }
        if ($cfg['key'] === '' && defined('GOOGLE_MAPS_API_KEY')) $cfg['key'] = trim((string)GOOGLE_MAPS_API_KEY);
        if ($cfg['map_id'] === '' && defined('GOOGLE_MAPS_MAP_ID')) $cfg['map_id'] = trim((string)GOOGLE_MAPS_MAP_ID);
    }
    if ($cfg['key'] === 'PASTE_YOUR_GOOGLE_MAPS_API_KEY_HERE') $cfg['key'] = '';
    return $cfg;
}

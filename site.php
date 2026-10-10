<?php
require_once __DIR__ . '/config.php';
// Public site-wide content/settings loader.
$SITE_DEFAULTS = [
    'site_name' => SITE_TITLE,
    'hero_image' => '',
    'hero_title' => '길 위에서 만난 장면들',
    'hero_subtitle' => '여행의 기록, 사진, 장소를 한 곳에 모았습니다.',
    'profile_photo' => 'assets/logo_icon.png',
    'profile_nickname' => '여행자',
    'profile_email' => '',
    'profile_intro' => '여행에서 만난 장면과 장소를 기록합니다.',
    'footer_copyright' => '© ' . SITE_TITLE . '. All rights reserved.',
];

function travel_site_load(): array {
    global $SITE_DEFAULTS;
    $file = travel_private_file('site.json');
    if (!is_file($file)) return $SITE_DEFAULTS;
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) return $SITE_DEFAULTS;
    $out = $SITE_DEFAULTS;
    foreach ($SITE_DEFAULTS as $key => $default) {
        if (isset($data[$key]) && is_string($data[$key])) $out[$key] = trim($data[$key]);
    }
    return $out;
}

function travel_site_url(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (preg_match('~^(https?:)?//~i', $value) || str_starts_with($value, '/')) return $value;
    return $value;
}

$TRAVEL_SITE = travel_site_load();

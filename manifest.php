<?php
// 웹 앱 매니페스트: 휴대폰 홈 화면에 "앱처럼" 추가할 수 있게 한다.
require_once __DIR__ . '/includes/layout.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
global $TRAVEL_THEME;
echo json_encode([
    'name' => SITE_TITLE, 'short_name' => mb_substr(SITE_TITLE, 0, 12),
    'description' => $TRAVEL_SITE['hero_subtitle'] ?? '',
    'lang' => 'ko', 'dir' => 'ltr', 'start_url' => './', 'scope' => './', 'id' => './', 'display' => 'standalone',
    'background_color' => $TRAVEL_THEME['page_bg'] ?? '#f8f7f3', 'theme_color' => $TRAVEL_THEME['header_bg'] ?? '#f8f7f3',
    'icons' => [
        ['src' => 'assets/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => '사진', 'url' => 'gallery.php'],
        ['name' => '지도', 'url' => 'index.php#map'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

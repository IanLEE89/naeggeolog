<?php
require_once __DIR__ . '/config.php';
// Public-site theme loader. Theme values are stored in private/theme.json.
$THEME_DEFAULTS = [
    'page_bg' => '#f8f7f3',
    'surface' => '#ffffff',
    'surface_alt' => '#f5f4ef',
    'text' => '#292824',
    'muted' => '#77736b',
    'heading' => '#1f1e1b',
    'border' => '#e4e1db',
    'accent' => '#292824',
    'accent_soft' => '#ebe8e1',
    'link' => '#3f4a43',
    'header_bg' => '#f8f7f3',
    'footer_bg' => '#292824',
    'footer_text' => '#e7e5df',
    'hero_overlay' => '#171714',
];

function travel_theme_load(): array {
    global $THEME_DEFAULTS;
    $file = travel_private_file('theme.json');
    if (!is_file($file)) return $THEME_DEFAULTS;
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) return $THEME_DEFAULTS;
    $out = $THEME_DEFAULTS;
    foreach ($THEME_DEFAULTS as $key => $default) {
        if (isset($data[$key]) && is_string($data[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $data[$key])) {
            $out[$key] = $data[$key];
        }
    }
    return $out;
}

function travel_theme_style(?array $theme = null): string {
    // 색상 변수만 출력합니다. 실제 적용은 assets/app.css 의 의미 기반 색상(bg-surface, text-muted …)이 담당합니다.
    $t = $theme ?: travel_theme_load();
    $vars = '';
    foreach ($t as $key => $value) $vars .= '--theme-' . $key . ':' . $value . ';';
    $vars .= '--theme-accent-base:' . ($t['accent'] ?? '#292824') . ';';   // 다크 모드에서 포인트 색을 계산하는 기준값
    return '<style id="travel-theme-vars">:root{' . $vars . '}</style>';
}

$TRAVEL_THEME = travel_theme_load();

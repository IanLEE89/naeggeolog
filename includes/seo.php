<?php
/** 검색엔진·구독용 공통 도우미: 사이트 주소, XML 출력 */
require_once __DIR__ . '/data.php';

/** 사이트 기준 주소(끝 슬래시 없음). SITE_URL 이 있으면 그것을, 없으면 접속한 주소(루트 스크립트 기준)를 사용 */
function site_base_url(): string {
    if (SITE_URL !== '') return rtrim(SITE_URL, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}
function site_url(string $rel = ''): string { return site_base_url() . '/' . ltrim($rel, '/'); }
function xml_e($v): string { return htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function trip_url(array $t): string { return site_url('trip.php?id=' . rawurlencode((string)$t['id'])); }
/** 날짜(Y-m-d) → RFC 2822 (RSS pubDate). 시각은 정오로 고정 */
function rss_date(string $d): string { $ts = strtotime($d . ' 12:00:00'); return date(DATE_RSS, $ts ?: time()); }

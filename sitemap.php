<?php
// /sitemap.xml — 공개 여행·나라·(있다면) 갤러리/태그 페이지. 숨김·초안·예약 대기 여행은 제외된다.
require_once __DIR__ . '/includes/seo.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$trips = load_trips();
$u = [];
$add = function (string $loc, string $freq = 'monthly', string $prio = '0.5', string $mod = '') use (&$u) { $u[] = [$loc, $freq, $prio, $mod]; };
$add(site_url(), 'weekly', '1.0', $trips ? (string)$trips[0]['date'] : '');
foreach (countries_from_trips($trips) as $c) $add(site_url('country.php?country=' . rawurlencode($c)), 'weekly', '0.7');
foreach ($trips as $t) $add(trip_url($t), 'yearly', '0.8', (string)$t['date']);
if (is_file(__DIR__ . '/gallery.php')) $add(site_url('gallery.php'), 'weekly', '0.6');
foreach (array_keys(trip_tag_counts($trips)) as $tag) if (is_file(__DIR__ . '/tag.php')) $add(site_url('tag.php?tag=' . rawurlencode($tag)), 'weekly', '0.4');
$add(site_url('expenses.php'), 'monthly', '0.3');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($u as [$loc, $freq, $prio, $mod]) {
    echo '  <url><loc>' . xml_e($loc) . '</loc>' . ($mod !== '' ? '<lastmod>' . xml_e($mod) . '</lastmod>' : '') . '<changefreq>' . $freq . '</changefreq><priority>' . $prio . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";

<?php
// /robots.txt — 관리자·비공개 폴더는 색인 제외, 사이트맵 위치 안내
require_once __DIR__ . '/includes/seo.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
echo "User-agent: *\n";
foreach (['admin/', 'hub/', 'data/', 'private/'] as $p) echo "Disallow: $base/$p\n";
echo "Allow: /\n\n";
echo 'Sitemap: ' . site_url('sitemap.php') . "\n";

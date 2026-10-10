<?php
// /feed.xml — RSS 2.0 (최근 공개 여행 30편). 구독 앱(Feedly 등)에서 새 여행기를 받아볼 수 있다.
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/seo.php';
header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=900');
$trips = array_slice(load_trips(), 0, 30);
$self = site_url('feed.php');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
  <title><?=xml_e(SITE_TITLE)?></title>
  <link><?=xml_e(site_url())?></link>
  <description><?=xml_e($TRAVEL_SITE['hero_subtitle'] ?? '')?></description>
  <language>ko</language>
  <atom:link href="<?=xml_e($self)?>" rel="self" type="application/rss+xml"/>
<?php if ($trips): ?>  <lastBuildDate><?=rss_date((string)$trips[0]['date'])?></lastBuildDate>
<?php endif; ?>
<?php foreach ($trips as $t):
    $img = $t['cover'] !== '' ? abs_url(photo_thumb_url((string)$t['cover'], 960)) : '';
    $desc = $t['summary'] !== '' ? $t['summary'] : trim($t['country'] . ' ' . $t['location'] . ' 여행 기록');
    $html = ($img !== '' ? '<p><img src="' . e($img) . '" alt="' . e($t['title']) . '"></p>' : '') . '<p>' . e($desc) . '</p>';
?>
  <item>
    <title><?=xml_e($t['title'])?></title>
    <link><?=xml_e(trip_url($t))?></link>
    <guid isPermaLink="true"><?=xml_e(trip_url($t))?></guid>
    <pubDate><?=rss_date((string)$t['date'])?></pubDate>
<?php if ($t['country'] !== ''): ?>    <category><?=xml_e($t['country'])?></category>
<?php endif; foreach ((array)$t['tags'] as $tg): ?>    <category><?=xml_e($tg)?></category>
<?php endforeach; ?>    <description><?=xml_e($desc)?></description>
    <content:encoded><![CDATA[<?=str_replace(']]>', ']]]]><![CDATA[>', $html)?>]]></content:encoded>
<?php if ($img !== ''): ?>    <media:content url="<?=xml_e($img)?>" medium="image"/>
<?php endif; ?>  </item>
<?php endforeach; ?>
</channel>
</rss>

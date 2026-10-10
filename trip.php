<?php
require_once __DIR__ . '/includes/trip-render.php';
$trip = get_trip((string)($_GET['id'] ?? ''));
$tState = $trip ? trip_state($trip) : '';
$isHidden = $trip && $tState !== 'public';
if (!$trip || ($isHidden && !is_admin_session())) { http_response_code(404); header('X-Robots-Tag: noindex'); echo '여행기를 찾을 수 없습니다.'; exit; }

if (!$isHidden) { require_once __DIR__ . '/includes/stats.php'; stats_record((string)$trip['id']); }   // 공개 여행의 방문 집계(봇·관리자 제외)
$current_country = $trip['country'] ?? '';
$current_id = $trip['id'];
$desc = ($trip['summary'] ?? '') !== '' ? $trip['summary'] : trim(($trip['country'] ?? '') . ' ' . ($trip['location'] ?? '') . ' 여행 기록');
page_head([
    'title'    => $trip['title'] . ' · ' . SITE_TITLE,
    'og_title' => $trip['title'],
    'desc'     => $desc,
    'og_image' => !empty($trip['cover']) ? abs_url(photo_thumb_url((string)$trip['cover'], 1600)) : '',
    'robots'   => $isHidden ? 'noindex,nofollow' : '',
    'og_type'  => 'article',
    'canonical' => 'trip.php?id=' . rawurlencode((string)$trip['id']),
    'jsonld'   => ['@context' => 'https://schema.org', '@graph' => array_values(array_filter([
        ['@type' => 'BlogPosting', 'headline' => $trip['title'], 'description' => $desc, 'inLanguage' => 'ko',
         'datePublished' => $trip['date'] ?? '', 'dateModified' => $trip['date'] ?? '',
         'mainEntityOfPage' => abs_url('trip.php?id=' . rawurlencode((string)$trip['id'])),
         'image' => !empty($trip['cover']) ? [abs_url(photo_thumb_url((string)$trip['cover'], 1600))] : null,
         'author' => ['@type' => 'Person', 'name' => $TRAVEL_SITE['profile_nickname'] ?? SITE_TITLE],
         'publisher' => ['@type' => 'Organization', 'name' => SITE_TITLE],
         'keywords' => !empty($trip['tags']) ? implode(', ', $trip['tags']) : null,
         'contentLocation' => (($trip['location'] ?? '') !== '') ? array_filter(['@type' => 'Place', 'name' => $trip['location'], 'geo' => (is_numeric($trip['lat'] ?? null) && is_numeric($trip['lng'] ?? null)) ? ['@type' => 'GeoCoordinates', 'latitude' => (float)$trip['lat'], 'longitude' => (float)$trip['lng']] : null]) : null],
        ['@type' => 'BreadcrumbList', 'itemListElement' => array_values(array_filter([
            ['@type' => 'ListItem', 'position' => 1, 'name' => SITE_TITLE, 'item' => abs_url('')],
            ($trip['country'] ?? '') !== '' ? ['@type' => 'ListItem', 'position' => 2, 'name' => $trip['country'], 'item' => abs_url('country.php?country=' . rawurlencode($trip['country']))] : null,
            ['@type' => 'ListItem', 'position' => 3, 'name' => $trip['title']],
        ]))],
    ], fn($x) => $x !== null))],
    'expect'   => !empty($trip['cover']) ? '#tvCover' : '',
]);
include __DIR__ . '/includes/header.php';
echo '<main id="main">';
if ($isHidden) {
    $msg = ['hidden' => '🙈 이 여행은 <b>숨김</b> 상태입니다.', 'draft' => '📝 이 여행은 <b>초안</b>(비공개) 상태입니다.', 'scheduled' => '⏰ 이 여행은 <b>' . e((string)$trip['publish_at']) . '</b>에 공개될 예정입니다.'][$tState] ?? '';
    echo '<div class="mx-auto mt-4 max-w-3xl rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-[13px] text-amber-800">' . $msg . ' 관리자에게만 보이며 검색·목록·지도·사이트맵에는 나타나지 않습니다.</div>';
}
trip_render_body($trip);
echo '</main>';
if (is_admin_session()): ?>
<a href="admin/trip-edit.php?id=<?=rawurlencode($trip['id'])?>" class="fixed bottom-5 left-5 z-[1400] inline-flex items-center gap-2 rounded-full bg-accent px-4 py-2.5 text-[13px] font-medium text-white shadow-lg transition hover:brightness-110" title="관리자 전용: 이 글 수정"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>이 글 수정</a>
<?php endif;
$__rel = trip_related(load_trips(), $trip, 3);
if ($__rel): ?>
<section class="tv-wrap tv-related" aria-labelledby="relTitle">
  <div class="tv-sec-head"><div><p class="tv-eyebrow">RELATED</p><h2 id="relTitle" class="tv-h2" style="font-size:clamp(24px,3vw,32px)">비슷한 여행</h2></div></div>
  <div class="tv-grid"><?php foreach ($__rel as $__t) render_trip_card($__t, ['country' => true]); ?></div>
</section>
<?php endif;
render_trip_pager(trip_neighbors(load_trips(), (string)$trip['id']));
include __DIR__ . '/footer.php';
page_end(['maps' => (bool)trip_route_points($trip)]);

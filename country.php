<?php
require_once __DIR__ . '/includes/layout.php';
$trips = load_trips();
$country = (string)($_GET['country'] ?? '');
$countryTrips = trips_by_country($trips, $country);
if ($country === '' || !$countryTrips) { http_response_code(404); echo '국가를 찾을 수 없습니다.'; exit; }

$current_country = $country;
page_head(['title' => $country . ' 여행 · ' . SITE_TITLE, 'desc' => $country . ' 여행 기록 ' . count($countryTrips) . '편', 'expect' => '#tripGrid', 'canonical' => 'country.php?country=' . rawurlencode($country),
    'jsonld' => ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $country . ' 여행', 'url' => abs_url('country.php?country=' . rawurlencode($country)),
        'isPartOf' => ['@type' => 'WebSite', 'name' => SITE_TITLE, 'url' => abs_url('')]]]);
include __DIR__ . '/includes/header.php';
?>
<main id="main" class="tv-wrap tv-page-top pb-[88px]">
  <header class="pb-10 pt-4">
    <p class="tv-eyebrow">COUNTRY ARCHIVE</p>
    <h1 class="tv-h1 my-2 text-[clamp(40px,5.5vw,62px)]"><?=e($country)?></h1>
    <p class="text-muted"><?=count($countryTrips)?>개의 여행 기록</p>
  </header>
  <div id="tripGrid" class="tv-grid">
    <?php foreach ($countryTrips as $t) render_trip_card($t); ?>
  </div>
</main>
<?php
include __DIR__ . '/footer.php';
page_end();

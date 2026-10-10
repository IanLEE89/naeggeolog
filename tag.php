<?php
// 태그별 여행 모아보기
require_once __DIR__ . '/includes/layout.php';
$trips = load_trips();
$tag = trim((string)($_GET['tag'] ?? ''));
$tagTrips = $tag !== '' ? trips_by_tag($trips, $tag) : [];
if (!$tagTrips) { http_response_code(404); echo '태그를 찾을 수 없습니다.'; exit; }
// 표시용 이름(대소문자 원형)
foreach ($tagTrips[0]['tags'] as $__g) if (mb_strtolower($__g) === mb_strtolower($tag)) { $tag = $__g; break; }
$allTags = trip_tag_counts($trips);

page_head(['title' => '#' . $tag . ' · ' . SITE_TITLE, 'desc' => '#' . $tag . ' 태그의 여행 기록 ' . count($tagTrips) . '편', 'expect' => '#tripGrid', 'canonical' => 'tag.php?tag=' . rawurlencode($tag)]);
include __DIR__ . '/includes/header.php';
?>
<main id="main" class="tv-wrap tv-page-top pb-[88px]">
  <header class="pb-8 pt-4">
    <p class="tv-eyebrow">TAG</p>
    <h1 class="tv-h1 my-2 text-[clamp(40px,5.5vw,62px)]">#<?=e($tag)?></h1>
    <p class="text-muted"><?=count($tagTrips)?>개의 여행 기록</p>
    <div class="tv-tags mt-5"><?php foreach (array_slice($allTags, 0, 24, true) as $g => $n): ?><a href="tag.php?tag=<?=rawurlencode($g)?>" class="<?=mb_strtolower($g) === mb_strtolower($tag) ? 'is-on' : ''?>">#<?=e($g)?> <small><?=$n?></small></a><?php endforeach; ?></div>
  </header>
  <div id="tripGrid" class="tv-grid">
    <?php foreach ($tagTrips as $t) render_trip_card($t, ['country' => true]); ?>
  </div>
</main>
<?php
include __DIR__ . '/footer.php';
page_end();

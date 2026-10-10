<?php
require_once __DIR__ . '/includes/layout.php';
$trips = load_trips();                       // 가벼운 목록(본문 제외), 날짜 내림차순
$countries = countries_from_trips($trips);

// 검색(?q=): 서버에서 본문 전체를 대상으로 필터링. 지도·통계·나라 타일은 항상 전체 기준
$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) > 100) $q = mb_substr($q, 0, 100);
if ($q === '') { $listTrips = $trips; }
else { $hit = array_flip(trip_search_ids($q)); $listTrips = array_values(array_filter($trips, fn($t) => isset($hit[$t['id']]))); }

// 히어로 이미지: 관리자 설정 → 없으면 최신 여행 커버
$heroRaw = trim((string)($TRAVEL_SITE['hero_image'] ?? ''));
$heroRel = '';
if ($heroRaw !== '' && str_starts_with(ltrim($heroRaw, '/'), 'travel-photos/')) { $heroRel = substr(ltrim($heroRaw, '/'), strlen('travel-photos/')); $heroRaw = ''; }
$latest = $trips[0] ?? null;
if ($heroRaw === '' && $heroRel === '' && $latest) { foreach ($trips as $ht) { if ($ht['cover'] !== '') { $heroRel = $ht['cover']; break; } } }
$heroUrl = $heroRel !== '' ? photo_thumb_url($heroRel, 1600) : travel_site_url($heroRaw);

// 통계 / 나라 타일 / 연도
$photoCount = array_sum(array_map(fn($t) => (int)($t['image_count'] ?? 0), $trips));
$tiles = [];
foreach ($countries as $c) {
    $list = trips_by_country($trips, $c); $cov = '';
    foreach ($list as $lt) { if ($lt['cover'] !== '') { $cov = $lt['cover']; break; } }
    $tiles[] = ['name' => $c, 'count' => count($list), 'cover' => $cov, 'last' => $list[0]['date'] ?? '', 'photos' => array_sum(array_map(fn($x) => (int)$x['image_count'], $list))];
}
$years = array_values(array_unique(array_filter(array_map(fn($t) => substr((string)$t['date'], 0, 4), $trips))));
rsort($years);
// 홈 '최근 여행': 관리자가 📌 고정한 여행을 앞에 둔다
$recent = array_slice(array_merge(array_values(array_filter($trips, fn($t) => !empty($t['pinned']))), array_values(array_filter($trips, fn($t) => empty($t['pinned'])))), 0, 8);
$mapTrips = [];
foreach ($trips as $t) {
    if ($t['lat'] === '' || $t['lng'] === '') continue;
    $mapTrips[] = ['id'=>$t['id'], 'title'=>$t['title'], 'date'=>$t['date'], 'country'=>$t['country'], 'location'=>$t['location'], 'lat'=>(float)$t['lat'], 'lng'=>(float)$t['lng']];
}

require_once __DIR__ . '/includes/stats.php'; stats_record('');   // 사이트 방문 집계(봇·관리자 제외)
$header_overlay = true;                       // 헤더가 히어로 위에 투명하게 겹침
page_head(['title' => SITE_TITLE, 'desc' => $TRAVEL_SITE['hero_subtitle'], 'og_image' => $heroUrl !== '' ? abs_url($heroUrl) : '', 'expect' => '#hero', 'canonical' => '',
    'jsonld' => ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => SITE_TITLE, 'url' => abs_url(''), 'description' => $TRAVEL_SITE['hero_subtitle'], 'inLanguage' => 'ko',
        'potentialAction' => ['@type' => 'SearchAction', 'target' => abs_url('index.php') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string']]]);
include __DIR__ . '/includes/header.php';
$arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
?>
<main id="main">

<!-- ① HERO ─────────────────────────────────────────────── -->
<section id="hero" class="tv-hero" aria-label="소개" data-hero>
  <div class="tv-hero-media" aria-hidden="true">
    <?php if ($heroRel !== ''): ?><?=photo_img($heroRel, '', ['w' => 1600, 'widths' => [960, 1600, 2400], 'sizes' => '100vw', 'eager' => true, 'nodim' => true, 'class' => 'tv-hero-img'])?>
    <?php elseif ($heroUrl !== ''): ?><img class="tv-hero-img" src="<?=e($heroUrl)?>" alt="" fetchpriority="high" decoding="async">
    <?php endif; ?>
  </div>
  <div class="tv-hero-scrim" aria-hidden="true"></div>
  <div class="tv-wrap tv-hero-inner">
    <p class="tv-hero-eyebrow">PERSONAL TRAVEL ARCHIVE</p>
    <h1 class="tv-hero-title"><?=e($TRAVEL_SITE['hero_title'])?></h1>
    <p class="tv-hero-sub"><?=nl2br(e($TRAVEL_SITE['hero_subtitle']))?></p>
    <div class="tv-hero-cta">
      <a class="tv-cta tv-cta-solid" href="#journeys">여행 둘러보기 <?=$arrow?></a>
      <a class="tv-cta tv-cta-glass" href="#map">지도로 보기</a>
    </div>
    <dl class="tv-stats">
      <div><dt>Journeys</dt><dd data-count="<?=count($trips)?>"><?=count($trips)?></dd></div>
      <div><dt>Countries</dt><dd data-count="<?=count($countries)?>"><?=count($countries)?></dd></div>
      <div><dt>Photos</dt><dd data-count="<?=$photoCount?>"><?=$photoCount?></dd></div>
    </dl>
  </div>
  <?php if ($latest): ?>
  <a class="tv-hero-latest" href="trip.php?id=<?=rawurlencode($latest['id'])?>">
    <span class="tv-hero-latest-tag">LATEST</span>
    <span class="min-w-0"><b><?=e($latest['title'])?></b><small><?=e(trim($latest['date'] . ' · ' . $latest['country'], ' ·'))?></small></span>
    <?=$arrow?>
  </a>
  <?php endif; ?>
  <a class="tv-scroll-cue" href="#journeys" aria-label="아래로 스크롤"><span></span></a>
</section>

<?php if (!$trips): ?>
<section class="tv-wrap tv-section"><div class="tv-empty">아직 등록된 여행이 없습니다.<br><span>관리자에서 첫 여행 기록을 작성해 보세요.</span></div></section>
<?php else: ?>

<!-- ①-b 여행한 장소: 오른쪽 → 왼쪽으로 흐르는 두 줄의 장소 버튼 ───────── -->
<?php
require_once __DIR__ . '/includes/gallery.php';
$__pi = array_slice(places_index(), 0, 60);
if (count($__pi) >= 6):
    $__r = [[], []]; foreach ($__pi as $__i => $__p) $__r[$__i % 2][] = $__p;
    foreach ($__r as &$__row) { while (count($__row) < 8) $__row = array_merge($__row, $__row); } unset($__row);   // 줄이 짧아도 끊김 없이 이어지도록 채움
    $__chip = fn($p) => '<a href="trip.php?id=' . rawurlencode($p['id']) . '#' . e($p['a']) . '" title="' . e($p['t']) . '">#' . e($p['n']) . '</a>';
?>
<section class="tv-places-band" aria-label="여행한 장소">
  <?php foreach ($__r as $__k => $__row): $__h = implode('', array_map($__chip, $__row)); ?>
  <div class="tv-marq" style="--dur:<?=max(40, count($__row) * (3 + $__k))?>s">
    <div class="tv-marq-track">
      <div class="tv-marq-set"><?=$__h?></div>
      <div class="tv-marq-set" aria-hidden="true"><?=str_replace('<a ', '<a tabindex="-1" ', $__h)?></div>
    </div>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ② 최근 여행 (가로 스크롤) ─────────────────────────── -->
<section id="journeys" class="tv-section tv-section-first" aria-labelledby="journeysTitle">
  <div class="tv-wrap tv-sec-head tv-reveal">
    <div><p class="tv-eyebrow">RECENT JOURNEYS</p><h2 id="journeysTitle" class="tv-h2">최근 여행</h2></div>
    <div class="tv-rail-ctl">
      <button type="button" class="tv-round-btn" data-rail="-1" aria-label="이전 여행들"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg></button>
      <button type="button" class="tv-round-btn" data-rail="1" aria-label="다음 여행들"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
    </div>
  </div>
  <div class="tv-rail" id="recentRail" tabindex="0" role="region" aria-label="최근 여행 목록 (좌우로 스크롤)">
    <?php foreach ($recent as $t) render_trip_card($t, ['variant' => 'poster', 'country' => true]); ?>
  </div>
</section>

<!-- ③ 나라별 ─────────────────────────────────────────── -->
<section id="countries" class="tv-section" aria-labelledby="countriesTitle">
  <div class="tv-wrap">
    <div class="tv-sec-head tv-reveal"><div><p class="tv-eyebrow">DESTINATIONS</p><h2 id="countriesTitle" class="tv-h2">나라별 여행</h2></div><p class="tv-sec-note"><?=count($countries)?>개 나라 · <?=count($trips)?>편의 기록</p></div>
    <div class="tv-tiles">
      <?php foreach ($tiles as $tile): ?>
      <a class="tv-tile tv-reveal" href="country.php?country=<?=rawurlencode($tile['name'])?>">
        <?php if ($tile['cover'] !== ''): ?><?=photo_img($tile['cover'], '', ['w' => 960, 'widths' => [480, 960], 'sizes' => '(max-width:640px) 100vw, (max-width:1000px) 50vw, 380px', 'nodim' => true])?><?php endif; ?>
        <span class="tv-tile-shade"></span>
        <span class="tv-tile-body"><b><?=e($tile['name'])?></b><small><?=$tile['count']?>편 · <?=number_format($tile['photos'])?> photos</small></span>
        <span class="tv-tile-go"><?=$arrow?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
// 많이 본 여행 (최근 90일 방문 집계가 충분히 쌓인 뒤에만 표시)
require_once __DIR__ . '/includes/stats.php';
$__pop = [];
if (($__ids = stats_popular_ids(4))) { $__by = []; foreach ($trips as $__t) $__by[$__t['id']] = $__t; foreach ($__ids as $__i) if (isset($__by[$__i])) $__pop[] = $__by[$__i]; }
if (count($__pop) >= 3): ?>
<section id="popular" class="tv-section" aria-labelledby="popularTitle">
  <div class="tv-wrap">
    <div class="tv-sec-head tv-reveal"><div><p class="tv-eyebrow">MOST VIEWED</p><h2 id="popularTitle" class="tv-h2">많이 본 여행</h2></div><p class="tv-sec-note">최근 3개월 기준</p></div>
    <div class="tv-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))"><?php foreach ($__pop as $__t) render_trip_card($__t, ['country' => true]); ?></div>
  </div>
</section>
<?php endif; ?>

<!-- ④ 지도 ───────────────────────────────────────────── -->
<section id="map" class="tv-section" aria-labelledby="mapTitle">
  <div class="tv-wrap">
    <div class="tv-sec-head tv-reveal"><div><p class="tv-eyebrow">MAP</p><h2 id="mapTitle" class="tv-h2">여행 지도</h2></div><p class="tv-sec-note">핀을 누르면 여행기로 이동합니다.</p></div>
    <div class="tv-map-frame tv-reveal"><div id="worldMap" role="region" aria-label="여행 지도"></div></div>
  </div>
</section>

<!-- ⑤ 전체 기록 ─────────────────────────────────────── -->
<section id="archive" class="tv-section tv-section-last" aria-labelledby="archiveTitle">
  <div class="tv-wrap">
    <div class="tv-sec-head tv-reveal">
      <div><p class="tv-eyebrow">ARCHIVE</p><h2 id="archiveTitle" class="tv-h2">모든 기록</h2></div>
      <p id="searchResultCount" data-query="<?=e($q)?>" class="tv-sec-note" aria-live="polite"><?=$q !== '' ? '“' . e($q) . '” 검색 결과 ' : ''?><?=count($listTrips)?>개의 여행</p>
    </div>

    <div class="tv-toolbar">
      <form action="index.php#archive" method="get" role="search" class="tv-inline-search">
        <label class="sr-only" for="tripSearch">여행 검색</label>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg>
        <input id="tripSearch" name="q" value="<?=e($q)?>" type="search" autocomplete="off" placeholder="제목 · 장소 · 날짜 · 본문 검색">
        <?php if ($q !== ''): ?><a class="tv-clear" href="index.php#archive" aria-label="검색 초기화">✕</a><?php endif; ?>
      </form>
      <div class="tv-filters" id="countryFilters" aria-label="나라 필터">
        <button type="button" class="tv-pill" aria-pressed="true" data-country="all">전체</button>
        <?php foreach (countries_from_trips($listTrips) as $c): ?><button type="button" class="tv-pill" aria-pressed="false" data-country="<?=e($c)?>"><?=e($c)?></button><?php endforeach; ?>
      </div>
      <div class="tv-filters tv-filters-end">
        <?php if (count($years) > 1): ?>
        <label class="tv-select"><span class="sr-only">연도</span>
          <select id="yearFilter"><option value="all">모든 연도</option><?php foreach ($years as $y): ?><option value="<?=e($y)?>"><?=e($y)?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <button type="button" class="tv-pill" id="sortToggle" data-sort="desc" aria-label="정렬 방향 바꾸기">최신순 ↓</button>
      </div>
    </div>

    <?php $__tc = trip_tag_counts($trips); if ($__tc): ?>
    <div class="tv-tags mb-7" aria-label="태그"><?php foreach (array_slice($__tc, 0, 14, true) as $__g => $__n): ?><a href="tag.php?tag=<?=rawurlencode((string)$__g)?>">#<?=e($__g)?> <small><?=$__n?></small></a><?php endforeach; ?></div>
    <?php endif; ?>
    <div class="tv-grid" id="tripGrid">
      <?php foreach ($listTrips as $t) render_trip_card($t, ['country' => true]); ?>
    </div>
    <div id="tripEmpty" <?=$listTrips ? 'hidden' : ''?> class="tv-empty">검색 조건에 맞는 여행 기록이 없습니다.<br><span><a class="underline" href="index.php#archive">필터 초기화</a></span></div>
  </div>
</section>
<?php endif; ?>

</main>

<?php include __DIR__ . '/footer.php'; ?>
<script>window.TRIPS=<?=json_encode($mapTrips, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;</script>
<?php page_end(['maps' => (bool)$mapTrips]);

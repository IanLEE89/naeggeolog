<?php
// 사진 모아보기 — 국가·연도별로 모든 여행의 사진을 한곳에서. 스크롤하면 이어서 불러오고, 사진을 누르면 라이트박스로 열립니다.
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/gallery.php';
const GAL_PER = 48;

$all = gallery_items();
$country = (string)($_GET['country'] ?? ''); $year = preg_replace('/\D/', '', (string)($_GET['year'] ?? ''));
$countries = []; $years = [];
foreach ($all as $it) { if ($it['country'] !== '') $countries[$it['country']] = ($countries[$it['country']] ?? 0) + 1; if ($it['year'] !== '') $years[$it['year']] = true; }
ksort($countries); $years = array_keys($years); rsort($years);
$list = array_values(array_filter($all, fn($it) => ($country === '' || $it['country'] === $country) && ($year === '' || $it['year'] === $year)));
$total = count($list);
$page = max(1, (int)($_GET['page'] ?? 1));
$slice = array_slice($list, ($page - 1) * GAL_PER, GAL_PER);
$more = $page * GAL_PER < $total;
$prevId = $page > 1 ? ($list[($page - 1) * GAL_PER - 1]['id'] ?? null) : null;
$q = fn(array $o) => 'gallery.php' . (($p = array_filter(array_merge(['country' => $country, 'year' => $year], $o), fn($v) => $v !== '' && $v !== null && $v !== 1)) ? '?' . http_build_query($p) : '');

if (!empty($_GET['frag'])) {                      // "더 보기" 조각 요청
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: private, max-age=60');
    echo gallery_render_chunk($slice, $prevId);
    echo $more ? '<template data-next="' . e($q(['page' => $page + 1, 'frag' => '1'])) . '"></template>' : '';
    exit;
}

page_head(['title' => '사진 · ' . SITE_TITLE, 'desc' => '여행 사진 ' . count($all) . '장을 한곳에서 모아봅니다.', 'canonical' => 'gallery.php', 'robots' => ($country !== '' || $year !== '' || $page > 1) ? 'noindex,follow' : '']);
$current_page = 'gallery';
include __DIR__ . '/includes/header.php';
?>
<main id="main" class="tv-wrap tv-page-top pb-[88px]">
  <header class="pb-8 pt-4">
    <p class="tv-eyebrow">PHOTO ARCHIVE</p>
    <h1 class="tv-h1 my-2 text-[clamp(40px,5.5vw,62px)]">사진</h1>
    <p class="text-muted"><?=number_format($total)?>장<?=($country !== '' || $year !== '') ? ' · ' . e(trim($country . ' ' . $year)) : ''?></p>
  </header>
  <div class="tv-toolbar">
    <div class="tv-filters" aria-label="나라 필터">
      <a class="tv-pill" href="<?=e($q(['country' => '', 'page' => 1]))?>" aria-pressed="<?=$country === '' ? 'true' : 'false'?>">전체</a>
      <?php foreach ($countries as $c => $n): ?><a class="tv-pill" href="<?=e($q(['country' => $c, 'page' => 1]))?>" aria-pressed="<?=$country === $c ? 'true' : 'false'?>"><?=e($c)?> <small style="opacity:.6"><?=$n?></small></a><?php endforeach; ?>
    </div>
    <?php if (count($years) > 1): ?>
    <div class="tv-filters tv-filters-end" aria-label="연도 필터">
      <a class="tv-pill" href="<?=e($q(['year' => '', 'page' => 1]))?>" aria-pressed="<?=$year === '' ? 'true' : 'false'?>">모든 연도</a>
      <?php foreach ($years as $y): ?><a class="tv-pill" href="<?=e($q(['year' => $y, 'page' => 1]))?>" aria-pressed="<?=$year === (string)$y ? 'true' : 'false'?>"><?=e($y)?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if (!$total): ?>
    <div class="tv-empty">표시할 사진이 없습니다.</div>
  <?php else: ?>
  <div class="tv-gal" id="galGrid" data-gallery><?=gallery_render_chunk($slice, $prevId)?></div>
  <div id="galMore" class="mt-8 text-center" <?=$more ? '' : 'hidden'?>>
    <a id="galMoreBtn" class="tv-pill" href="<?=e($q(['page' => $page + 1]))?>" data-next="<?=e($q(['page' => $page + 1, 'frag' => '1']))?>">사진 더 보기</a>
  </div>
  <?php endif; ?>
</main>
<?php
include __DIR__ . '/footer.php';
page_end();

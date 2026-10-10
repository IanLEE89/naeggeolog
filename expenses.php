<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/expenses.php';

$trips = load_trips_full();
$allCountries = countries_from_trips($trips);
$country = (string)($_GET['country'] ?? '');
if ($country !== '' && !in_array($country, $allCountries, true)) $country = '';
$scope = $country === '' ? $trips : trips_by_country($trips, $country);

// 여행별 집계 (지출이 있는 여행만)
$rows = [];
foreach ($scope as $t) {
    $lines = trip_expense_lines($t); if (!$lines) continue;
    $agg = expense_aggregate($lines);
    $rows[] = ['trip' => $t, 'agg' => $agg];
}
usort($rows, fn($a, $b) => strcmp((string)$b['trip']['date'], (string)$a['trip']['date']));
$lines = []; foreach ($rows as $r) foreach (trip_expense_lines($r['trip']) as $l) $lines[] = $l;
$all = expense_aggregate($lines);
$maxTrip = max(array_merge([1], array_map(fn($r) => $r['agg']['total'], $rows)));

// 국가별
$byCountry = [];
foreach ($rows as $r) { $c = (string)($r['trip']['country'] ?? '기타'); $byCountry[$c] = ($byCountry[$c] ?? 0) + $r['agg']['total']; }
arsort($byCountry);

$current_page = 'expenses';
page_head(['title' => '지출 대시보드 · ' . SITE_TITLE, 'desc' => '여행 지출을 카테고리·여행·국가별로 모아 봅니다.', 'expect' => '#expHead']);
include __DIR__ . '/includes/header.php';
?>
<main id="main" class="tv-wrap tv-page-top pb-[88px]">
  <header id="expHead" class="py-6 pb-10">
    <p class="tv-eyebrow">EXPENSES</p>
    <h1 class="tv-h1 my-2 text-[clamp(38px,5.5vw,60px)]">지출 대시보드</h1>
    <p class="text-muted">여행에서 쓴 돈을 한눈에 봅니다. 외화는 기록 당시 환율로 환산한 원화 기준입니다.</p>
    <?php if (count($allCountries) > 1): ?>
    <div class="mt-6 flex flex-wrap gap-2" aria-label="국가 필터">
      <a class="tv-pill" aria-pressed="<?=$country === '' ? 'true' : 'false'?>" href="expenses.php">전체</a>
      <?php foreach ($allCountries as $c): ?><a class="tv-pill" aria-pressed="<?=$country === $c ? 'true' : 'false'?>" href="expenses.php?country=<?=rawurlencode($c)?>"><?=e($c)?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </header>

<?php if (!$rows || $all['total'] <= 0): ?>
  <div class="rounded-xl border border-dashed border-line bg-surface/50 px-6 py-16 text-center text-muted">
    아직 집계할 지출 기록이 없습니다.<br><span class="text-[13px]">관리자에서 여행 섹션에 지출 내역을 입력하면 여기에 나타납니다.</span>
  </div>
<?php else: ?>
  <section class="grid grid-cols-4 gap-4 max-[900px]:grid-cols-2" aria-label="요약">
    <div class="tv-kpi"><span class="text-xs text-muted">총 지출</span><b><?=won($all['total'])?></b></div>
    <div class="tv-kpi"><span class="text-xs text-muted">지출 기록 여행</span><b><?=count($rows)?>편</b></div>
    <div class="tv-kpi"><span class="text-xs text-muted">여행당 평균</span><b><?=won($all['total'] / max(1, count($rows)))?></b></div>
    <div class="tv-kpi"><span class="text-xs text-muted">기록된 항목</span><b><?=number_format($all['count'])?>건</b></div>
  </section>
  <?php if ($all['unconverted'] > 0): ?><p class="mt-3 text-[12px] text-muted">환율 정보가 없는 <?=$all['unconverted']?>개 항목은 합계에서 제외되었습니다. (관리자에서 날짜를 입력하고 저장하면 환산됩니다)</p><?php endif; ?>

  <div class="mt-6 grid grid-cols-[1.1fr_.9fr] gap-6 max-[900px]:grid-cols-1">
    <section class="tv-panel"><h3>카테고리별</h3><?php render_expense_cats($all); ?></section>

    <section class="tv-panel"><h3>가장 큰 지출</h3>
      <ol class="m-0 list-none space-y-3 p-0 text-[14px]">
        <?php foreach ($all['top'] as $i => $l): ?>
        <li class="flex items-baseline justify-between gap-4">
          <span class="min-w-0"><span class="mr-2 text-[11px] text-muted"><?=sprintf('%02d', $i + 1)?></span><?=e($l['item'])?>
            <a class="ml-1 block pl-6 text-[11px] text-muted underline-offset-2 hover:underline" href="trip.php?id=<?=rawurlencode($l['trip_id'])?>"><?=e($l['trip_title'])?></a></span>
          <span class="shrink-0 font-medium"><?=won($l['krw'])?></span>
        </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <section class="tv-panel mt-6">
    <h3>여행별</h3>
    <ul class="m-0 list-none space-y-5 p-0">
      <?php foreach ($rows as $r): $t = $r['trip']; $a = $r['agg']; ?>
      <li>
        <div class="mb-2 flex items-baseline justify-between gap-4">
          <a class="min-w-0 text-[15px] text-heading hover:underline" href="trip.php?id=<?=rawurlencode($t['id'])?>"><span class="mr-2 text-[12px] text-muted"><?=e($t['date'] ?? '')?></span><?=e($t['title'])?></a>
          <span class="shrink-0 text-[14px] font-medium"><?=won($a['total'])?></span>
        </div>
        <div class="tv-seg" style="width:<?=max(4, round($a['total'] / $maxTrip * 100, 2))?>%" role="img" aria-label="<?=e($t['title'])?> 카테고리 구성">
          <?php foreach ($a['cats'] as $k => $v): ?><span style="width:<?=round($v / max(1, $a['total']) * 100, 2)?>%;background:<?=EXPENSE_CATS[$k][1]?>" title="<?=e(EXPENSE_CATS[$k][0])?> <?=won($v)?>"></span><?php endforeach; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <div class="mt-5 flex flex-wrap gap-x-4 gap-y-1.5 text-[12px] text-muted">
      <?php foreach (EXPENSE_CATS as $k => $c): if (!isset($all['cats'][$k])) continue; ?><span class="flex items-center gap-1.5"><i class="inline-block h-2.5 w-2.5 rounded-full" style="background:<?=$c[1]?>"></i><?=e($c[0])?></span><?php endforeach; ?>
    </div>
  </section>

  <div class="mt-6 grid grid-cols-2 gap-6 max-[900px]:grid-cols-1">
    <?php if (count($byCountry) > 1): ?>
    <section class="tv-panel"><h3>국가별</h3>
      <ul class="m-0 list-none space-y-4 p-0">
        <?php foreach ($byCountry as $c => $v): ?>
        <li><div class="mb-1.5 flex justify-between text-[14px]"><span><?=e($c)?></span><span class="text-muted"><?=won($v)?> <span class="ml-1 text-[11px]"><?=round($v / $all['total'] * 100)?>%</span></span></div>
          <div class="tv-seg"><span style="width:<?=round($v / $all['total'] * 100, 2)?>%;background:var(--theme-accent)"></span></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
    <section class="tv-panel"><h3>통화별 원금액</h3>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($all['cur'] as $c => $v): ?><span class="tv-chip text-[13px]"><?=e($c)?> <?=expense_num($v)?></span><?php endforeach; ?>
      </div>
      <p class="mt-4 text-[12px] leading-6 text-muted">여행지에서 실제로 결제한 통화별 합계입니다.</p>
    </section>
  </div>
<?php endif; ?>
</main>
<?php
include __DIR__ . '/footer.php';
page_end();

<?php
/**
 * 지출 집계 — 여행 상세의 "지출 요약"과 지출 대시보드(expenses.php)가 함께 사용합니다.
 * 환산 규칙은 상세 페이지의 기존 계산과 동일: krw_amount → amount×rate_to_krw → (KRW면 amount) → 미환산
 */
require_once __DIR__ . '/data.php';

// 카테고리: [라벨, 색]
const EXPENSE_CATS = [
    'lodging'   => ['숙박',     '#7a5cc0'],
    'transport' => ['교통',     '#3b82c4'],
    'activity'  => ['입장·체험', '#2f9e7a'],
    'food'      => ['식비',     '#d9822b'],
    'shopping'  => ['쇼핑',     '#cf4f7e'],
    'telecom'   => ['통신',     '#8a8f98'],
    'other'     => ['기타',     '#a8a29e'],
];
// 항목 이름으로 자동 분류(위에서부터 먼저 맞는 것). 편집기에서 직접 지정하면 그 값이 우선.
const EXPENSE_RULES = [
    'lodging'   => '/숙박|호텔|숙소|에어비앤비|airbnb|호스텔|리조트|민박|게스트하우스|체크인|hotel|hostel|resort/iu',
    'transport' => '/교통|버스|지하철|메트로|기차|열차|택시|항공|비행|공항|우버|uber|렌터카|렌트|주유|통행료|톨게이트|트램|페리|패스|철도|승차|metro|bus|taxi|train|flight/iu',
    'activity'  => '/입장|티켓|투어|박물관|미술관|관람|공연|체험|액티비티|성당|궁|전망대|바티칸|콜로세움|사원|케이블카|수족관|테마파크|ticket|museum|tour/iu',
    'telecom'   => '/유심|esim|이심|와이파이|wifi|로밍|통신|sim/iu',
    'shopping'  => '/쇼핑|기념품|선물|마트|면세|옷|가방|구매|화장품|약국|souvenir|shop/iu',
    'food'      => '/식비|식사|아침|점심|저녁|브런치|커피|카페|맛집|레스토랑|식당|피자|파스타|젤라토|맥주|와인|음료|간식|빵|라멘|술|밥|디저트|먹|food|coffee|cafe|dinner|lunch/iu',
];

function expense_category(array $ex): string {
    $c = (string)($ex['category'] ?? '');
    if ($c !== '' && isset(EXPENSE_CATS[$c])) return $c;
    $item = (string)($ex['item'] ?? '');
    foreach (EXPENSE_RULES as $key => $re) if (preg_match($re, $item)) return $key;
    return 'other';
}
/** 원화 환산액. 환산할 수 없으면 null */
function expense_krw(array $ex): ?float {
    $amt = (float)($ex['amount'] ?? 0); $cur = strtoupper((string)($ex['currency'] ?? 'KRW'));
    if (array_key_exists('krw_amount', $ex) && $ex['krw_amount'] !== null) return (float)$ex['krw_amount'];
    if (isset($ex['rate_to_krw']) && $ex['rate_to_krw'] !== null && (float)$ex['rate_to_krw'] > 0) return $amt * (float)$ex['rate_to_krw'];
    if ($cur === 'KRW') return $amt;
    return null;
}

/** 한 여행의 지출 행(평탄화) */
function trip_expense_lines(array $trip): array {
    $out = [];
    foreach (($trip['sections'] ?? []) as $s) {
        foreach ((is_array($s['expenses'] ?? null) ? $s['expenses'] : []) as $ex) {
            if (!is_array($ex)) continue;
            $out[] = [
                'trip_id' => (string)($trip['id'] ?? ''), 'trip_title' => (string)($trip['title'] ?? ''),
                'country' => (string)($trip['country'] ?? ''), 'date' => (string)($trip['date'] ?? ''),
                'section' => (string)($s['title'] ?? ''), 'item' => (string)($ex['item'] ?? ''),
                'cur' => strtoupper((string)($ex['currency'] ?? 'KRW')), 'amount' => (float)($ex['amount'] ?? 0),
                'krw' => expense_krw($ex), 'cat' => expense_category($ex),
            ];
        }
    }
    return $out;
}
function all_expense_lines(array $trips): array {
    $out = []; foreach ($trips as $t) foreach (trip_expense_lines($t) as $l) $out[] = $l; return $out;
}

/** 합계 · 카테고리별 · 통화별 · 상위 지출 */
function expense_aggregate(array $lines): array {
    $r = ['total' => 0.0, 'count' => count($lines), 'unconverted' => 0, 'cats' => [], 'cur' => [], 'top' => []];
    foreach ($lines as $l) {
        $r['cur'][$l['cur']] = ($r['cur'][$l['cur']] ?? 0) + $l['amount'];
        if ($l['krw'] === null) { $r['unconverted']++; continue; }
        $r['total'] += $l['krw'];
        $r['cats'][$l['cat']] = ($r['cats'][$l['cat']] ?? 0) + $l['krw'];
    }
    // 카테고리는 EXPENSE_CATS 순서가 아니라 금액 큰 순
    arsort($r['cats']);
    $conv = array_values(array_filter($lines, fn($l) => $l['krw'] !== null && $l['krw'] > 0));
    usort($conv, fn($a, $b) => $b['krw'] <=> $a['krw']);
    $r['top'] = array_slice($conv, 0, 5);
    return $r;
}
function won($n): string { return '₩' . number_format((float)$n); }
function expense_num($n): string { return rtrim(rtrim(number_format((float)$n, 2, '.', ','), '0'), '.'); }

/** 카테고리 구성 막대 + 범례 (여행 상세 · 대시보드 공용) */
function render_expense_cats(array $agg): void {
    if ($agg['total'] <= 0) return;
    ?>
<div class="flex h-3 w-full overflow-hidden rounded-full bg-line" role="img" aria-label="카테고리별 지출 비율">
  <?php foreach ($agg['cats'] as $k => $v): ?><span style="width:<?=round($v / $agg['total'] * 100, 2)?>%;background:<?=EXPENSE_CATS[$k][1]?>" title="<?=e(EXPENSE_CATS[$k][0])?> <?=won($v)?>"></span><?php endforeach; ?>
</div>
<ul class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2.5 text-[13px] max-[420px]:grid-cols-1">
  <?php foreach ($agg['cats'] as $k => $v): ?>
  <li class="flex items-center justify-between gap-3">
    <span class="flex items-center gap-2 text-ink"><i class="inline-block h-2.5 w-2.5 rounded-full" style="background:<?=EXPENSE_CATS[$k][1]?>"></i><?=e(EXPENSE_CATS[$k][0])?></span>
    <span class="text-muted"><?=won($v)?> <span class="ml-1 text-[11px]"><?=round($v / $agg['total'] * 100)?>%</span></span>
  </li>
  <?php endforeach; ?>
</ul>
<?php
}

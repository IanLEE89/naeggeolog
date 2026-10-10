<?php
/**
 * 여행 상세 렌더링 — trip.php 와 관리자 미리보기(admin/preview.php)가 함께 사용합니다.
 * (예전에는 두 파일에 같은 코드가 복사되어 있어 미리보기가 실제 화면과 어긋났습니다.)
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/sanitize.php';

function trip_num($n) { return number_format((float)$n, 2, '.', ','); }
function trip_basic($trip) {
  $b = is_array($trip['basic_info'] ?? null) ? $trip['basic_info'] : [];
  $b['date'] = $b['date'] ?? ($trip['date'] ?? '');
  $b['weather'] = $b['weather'] ?? ($trip['weather'] ?? '');
  $b['country'] = $b['country'] ?? ($trip['country'] ?? '');
  $b['country_en'] = $b['country_en'] ?? ($trip['country_en'] ?? '');
  if (is_array($b['lodging'] ?? null)) {
    $legacyLodging=$b['lodging'];
    $b['lodging']=$legacyLodging['name'] ?? '';
    if (($b['lodging_url'] ?? '')==='') $b['lodging_url']=$legacyLodging['url'] ?? '';
  } else {
    $b['lodging'] = $b['lodging'] ?? '';
  }
  $b['lodging_url'] = $b['lodging_url'] ?? '';
  $b['transport'] = is_array($b['transport'] ?? null) ? $b['transport'] : [];
  $b['places'] = $b['places'] ?? '';
  return $b;
}
function trip_legacy_subinfo($html) {
  $out = ['location'=>'','location_url'=>'','purpose'=>'','rating'=>0,'features'=>[]];
  $html = (string)$html;
  if ($html === '') return $out;
  if (preg_match('/<li>\s*<strong>장소<\/strong>\s*[:：]\s*(.*?)<\/li>/isu', $html, $m)) {
    $part = $m[1];
    if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/isu', $part, $a)) { $out['location_url'] = html_entity_decode($a[1], ENT_QUOTES, 'UTF-8'); $out['location'] = trim(strip_tags($a[2])); }
    else $out['location'] = trim(strip_tags($part));
  }
  if (preg_match('/<li>\s*<strong>목적<\/strong>\s*[:：]\s*(.*?)<\/li>/isu', $html, $m)) $out['purpose'] = trim(strip_tags($m[1]));
  if (preg_match('/<li>\s*<strong>추천도<\/strong>\s*[:：]\s*(⭐+)/u', $html, $m)) $out['rating'] = min(5, preg_match_all('/⭐/u', $m[1], $tmp));
  if (preg_match('/<li>\s*<strong>특징<\/strong>\s*[:：]\s*(.*?)<\/li>/isu', $html, $m)) {
    preg_match_all('/#[^\s<]+/u', strip_tags($m[1]), $tags);
    $out['features'] = array_values(array_filter(array_map('trim', $tags[0] ?? [])));
  }
  return $out;
}
function trip_subinfo($section) {
  $si = is_array($section['subsection_info'] ?? null) ? $section['subsection_info'] : [];
  $legacy = trip_legacy_subinfo($section['content_html'] ?? '');
  foreach (['location','location_url','purpose'] as $k) if (($si[$k] ?? '') === '' && $legacy[$k] !== '') $si[$k] = $legacy[$k];
  if ((int)($si['rating'] ?? 0) <= 0 && $legacy['rating'] > 0) $si['rating'] = $legacy['rating'];
  if (empty($si['features']) && !empty($legacy['features'])) $si['features'] = $legacy['features'];
  $si['rating'] = max(0, min(5, (int)($si['rating'] ?? 0)));
  $si['features'] = is_array($si['features'] ?? null) ? array_values(array_filter(array_map('strval', $si['features']))) : [];
  return $si;
}
function trip_strip_legacy_meta($html) {
  $html = (string)$html;
  if ($html === '') return '';
  // Older posts stored 장소/목적/추천도/특징 as the first UL. Remove only that metadata list;
  // keep all other links/lists and the actual article body intact.
  $html = preg_replace('/\s*<ul>\s*<li>\s*<strong>장소<\/strong>.*?<\/ul>\s*/isu', '\n', $html, 1);
  return trim((string)$html);
}
function trip_expenses($section) { return is_array($section['expenses'] ?? null) ? $section['expenses'] : []; }
/** 섹션 앵커(목차·지도 연동용). 제목이 없으면 빈 문자열 */
function trip_section_anchor($s) {
  if (!empty($s['anchor'])) return (string)$s['anchor'];
  $t = trim((string)($s['title'] ?? ''));
  return $t === '' ? '' : trim((string)preg_replace('/[^\p{L}\p{N}]+/u', '-', $t), '-');
}
/** 주요 장소: 여행의 각 섹션에 지정한 장소 → [이름, 섹션 앵커]. 상세 페이지의 "주요 장소" 버튼과 홈의 흐르는 장소 띠가 같이 쓴다 */
function trip_section_places(array $trip): array {
    $out = []; $seen = [];
    foreach ((array)($trip['sections'] ?? []) as $i => $s) {
        if ((int)($s['level'] ?? 2) === 1) continue;
        $name = trim((string)(trip_subinfo($s)['location'] ?? ''));
        $k = mb_strtolower($name);
        if ($name === '' || isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = ['name' => $name, 'anchor' => trip_section_anchor($s) ?: 'sec-' . ($i + 1)];
    }
    return $out;
}
function trip_route_points($trip) {
  $points=[];
  foreach (($trip['sections'] ?? []) as $i => $s) {
    if ((int)($s['level'] ?? 2) !== 2) continue;
    $si=trip_subinfo($s);
    $lat=$si['lat']??''; $lng=$si['lng']??'';
    if ($lat==='' || $lng==='' || !is_numeric($lat) || !is_numeric($lng)) continue;
    $points[]=['title'=>(string)($s['title']??''),'location'=>(string)($si['location']??''),'lat'=>(float)$lat,'lng'=>(float)$lng,'url'=>(string)($si['location_url']??''),'anchor'=>trip_section_anchor($s),'si'=>$i];
  }
  return $points;
}

/** 본문 한 섹션의 HTML (레거시 메타 제거 → 정화) */
function trip_section_html(array $s): string {
    $c = (string)($s['content_html'] ?? '');
    if (isset($s['subsection_info'])) $c = trip_strip_legacy_meta($c);
    return html_clean_cached($c);
}

/**
 * 여행 상세 본문을 출력한다 (호출하는 쪽에서 <main id="main"> 로 감싼다).
 * 구조: 헤드(제목·커버·정보·지출요약) / 지도(넓은 화면에서는 오른쪽 고정) / 본문(목차·글)
 */
function trip_render_body(array $trip): void {
    require_once __DIR__ . '/expenses.php';
    $basic = trip_basic($trip);
    $weatherIcons = ['맑음'=>'☀️','눈'=>'❄️','비'=>'🌧️','흐림'=>'☁️','폭풍'=>'⛈️'];
    $routePoints = trip_route_points($trip);
    $routeBySection = [];                       // 섹션 인덱스 → 지도 포인트 번호
    foreach ($routePoints as $pi => $pt) $routeBySection[$pt['si']] = $pi;
    $toc = [];
    foreach (($trip['sections'] ?? []) as $s) {
        if ((int)($s['level'] ?? 2) === 2 && trim((string)($s['title'] ?? '')) !== '')
            $toc[] = ['title'=>$s['title'], 'anchor'=>trip_section_anchor($s)];
    }
    $lines = trip_expense_lines($trip);
    $agg = $lines ? expense_aggregate($lines) : null;
    $row = function($label, $html) { if ($html === '') return; echo '<div class="tv-info-row"><span class="tv-info-label">'.$label.'</span><span class="tv-info-value">'.$html.'</span></div>'; };
    ?>
<div class="tv-progress" aria-hidden="true"></div>
<div class="tv-trip">

<div class="tv-trip-head">
  <header class="pt-[clamp(32px,5vw,58px)] pb-8">
    <p class="tv-eyebrow"><?=e($trip['country'] ?? '')?> · <?=e($trip['location'] ?? '')?></p>
    <h1 class="tv-h1 my-2 mb-3 text-[clamp(34px,5.2vw,58px)] [text-wrap:balance]"><?=e($trip['title'])?></h1>
    <p class="m-0 text-[13px] text-muted"><?=e($trip['date'] ?? '')?> · <?=intval($trip['image_count'] ?? 0)?> photos</p>
    <?php if (!empty($trip['tags'])): ?><div class="tv-tags mt-4"><?php foreach ($trip['tags'] as $__g): ?><a href="tag.php?tag=<?=rawurlencode((string)$__g)?>">#<?=e($__g)?></a><?php endforeach; ?></div><?php endif; ?>
    <div class="tv-actions mt-5" data-no-print>
      <button type="button" data-share data-title="<?=e($trip['title'])?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3M7 8l5-5 5 5M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/></svg>공유</button>
      <button type="button" data-print><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M7 14h10v7H7z"/></svg>인쇄 · PDF</button>
    </div>
    <?php if (!empty($trip['cover'])): ?><div id="tvCover" data-vt-cover class="mt-8 overflow-hidden rounded-[12px]"><?=photo_img((string)$trip['cover'], (string)$trip['title'].' 대표 사진', ['w'=>1600,'widths'=>[960,1600,2400],'sizes'=>'(max-width:860px) 100vw, 820px','eager'=>true,'class'=>'block max-h-[760px] w-full object-cover'])?></div><?php endif; ?>
  </header>

  <section class="pb-5"><div class="tv-info">
<?php
    $row('날짜', e($basic['date'] ?? ''));
    if (($basic['weather'] ?? '') !== '') $row('날씨', (isset($weatherIcons[$basic['weather']]) ? '<span class="tv-emoji" aria-hidden="true">'.$weatherIcons[$basic['weather']].'</span>' : '').e($basic['weather']));
    if (($basic['country'] ?? '') !== '') $row('나라', e($basic['country']).(($basic['country_en'] ?? '')!=='' ? ' · '.e($basic['country_en']) : ''));
    if (($basic['lodging'] ?? '') !== '') $row('숙소', ($basic['lodging_url'] ?? '')!=='' ? '<a href="'.e($basic['lodging_url']).'" target="_blank" rel="noopener">'.e($basic['lodging']).'</a>' : e($basic['lodging']));
    if (!empty($basic['transport'])) $row('이동수단', e(implode(', ', $basic['transport'])));
    $__pl = trip_section_places($trip); $__plHtml = '';
    $__have = [];
    foreach ($__pl as $__p) { $__have[mb_strtolower($__p['name'])] = true; $__plHtml .= '<a href="#' . e($__p['anchor']) . '">#' . e($__p['name']) . '</a>'; }
    foreach (preg_split('/[,，、\/\n]+/u', (string)($basic['places'] ?? '')) as $__t) {      // 직접 적어 둔 주요 장소(섹션과 연결되지 않은 것)는 링크 없이 표시
        $__t = trim(ltrim(trim($__t), '#')); if ($__t !== '' && empty($__have[mb_strtolower($__t)])) $__plHtml .= '<span>#' . e($__t) . '</span>';
    }
    if ($__plHtml !== '') $row('주요 장소', '<span class="tv-tags tv-places">' . $__plHtml . '</span>');
?>
  </div></section>

  <?php if ($agg && $agg['total'] > 0): ?>
  <section class="pb-5" aria-label="지출 요약">
    <div class="tv-panel">
      <div class="mb-4 flex items-end justify-between gap-3">
        <div><div class="text-[11px] font-bold tracking-[.12em] text-muted">지출 요약</div><div class="mt-1 font-serif text-[28px] leading-tight text-heading"><?=won($agg['total'])?></div></div>
        <a class="text-[12px] text-muted underline underline-offset-2 hover:text-ink" href="expenses.php">전체 지출 보기 →</a>
      </div>
      <?php render_expense_cats($agg); ?>
      <?php if ($agg['unconverted'] > 0): ?><p class="mt-3 text-[11px] text-muted">환율 정보가 없는 <?=$agg['unconverted']?>개 항목은 합계에 포함되지 않았습니다.</p><?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<?php if ($routePoints): ?>
<aside class="tv-trip-map" aria-label="이동경로">
  <div class="mb-3 text-xs font-bold tracking-[.14em] text-muted">이동경로</div>
  <div id="tripRouteMap" role="region" aria-label="이동경로 지도" class="tv-route overflow-hidden rounded-[12px] border border-line bg-line" data-points="<?=e(json_encode($routePoints, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>"></div>
  <p class="mt-2 hidden text-[11px] text-muted min-[1180px]:block">글을 읽으며 스크롤하면 지도가 해당 장소로 이동합니다.</p>
  <?php if ($toc): ?>
  <nav class="tv-toc-mini" aria-label="읽기 진행 목차" data-toc-mini>
    <div class="tv-toc-mini-head"><span>목차</span><span data-toc-pos aria-live="off"></span></div>
    <ol>
    <?php foreach ($toc as $i => $it): ?>
      <li><a href="#<?=e($it['anchor'])?>"><span class="tv-toc-dot" aria-hidden="true"></span><span class="tv-toc-t"><?=e($it['title'])?></span></a></li>
    <?php endforeach; ?>
    </ol>
  </nav>
  <?php endif; ?>
</aside>
<?php endif; ?>

<div class="tv-trip-body">
<?php if ($toc): ?>
<nav class="tv-toc my-9 mb-14 border-y border-line bg-alt px-7 py-7 max-[560px]:px-5" aria-label="목차">
  <div class="mb-5 text-xs font-bold tracking-[.14em] text-muted">목차</div>
  <ol class="m-0 list-none p-0">
  <?php foreach ($toc as $i => $it): ?>
    <li><a class="flex w-full items-baseline gap-3 rounded px-1 py-1.5 text-ink no-underline hover:bg-accent-soft" href="#<?=e($it['anchor'])?>"><span class="w-8 shrink-0 text-[11px] text-muted"><?=sprintf('%02d', $i+1)?></span><span class="text-sm"><?=e($it['title'])?></span></a></li>
  <?php endforeach; ?>
  </ol>
</nav>
<?php endif; ?>

<article class="pb-10 pt-4">
<?php foreach (($trip['sections'] ?? []) as $sIdx => $s):
    $level = (int)($s['level'] ?? 2); if ($level === 1) continue;
    $si = trip_subinfo($s); $expenses = trip_expenses($s); $content = trip_section_html($s);
    $imgs = $s['images'] ?? []; $caps = is_array($s['image_captions'] ?? null) ? $s['image_captions'] : [];
    $hl = $level === 3 ? 3 : 2; $anchor = trip_section_anchor($s); $sid = $anchor !== '' ? $anchor : (($si['location'] ?? '') !== '' ? 'sec-' . ($sIdx + 1) : ''); ?>
  <section class="mb-[64px] scroll-mt-[84px]"<?=$sid !== '' ? ' id="'.e($sid).'"' : ''?><?=isset($routeBySection[$sIdx]) ? ' data-route-index="'.(int)$routeBySection[$sIdx].'"' : ''?>>
    <?php if (!empty($s['title'])): ?><h<?=$hl?> class="mb-5 font-serif text-[29px] font-medium leading-[1.35] tracking-[-.025em] text-heading max-[560px]:text-[26px]"><?=e($s['title'])?></h<?=$hl?>><?php endif; ?>

    <div class="tv-meta">
      <div class="tv-meta-grid">
        <?php if (($si['location'] ?? '') !== ''): ?><span class="tv-loc"><span class="tv-emoji" aria-hidden="true">📍</span><?php if (($si['location_url'] ?? '') !== ''): ?><a href="<?=e($si['location_url'])?>" target="_blank" rel="noopener" class="underline underline-offset-2"><?=e($si['location'])?></a><?php else: ?><?=e($si['location'])?><?php endif; ?></span><?php endif; ?>
        <?php if (($si['purpose'] ?? '') !== ''): ?><span>· <?=e($si['purpose'])?></span><?php endif; ?>
        <?php if ((int)($si['rating'] ?? 0) > 0): ?><span class="tracking-[.08em] text-ink"><?=str_repeat('★', (int)$si['rating'])?><?=str_repeat('☆', 5-(int)$si['rating'])?></span><?php endif; ?>
      </div>
      <?php if (!empty($si['features'])): ?><div class="mt-3 flex flex-wrap gap-[7px]"><?php foreach ($si['features'] as $f): ?><span class="tv-chip"><?=e($f)?></span><?php endforeach; ?></div><?php endif; ?>
    </div>

    <?php if (trim($content) !== ''): ?><div class="story"><?=$content?></div><?php endif; ?>

    <?php if ($imgs): ?>
    <?php
      // 사진 배치: 가로로 긴 사진은 한 줄을 모두 쓰고, 세로·정사각 사진은 두 장씩 나란히(높이를 맞춰 비율 그대로). 억지로 같은 높이 칸에 넣지 않는다.
      $items = [];
      foreach ($imgs as $img) { $inf = photo_info((string)$img); $items[] = ['img' => (string)$img, 'inf' => $inf, 'r' => $inf ? $inf['w'] / max(1, $inf['h']) : 1.5]; }
      $rows = []; $pend = [];
      foreach ($items as $it) {
          if ($it['r'] >= 1.12) { if ($pend) { $rows[] = $pend; $pend = []; } $rows[] = [$it]; }
          else { $pend[] = $it; if (count($pend) === 2) { $rows[] = $pend; $pend = []; } }
      }
      if ($pend) $rows[] = $pend;
    ?>
    <div class="tv-gallery mt-7" data-gallery>
      <?php foreach ($rows as $row_): $pair = count($row_) === 2; $lone = count($row_) === 1 && $row_[0]['r'] < 1.12; ?>
      <div class="tv-gal-row<?=$pair ? ' is-pair' : ''?><?=$lone ? ' is-lone' : ''?>">
        <?php foreach ($row_ as $it):
            $img = $it['img']; $inf = $it['inf']; $cap = (string)($caps[$img] ?? '');
            $bw = $inf ? min(2400, $inf['w']) : 0; $bh = $inf ? (int)round($inf['h'] * $bw / max(1, $inf['w'])) : 0; ?>
        <figure class="tv-fig" style="--r:<?=round($it['r'], 3)?>">
          <a href="<?=e(photo_thumb_url($img, 2400))?>" class="photo-link block"<?=$bw ? ' data-pswp-width="'.$bw.'" data-pswp-height="'.$bh.'"' : ''?> data-orig="<?=e(photo_url($img))?>"<?=$cap !== '' ? ' data-caption="'.e($cap).'"' : ''?>><?=photo_img($img, $cap !== '' ? $cap : (string)$trip['title'], ['w'=>960,'widths'=>[480,960,1600],'sizes'=>$pair ? '(max-width:560px) 100vw, 410px' : ($lone ? '(max-width:560px) 100vw, 410px' : '(max-width:860px) 100vw, 820px'),'class'=>'block h-auto w-full transition-transform duration-300 hover:scale-[1.015]'])?></a>
          <?php if ($cap !== ''): ?><figcaption><?=e($cap)?></figcaption><?php endif; ?>
        </figure>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($expenses): $krw = 0; $sums = []; ?>
    <div class="tv-expense">
      <div class="text-[11px] font-bold tracking-[.12em] text-muted">지출 내역</div>
      <div class="mt-3">
      <?php foreach ($expenses as $ex):
          $amt = (float)($ex['amount'] ?? 0); $cur = strtoupper((string)($ex['currency'] ?? 'KRW'));
          $sums[$cur] = ($sums[$cur] ?? 0) + $amt;
          $line = expense_krw($ex) ?? 0; $krw += $line; $cat = expense_category($ex); ?>
        <div class="tv-expense-row"><span class="flex items-center gap-2"><i class="inline-block h-2 w-2 shrink-0 rounded-full" style="background:<?=EXPENSE_CATS[$cat][1]?>" title="<?=e(EXPENSE_CATS[$cat][0])?>"></i><?=e($ex['item'] ?? '')?></span><span><?=e($cur)?> <?=trip_num($amt)?><?php if ($line > 0 && $cur !== 'KRW'): ?><span class="ml-2 text-muted">≈ ₩<?=number_format($line)?></span><?php endif; ?></span></div>
      <?php endforeach; ?>
      </div>
      <div class="mt-3 flex flex-wrap gap-[7px]">
        <?php foreach ($sums as $c => $v): ?><span class="tv-chip"><?=e($c)?> <?=trip_num($v)?></span><?php endforeach; ?>
        <?php if ($krw > 0): ?><span class="tv-chip tv-expense-total">총 환산액 ₩<?=number_format($krw)?></span><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</article>
</div>

</div>
<?php
}

/** (예전 단순 라이트박스 — 이제 PhotoSwipe 갤러리가 대체합니다. 호환을 위해 빈 함수 유지) */
function trip_render_lightbox(): void {}

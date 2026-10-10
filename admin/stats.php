<?php
// 방문 통계: 쿠키·외부 서비스 없이 서버에서 직접 센 조회수(includes/stats.php). 봇·관리자 접속은 제외됩니다.
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/data.php';
require_once __DIR__.'/../includes/stats.php';
require_once __DIR__.'/style.php';

$days = (int)($_GET['d'] ?? 30); if (!in_array($days, [7, 30, 90], true)) $days = 30;
$s = stats_summary($days);
$titles = []; foreach (load_trips_admin() as $t) $titles[$t['id']] = $t['title'];
$maxV = max(array_merge([1], array_map(fn($r) => $r[0], $s['daily'])));
$today = $s['daily'][date('Y-m-d')] ?? [0, 0];
$n = count($s['daily']); $avg = $n ? round($s['v'] / $n, 1) : 0;
$W = 760; $H = 190; $pl = 30; $pb = 22; $bw = ($W - $pl) / max(1, $n);
$top = array_slice($s['trips'], 0, 10, true); $topMax = max(array_merge([1], array_values($top)));
$refs = array_slice($s['refs'], 0, 8, true); $refMax = max(array_merge([1], array_values($refs)));
$hasAny = $s['v'] > 0;

admin_head('방문 통계'); ?>
<div class="adm-wrap">
  <?php admin_nav('stats'); ?>
  <section>
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="text-xs tracking-[.18em] text-stone-400">VISITORS</p>
        <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">방문 통계</h1>
        <p class="mt-2 text-sm text-stone-500">쿠키 없이 서버에서 직접 센 값입니다. 봇·관리자 접속·Do Not Track 은 제외하고, 같은 사람의 하루 여러 번 방문은 "방문자" 1명으로 셉니다.</p>
      </div>
      <div class="adm-tabs" role="tablist"><?php foreach ([7, 30, 90] as $d): ?><a href="stats.php?d=<?=$d?>" aria-current="<?=$d === $days ? 'true' : 'false'?>">최근 <?=$d?>일</a><?php endforeach; ?></div>
    </div>

    <div class="adm-kpis">
      <div><small>조회수</small><b><?=number_format($s['v'])?></b><span>일평균 <?=$avg?></span></div>
      <div><small>방문자(일별 합)</small><b><?=number_format($s['u'])?></b><span>하루 안에는 중복 제외</span></div>
      <div><small>오늘</small><b><?=number_format($today[0])?></b><span>방문자 <?=number_format($today[1])?></span></div>
    </div>

    <?php if (!STATS_ENABLED): ?><div class="adm-note">통계 수집이 꺼져 있습니다(<code>STATS_ENABLED</code>).</div><?php endif; ?>
    <?php if (!$hasAny): ?><div class="adm-note">아직 집계된 방문이 없습니다. 방문자가 여행 글이나 홈을 열면 여기에 쌓입니다(관리자로 로그인한 상태의 접속은 세지 않아요).</div><?php endif; ?>

    <div class="adm-chart">
      <svg viewBox="0 0 <?=$W?> <?=$H + $pb?>" width="100%" role="img" aria-label="일별 조회수 그래프" style="display:block;max-width:100%">
        <?php foreach ([0, .5, 1] as $g): $y = 8 + ($H - 16) * (1 - $g); ?><line x1="<?=$pl?>" x2="<?=$W?>" y1="<?=$y?>" y2="<?=$y?>" stroke="#ece9e4"/><text x="<?=$pl - 6?>" y="<?=$y + 3?>" text-anchor="end" font-size="9" fill="#a8a29e"><?=round($maxV * $g)?></text><?php endforeach; ?>
        <?php $i = 0; $pts = [];
        foreach ($s['daily'] as $day => [$v, $u]):
          $x = $pl + $i * $bw; $h = ($H - 16) * $v / $maxV; $hu = ($H - 16) * $u / $maxV;
          $pts[] = round($x + $bw / 2, 1) . ',' . round($H - 8 - $hu, 1); ?>
          <rect x="<?=round($x + $bw * .12, 1)?>" y="<?=round($H - 8 - $h, 1)?>" width="<?=round($bw * .76, 1)?>" height="<?=round($h, 1)?>" rx="2" fill="#1c1917" opacity=".82"><title><?=e($day)?> · 조회 <?=$v?> · 방문자 <?=$u?></title></rect>
          <?php if ($n <= 31 ? $i % ($n > 14 ? 5 : 1) === 0 : $i % 14 === 0): ?><text x="<?=round($x + $bw / 2, 1)?>" y="<?=$H + 12?>" text-anchor="middle" font-size="9" fill="#a8a29e"><?=e(substr($day, 5))?></text><?php endif; ?>
        <?php $i++; endforeach; ?>
        <polyline points="<?=implode(' ', $pts)?>" fill="none" stroke="#d97706" stroke-width="1.6" stroke-linejoin="round"/>
      </svg>
      <p style="margin:6px 0 0;font-size:11px;color:#a8a29e"><span style="display:inline-block;width:10px;height:10px;background:#1c1917;opacity:.82;border-radius:2px;vertical-align:-1px"></span> 조회수 &nbsp; <span style="display:inline-block;width:14px;height:2px;background:#d97706;vertical-align:3px"></span> 방문자</p>
    </div>

    <div class="grid gap-6 md:grid-cols-2" style="margin-top:6px">
      <div>
        <h2 class="adm-h2">많이 본 여행</h2>
        <table class="adm-table"><tbody>
          <?php foreach ($top as $id => $c): ?><tr><td><a href="../trip.php?id=<?=rawurlencode((string)$id)?>" target="_blank" rel="noopener" style="color:inherit"><?=e($titles[$id] ?? $id)?></a><div class="adm-bar" style="margin-top:6px"><i style="width:<?=round($c / $topMax * 100)?>%"></i></div></td><td style="text-align:right;width:60px;font-weight:600"><?=number_format($c)?></td></tr><?php endforeach; ?>
          <?php if (!$top): ?><tr><td style="padding:26px;text-align:center;color:#a8a29e">데이터 없음</td></tr><?php endif; ?>
        </tbody></table>
      </div>
      <div>
        <h2 class="adm-h2">유입 경로</h2>
        <table class="adm-table"><tbody>
          <?php foreach ($refs as $h => $c): ?><tr><td><?=e($h)?><div class="adm-bar" style="margin-top:6px"><i style="width:<?=round($c / $refMax * 100)?>%;background:#d97706"></i></div></td><td style="text-align:right;width:60px;font-weight:600"><?=number_format($c)?></td></tr><?php endforeach; ?>
          <?php if (!$refs): ?><tr><td style="padding:26px;text-align:center;color:#a8a29e">외부 사이트에서 들어온 방문이 아직 없습니다</td></tr><?php endif; ?>
        </tbody></table>
      </div>
    </div>
    <p class="adm-note">저장 위치: <code>private/stats/YYYY-MM.json</code> (월 1개, 웹에서 직접 열 수 없음). 홈의 "많이 본 여행"은 최근 90일 데이터가 충분히 쌓이면 자동으로 나타납니다.</p>
  </section>
</div>
<?php admin_foot();

<?php
/** 사진 모아보기: 공개 여행의 모든 사진을 한 목록으로(크기 포함). cache/gallery.json 에 캐시 — 여행 데이터가 바뀌면 자동 재생성 */
require_once __DIR__ . '/images.php';

function gallery_dims(string $rel): ?array {
    static $dims = null, $dirty = false;
    if ($dims === null) {
        $f = CACHE_DIR . '/dims.json'; $dims = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
        register_shutdown_function(function () use (&$dims, &$dirty, $f) { if ($dirty && is_dir(dirname($f)) && is_writable(dirname($f))) @file_put_contents($f, json_encode($dims), LOCK_EX); });
    }
    $abs = photo_abs($rel); if ($abs === null) return null;
    $mt = (int)@filemtime($abs);
    if (isset($dims[$rel]) && $dims[$rel][2] === $mt) return $dims[$rel];
    $i = @getimagesize($abs); if (!$i || $i[0] < 1) return null;
    $dirty = true; return $dims[$rel] = [(int)$i[0], (int)$i[1], $mt];
}

/** [ ['rel','w','h','cap','id','title','country','date','year'], … ]  (여행 날짜 내림차순, 여행 안에서는 글 순서) */
function gallery_items(): array {
    $st = store(); $sig = $st->signature() . '|' . date('Y-m-d-H');     // 예약 공개 반영을 위해 시간 단위로도 갱신
    $cf = CACHE_DIR . '/gallery.json';
    $c = is_file($cf) ? json_decode((string)file_get_contents($cf), true) : null;
    if (is_array($c) && ($c['v'] ?? 0) === 1 && ($c['sig'] ?? '') === $sig) return $c['items'];
    $items = [];
    $trips = array_values(array_filter($st->all(), 'trip_is_public'));
    usort($trips, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
    foreach ($trips as $t) {
        $caps = [];
        foreach ((array)($t['sections'] ?? []) as $s) { if (is_array($s['image_captions'] ?? null)) $caps += $s['image_captions']; }
        foreach (trip_photo_paths($t) as $rel) {
            $d = gallery_dims($rel); if (!$d) continue;
            $items[] = ['rel' => $rel, 'w' => $d[0], 'h' => $d[1], 'cap' => (string)($caps[$rel] ?? ''), 'id' => (string)$t['id'], 'title' => (string)$t['title'],
                        'country' => (string)($t['country'] ?? ''), 'date' => (string)($t['date'] ?? ''), 'year' => substr((string)($t['date'] ?? ''), 0, 4)];
        }
    }
    if (is_dir(CACHE_DIR) && is_writable(CACHE_DIR)) @file_put_contents($cf, json_encode(['v' => 1, 'sig' => $sig, 'items' => $items], JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $items;
}

/** 갤러리 타일 묶음 HTML (여행이 바뀌는 곳마다 제목 줄) */
function gallery_render_chunk(array $items, ?string $prevTripId): string {
    $html = ''; $cur = $prevTripId;
    foreach ($items as $it) {
        if ($it['id'] !== $cur) {
            $cur = $it['id'];
            $html .= '<div class="tv-gal-head"><a href="trip.php?id=' . rawurlencode($it['id']) . '"><b>' . e($it['title']) . '</b><span>' . e(trim($it['date'] . ' · ' . $it['country'], ' ·')) . '</span></a></div>';
        }
        $bw = min(2400, $it['w']); $bh = (int)round($it['h'] * $bw / max(1, $it['w']));
        $cap = trim($it['title'] . ($it['cap'] !== '' ? ' — ' . $it['cap'] : ''));
        $html .= '<a class="tv-gal-tile photo-link" href="' . e(photo_thumb_url($it['rel'], 2400)) . '" data-pswp-width="' . $bw . '" data-pswp-height="' . $bh . '" data-orig="' . e(photo_url($it['rel'])) . '" data-caption="' . e($cap) . '">'
              . photo_img($it['rel'], $it['cap'] !== '' ? $it['cap'] : $it['title'], ['w' => 480, 'widths' => [240, 480, 960], 'sizes' => '(max-width:560px) 50vw, (max-width:1000px) 33vw, 240px', 'nodim' => true]) . '</a>';
    }
    return $html;
}

/** 홈용: 공개 여행 전체의 주요 장소(최신 여행부터). cache/places.json 에 캐시 */
function places_index(): array {
    require_once __DIR__ . '/trip-render.php';
    $st = store(); $sig = $st->signature() . '|' . date('Y-m-d-H');
    $cf = CACHE_DIR . '/places.json';
    $c = is_file($cf) ? json_decode((string)file_get_contents($cf), true) : null;
    if (is_array($c) && ($c['v'] ?? 0) === 1 && ($c['sig'] ?? '') === $sig) return $c['items'];
    $trips = array_values(array_filter($st->all(), 'trip_is_public'));
    usort($trips, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
    $items = [];
    foreach ($trips as $t) foreach (trip_section_places($t) as $p) $items[] = ['n' => $p['name'], 'id' => (string)$t['id'], 'a' => $p['anchor'], 't' => (string)$t['title']];
    if (is_dir(CACHE_DIR) && is_writable(CACHE_DIR)) @file_put_contents($cf, json_encode(['v' => 1, 'sig' => $sig, 'items' => $items], JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $items;
}

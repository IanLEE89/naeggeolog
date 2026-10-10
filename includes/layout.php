<?php
/** 공개 페이지 공통 레이아웃: <head>, 스크립트, 여행 카드 */
require_once __DIR__ . '/../theme.php';
require_once __DIR__ . '/../site.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/images.php';

/**
 * 관리자 로그인 상태(읽기 전용). 관리자 쿠키가 있는 방문자(= 주인)에게만 세션을 열고 곧바로 닫는다.
 * 일반 방문자는 세션을 전혀 만들지 않는다.
 */
function site_session_data(): array {
    static $d = null;
    if ($d !== null) return $d;
    if (session_status() === PHP_SESSION_ACTIVE) return $d = (array)$_SESSION;
    if (empty($_COOKIE['NAEGGEOLOGSESS']) || headers_sent()) return $d = [];
    session_name('NAEGGEOLOGSESS');
    session_cache_limiter('');                      // 공개 페이지의 캐시 헤더를 건드리지 않음
    @session_start(['read_and_close' => true, 'use_strict_mode' => 1]);
    return $d = (array)($_SESSION ?? []);
}
function is_admin_session(): bool { return !empty(site_session_data()['admin_logged_in']); }
/** 내 공간 회원으로 로그인한 방문자(아이콘 표시용 — 실제 권한 확인은 hub 에서 매번 함) */
function is_member_session(): bool { return !empty(site_session_data()['hub_uid']); }

/** 정적 파일 URL + 수정시각 기반 캐시 무효화 */
function asset_url(string $path): string {
    $f = dirname(__DIR__) . '/' . ltrim($path, '/');
    return $path . '?v=' . (is_file($f) ? filemtime($f) : 1);
}
/** 사이트 루트 기준 상대 URL → 절대 URL (OG 이미지용) */
function abs_url(string $rel): string {
    if (preg_match('~^https?://~i', $rel)) return $rel;
    if (SITE_URL !== '') return rtrim(SITE_URL, '/') . '/' . ltrim($rel, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . (str_starts_with($rel, '/') ? '' : $dir . '/') . $rel;
}

/**
 * <html> ~ <body> 시작까지 출력.
 * 옵션: title, desc, og_image, robots, base, bodyClass, expect(페이지 전환 전에 파싱을 기다릴 상단 요소의 #id)
 */
function page_head(array $o = []): void {
    global $TRAVEL_THEME;
    $title = $o['title'] ?? SITE_TITLE;
    if (!headers_sent()) header('Vary: Accept');   // 브라우저별 WebP/JPEG 선택이 HTML에 반영되므로
    ?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if (!empty($o['base'])): ?><base href="<?=e($o['base'])?>">
<?php endif; ?>
<title><?=e($title)?></title>
<?php if (!empty($o['desc'])): ?><meta name="description" content="<?=e($o['desc'])?>">
<?php endif; ?>
<?php if (!empty($o['robots'])): ?><meta name="robots" content="<?=e($o['robots'])?>">
<?php endif; ?>
<meta name="color-scheme" content="light dark">
<script>try{var t=localStorage.getItem("naeggeolog-theme");if(t!=="dark"&&t!=="light")t=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";document.documentElement.dataset.theme=t}catch(e){}if((window.scrollY||0)>8)document.documentElement.dataset.scrolled=1</script>
<meta name="theme-color" content="<?=e($TRAVEL_THEME['header_bg'] ?? '#f8f7f3')?>">
<meta property="og:site_name" content="<?=e(SITE_TITLE)?>">
<meta property="og:locale" content="ko_KR">
<meta property="og:type" content="<?=e($o['og_type'] ?? 'website')?>">
<meta property="og:title" content="<?=e($o['og_title'] ?? $title)?>">
<?php if (!empty($o['desc'])): ?><meta property="og:description" content="<?=e($o['desc'])?>">
<?php endif; ?>
<?php if (isset($o['canonical'])): $__canon = abs_url((string)$o['canonical']); ?><link rel="canonical" href="<?=e($__canon)?>">
<meta property="og:url" content="<?=e($__canon)?>">
<?php endif; ?>
<?php if (!empty($o['og_image'])): ?><meta property="og:image" content="<?=e($o['og_image'])?>">
<?php endif; ?>
<meta name="twitter:card" content="<?=!empty($o['og_image']) ? 'summary_large_image' : 'summary'?>">
<link rel="alternate" type="application/rss+xml" title="<?=e(SITE_TITLE)?> RSS" href="feed.php">
<?php if (!empty($o['jsonld'])): ?><script type="application/ld+json"><?=json_encode($o['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)?></script>
<?php endif; ?>
<link rel="icon" href="favicon.ico" sizes="48x48">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="manifest" href="manifest.php">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?=e(SITE_TITLE)?>">
<?=travel_theme_style()?>
<link rel="stylesheet" href="<?=e(asset_url('assets/app.css'))?>">
<?php if (!empty($o['expect'])): ?><link rel="expect" href="<?=e($o['expect'])?>" blocking="render">
<?php endif; ?>
</head>
<body class="m-0 bg-page font-sans leading-[1.75] text-ink antialiased <?=e($o['bodyClass'] ?? '')?>">
<?php
}

/** 공통 스크립트 + </body></html>. 옵션: maps(bool) */
function page_end(array $o = []): void {
    if (!empty($o['maps'])) include dirname(__DIR__) . '/google-maps-loader.php';
    ?><script>window.TV_GALLERY_V=<?=(int)@filemtime(dirname(__DIR__).'/assets/gallery.js')?>;</script>
<script src="<?=e(asset_url('assets/app.js'))?>" defer></script>
</body>
</html>
<?php
}

/**
 * 여행 카드. 옵션:
 *   variant: 'grid'(기본, 사진 위·글 아래) | 'poster'(세로 포스터, 글이 사진 위에 겹침 — 가로 스크롤 줄에서 사용)
 *   country: 국가명 표시
 */
function render_trip_card(array $t, array $o = []): void {
    $cover = (string)($t['cover'] ?? '');
    $variant = $o['variant'] ?? 'grid';
    $showC = !empty($o['country']);
    $date = (string)($t['date'] ?? '');
    $metaParts = array_filter([$date, $showC ? ($t['country'] ?? '') : '', $t['location'] ?? ''], fn($x) => $x !== '');
    $meta = implode(' · ', $metaParts);
    $href = 'trip.php?id=' . rawurlencode($t['id']);
    $cam = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>';
    if ($variant === 'poster') { ?>
<article class="tv-poster" data-trip="<?=e($t['id'])?>">
  <a href="<?=$href?>" class="tv-poster-link">
    <div class="tv-poster-img"><?php if ($cover !== ''): ?><?=photo_img($cover, '', ['w' => 960, 'widths' => [480, 960], 'sizes' => '(max-width:640px) 78vw, 340px', 'nodim' => true])?><?php endif; ?></div>
    <div class="tv-poster-body">
      <span class="tv-poster-meta"><?=e($meta)?></span>
      <h3 class="tv-poster-title"><?=e($t['title'])?></h3>
      <span class="tv-poster-more"><?=$cam?> <?=intval($t['image_count'] ?? 0)?> · 보기 →</span>
    </div>
  </a>
</article>
<?php return; } ?>
<article class="tv-card tv-reveal" data-country="<?=e($t['country'] ?? '')?>" data-year="<?=e(substr($date, 0, 4))?>" data-date="<?=e($date)?>" data-trip="<?=e($t['id'])?>">
  <a class="block" href="<?=$href?>">
    <div class="tv-thumb"><?php if ($cover !== ''): ?><?=photo_img($cover, (string)$t['title'], ['w' => 480, 'widths' => [480, 960], 'sizes' => '(max-width:560px) 100vw, (max-width:900px) 50vw, 384px', 'nodim' => true])?><?php endif; ?>
      <span class="tv-badge"><?=$cam?> <?=intval($t['image_count'] ?? 0)?></span></div>
    <div class="tv-card-body">
      <div class="tv-card-meta"><?=e($meta)?></div>
      <h3 class="tv-title"><?=e($t['title'])?></h3>
      <?php if (($t['summary'] ?? '') !== ''): ?><p class="tv-sum"><?=e($t['summary'])?></p><?php endif; ?>
    </div>
  </a>
</article>
<?php
}

/** 이전 · 다음 여행 이동 카드 */
function render_trip_pager(array $nb): void {
    if (empty($nb['prev']) && empty($nb['next'])) return;
    $card = function (?array $t, string $dir) {
        if (!$t) return '<div></div>';
        $label = $dir === 'prev' ? '← 이전 여행' : '다음 여행 →';
        $img = !empty($t['cover']) ? '<span class="block h-16 w-24 shrink-0 overflow-hidden rounded-md bg-line">' . photo_img((string)$t['cover'], '', ['w' => 240, 'widths' => [240, 480], 'sizes' => '96px', 'nodim' => true, 'class' => 'h-full w-full object-cover']) . '</span>' : '';
        $text = '<span class="min-w-0 ' . ($dir === 'next' ? 'text-right' : '') . '"><span class="block text-[11px] font-bold tracking-[.1em] text-muted">' . $label . '</span><span class="mt-1 block truncate text-[15px] text-heading">' . e($t['title']) . '</span><span class="block text-[12px] text-muted">' . e(trim(($t['date'] ?? '') . ' · ' . ($t['country'] ?? ''), ' ·')) . '</span></span>';
        return '<a rel="' . $dir . '" href="trip.php?id=' . rawurlencode($t['id']) . '" class="tv-pager-card flex items-center gap-4 rounded-xl border border-line bg-surface p-3 transition hover:border-ink ' . ($dir === 'next' ? 'flex-row-reverse text-right' : '') . '">' . ($dir === 'prev' ? $img . $text : $img . $text) . '</a>';
    };
    ?>
<nav class="tv-read mb-16 mt-4" aria-label="이전·다음 여행">
  <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1"><?=$card($nb['prev'], 'prev')?><?=$card($nb['next'], 'next')?></div>
  <p class="mt-3 text-center text-[12px] text-muted"><?=intval($nb['pos'])?> / <?=intval($nb['total'])?> · 시간 순서 · <a class="underline underline-offset-2 hover:text-ink" href="index.php#archive">전체 목록</a></p>
</nav>
<?php
}

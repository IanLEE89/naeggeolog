<?php
$header_trips = $header_trips ?? load_trips();
$header_countries = $header_countries ?? countries_from_trips($header_trips);
$current_country = $current_country ?? '';
$current_id = $current_id ?? '';
$current_page = $current_page ?? '';
$header_overlay = !empty($header_overlay);          // 홈: 히어로 위에 투명하게 겹침
$isAdmin = is_admin_session();
$isMember = !$isAdmin && is_member_session();
$ico = 'width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
?>
<header class="tv-header" <?=$header_overlay ? 'data-overlay' : ''?> style="view-transition-name:site-header">
  <?php if ($header_overlay): ?><noscript><style>.tv-header[data-overlay]{background:color-mix(in srgb,var(--theme-header_bg) 92%,transparent)!important;color:var(--theme-text)!important;border-color:var(--theme-border)!important}.tv-header[data-overlay] .tv-logo{filter:none!important}.tv-header[data-overlay] .tv-logo-pair .tv-logo-l{display:block!important}.tv-header[data-overlay] .tv-logo-pair .tv-logo-d{display:none!important}</style></noscript><?php endif; ?>
  <div class="tv-wrap tv-header-row">
    <button type="button" class="tv-icon-btn tv-menu-btn md:hidden" popovertarget="mobileNav" aria-label="메뉴 열기" aria-controls="mobileNav">
      <svg <?=$ico?>><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    <a class="tv-brand" href="index.php" aria-label="<?=e(SITE_TITLE)?> 홈">
      <?php
        // 로고: assets/logo.svg(또는 .png) + 어두운 배경용 assets/logo-dark.svg(또는 .png). 어두운 버전이 없으면 밝은 로고를 색 반전해서 씀. 둘 다 없으면 사이트 이름을 글자로 표시
        $__logo = static function (string $base): ?array {
            foreach (['svg', 'png', 'webp'] as $x) {
                $f = __DIR__ . "/../assets/$base.$x";
                if (!is_file($f)) continue;
                $w = 240; $h = 50;
                if ($x === 'svg') { if (preg_match('/<svg[^>]*\bwidth="([\d.]+)"[^>]*\bheight="([\d.]+)"/', (string)@file_get_contents($f, false, null, 0, 2048), $m)) { $w = (int)round((float)$m[1]); $h = (int)round((float)$m[2]); } }
                elseif ($sz = @getimagesize($f)) { [$w, $h] = $sz; }
                return ["assets/$base.$x?v=" . filemtime($f), $w, $h];
            }
            return null;
        };
        $__ll = $__logo('logo'); $__ld = $__ll ? $__logo('logo-dark') : null;
      ?>
      <?php if ($__ll && $__ld): ?><span class="tv-logo-pair"><img class="tv-logo-l" src="<?=e($__ll[0])?>" alt="<?=e(SITE_TITLE)?>" width="<?=$__ll[1]?>" height="<?=$__ll[2]?>"><img class="tv-logo-d" src="<?=e($__ld[0])?>" alt="" aria-hidden="true" width="<?=$__ld[1]?>" height="<?=$__ld[2]?>"></span>
      <?php elseif ($__ll): ?><img class="tv-logo" src="<?=e($__ll[0])?>" alt="<?=e(SITE_TITLE)?>" width="<?=$__ll[1]?>" height="<?=$__ll[2]?>">
      <?php else: ?><span class="tv-logo tv-logo-text"><?=e(SITE_TITLE)?></span><?php endif; ?>
    </a>

    <nav class="tv-nav hidden md:flex" aria-label="주요 메뉴">
      <div class="tv-mega-wrap">
        <button type="button" class="tv-nav-link <?=($current_country!=='' || $current_id!=='') ? 'is-current' : ''?>" aria-expanded="false" aria-haspopup="true" data-mega-toggle>
          여행지 <svg class="tv-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="tv-mega" role="group" aria-label="여행지">
          <div class="tv-mega-grid" style="--cols:<?=max(1, min(4, count($header_countries)))?>">
            <?php foreach ($header_countries as $__nc): $__hl = trips_by_country($header_trips, $__nc); ?>
            <div class="tv-mega-col">
              <a class="tv-mega-head <?=$current_country===$__nc ? 'is-current' : ''?>" href="country.php?country=<?=rawurlencode($__nc)?>"><?=e($__nc)?> <span><?=count($__hl)?></span></a>
              <?php foreach (array_slice($__hl, 0, 5) as $__nt): ?>
                <a class="tv-mega-item <?=$current_id===$__nt['id'] ? 'is-current' : ''?>" href="trip.php?id=<?=rawurlencode($__nt['id'])?>"><?=e($__nt['title'])?></a>
              <?php endforeach; ?>
              <?php if (count($__hl) > 5): ?><a class="tv-mega-item tv-mega-more" href="country.php?country=<?=rawurlencode($__nc)?>">+<?=count($__hl) - 5?>편 더 보기</a><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <a class="tv-nav-link" href="index.php#journeys">최근 여행</a>
      <a class="tv-nav-link" href="index.php#map">지도</a>
      <a class="tv-nav-link <?=$current_page==='gallery' ? 'is-current' : ''?>" href="gallery.php">사진</a>
      <a class="tv-nav-link <?=$current_page==='expenses' ? 'is-current' : ''?>" href="expenses.php">지출</a>
    </nav>

    <div class="tv-header-tools">
      <button type="button" class="tv-icon-btn" data-search-open aria-label="검색 (단축키 /)" title="검색 ( / )">
        <svg <?=$ico?>><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg>
      </button>
      <button type="button" data-theme-toggle class="tv-icon-btn" aria-label="다크 모드 전환" aria-pressed="false" title="다크 모드">
        <svg class="dark:hidden" <?=$ico?>><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8Z"/></svg>
        <svg class="hidden dark:block" <?=$ico?>><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <?php if ($isAdmin || $isMember): ?>
      <a class="tv-icon-btn" href="hub/" aria-label="내 공간" title="내 공간 (파일 · 기능)">
        <svg <?=$ico?>><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/></svg>
      </a>
      <?php endif; ?>
      <?php if ($isAdmin): ?>
      <a class="tv-icon-btn tv-admin is-on" href="admin/index.php" aria-label="관리자 화면으로 이동" title="관리자 화면 (로그인됨)">
        <svg <?=$ico?>><path d="M12 3 4 6v5.5c0 4.6 3.2 8.2 8 9.5 4.8-1.3 8-4.9 8-9.5V6z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg><i class="tv-admin-dot" aria-hidden="true"></i>
      </a>
      <?php elseif (!$isMember): ?>
      <a class="tv-icon-btn tv-admin" href="hub/login.php" aria-label="로그인" title="로그인 (내 공간 · 관리자)">
        <svg <?=$ico?>><circle cx="12" cy="8.5" r="3.6"/><path d="M5 20c.8-3.6 3.8-5.5 7-5.5s6.2 1.9 7 5.5"/></svg>
      </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- 검색 팔레트 (/ 또는 Ctrl·⌘+K) -->
<dialog id="searchDialog" class="tv-search" aria-label="사이트 검색">
  <form action="index.php#archive" method="get" role="search" class="tv-search-form">
    <svg class="tv-search-ico" <?=$ico?>><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg>
    <input id="searchInput" name="q" type="search" autocomplete="off" spellcheck="false" placeholder="여행지 · 장소 · 날짜 · 본문 검색" aria-label="검색어" aria-controls="searchResults" role="combobox" aria-expanded="false">
    <kbd class="tv-kbd">Esc</kbd>
  </form>
  <div id="searchResults" class="tv-search-results" role="listbox" aria-label="검색 결과" hidden></div>
  <div id="searchIdle" class="tv-search-idle">
    <p class="tv-search-label">나라</p>
    <div class="tv-search-chips">
      <?php foreach ($header_countries as $__c): ?><a href="country.php?country=<?=rawurlencode($__c)?>"><?=e($__c)?></a><?php endforeach; ?>
    </div>
    <p class="tv-search-label">최근 여행</p>
    <?php foreach (array_slice($header_trips, 0, 4) as $__nt): ?>
      <a class="tv-search-item" href="trip.php?id=<?=rawurlencode($__nt['id'])?>">
        <span class="tv-search-thumb"><?php if ($__nt['cover'] !== ''): ?><img src="<?=e(photo_thumb_url($__nt['cover'], 240))?>" alt="" width="56" height="56" loading="lazy"><?php endif; ?></span>
        <span class="min-w-0"><b><?=e($__nt['title'])?></b><small><?=e(trim($__nt['date'] . ' · ' . $__nt['country'], ' ·'))?></small></span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="tv-search-foot"><span><kbd class="tv-kbd">↑</kbd><kbd class="tv-kbd">↓</kbd> 이동</span><span><kbd class="tv-kbd">Enter</kbd> 열기 · 전체 결과</span></div>
</dialog>

<!-- 모바일 메뉴 (popover: 바깥 탭 / Esc 로 닫힘) -->
<div id="mobileNav" popover class="tv-drawer md:hidden" aria-label="메뉴">
  <div class="flex items-center justify-between border-b border-line px-4 py-3">
    <span class="font-serif text-lg text-heading"><?=e(SITE_TITLE)?></span>
    <button type="button" class="tv-icon-btn" popovertarget="mobileNav" popovertargetaction="hide" aria-label="메뉴 닫기"><svg <?=$ico?>><path d="M6 6l12 12M18 6 6 18"/></svg></button>
  </div>
  <div class="px-4 pt-4"><button type="button" class="tv-drawer-search" data-search-open><svg <?=$ico?>><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg> 여행지 · 장소 · 날짜 검색</button></div>
  <nav class="px-2 py-3" aria-label="모바일 메뉴">
    <a class="block rounded-lg px-3 py-3 text-[15px] text-ink hover:bg-alt" href="index.php">홈</a>
    <a class="block rounded-lg px-3 py-3 text-[15px] text-ink hover:bg-alt" href="index.php#journeys">최근 여행</a>
    <a class="block rounded-lg px-3 py-3 text-[15px] text-ink hover:bg-alt" href="index.php#map">지도</a>
    <?php foreach($header_countries as $__nc): $__hl = trips_by_country($header_trips, $__nc); ?>
      <details class="rounded-lg" <?=$current_country===$__nc ? 'open' : ''?>>
        <summary class="flex items-center justify-between rounded-lg px-3 py-3 text-[15px] text-ink hover:bg-alt">
          <span><?=e($__nc)?> <span class="ml-1 text-xs text-muted"><?=count($__hl)?></span></span>
          <svg class="tv-chev text-muted transition" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
        </summary>
        <div class="pb-2 pl-3">
          <a class="block rounded-lg px-3 py-2 text-[13px] text-muted hover:bg-alt" href="country.php?country=<?=rawurlencode($__nc)?>">전체 보기</a>
          <?php foreach($__hl as $__nt): ?>
            <a class="block rounded-lg px-3 py-2 text-[13px] leading-[1.45] <?=$current_id===$__nt['id'] ? 'bg-alt text-ink' : 'text-muted'?> hover:bg-alt" href="trip.php?id=<?=rawurlencode($__nt['id'])?>"><?=e($__nt['title'])?></a>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
    <a class="block rounded-lg px-3 py-3 text-[15px] text-ink hover:bg-alt" href="gallery.php">사진 모아보기</a>
    <a class="block rounded-lg px-3 py-3 text-[15px] text-ink hover:bg-alt" href="expenses.php">지출 대시보드</a>
    <?php if ($isAdmin || $isMember): ?><a class="mt-2 flex items-center gap-2 rounded-lg border-t border-line px-3 py-3 text-[14px] text-ink hover:bg-alt" href="hub/"><svg <?=$ico?>><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/></svg>내 공간</a><?php endif; ?>
    <a class="<?=($isAdmin || $isMember) ? '' : 'mt-2 border-t border-line '?>flex items-center gap-2 rounded-lg px-3 py-3 text-[14px] text-muted hover:bg-alt" href="<?=$isAdmin ? 'admin/index.php' : ($isMember ? 'hub/' : 'hub/login.php')?>"><svg <?=$ico?>><circle cx="12" cy="8.5" r="3.6"/><path d="M5 20c.8-3.6 3.8-5.5 7-5.5s6.2 1.9 7 5.5"/></svg><?=$isAdmin ? '관리자 화면' : ($isMember ? '파일 · 내 공간' : '로그인')?></a>
  </nav>
</div>

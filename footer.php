<?php
if (!isset($TRAVEL_SITE)) require_once __DIR__ . '/site.php';
$footer_wrap = $footer_wrap ?? 'tv-wrap';
$profilePhoto = travel_site_url($TRAVEL_SITE['profile_photo'] ?? '');
?>
<footer class="mt-12 border-t border-line bg-footer text-footer-ink">
  <div class="<?= $footer_wrap ?> py-12 sm:py-14">
    <div class="grid gap-10 md:grid-cols-[1.6fr_1fr]">
      <section class="flex items-start gap-4">
        <?php if ($profilePhoto): ?>
          <img src="<?=e($profilePhoto)?>" alt="<?=e($TRAVEL_SITE['profile_nickname'])?> 프로필" width="64" height="64" loading="lazy" decoding="async" class="h-16 w-16 flex-none rounded-full border border-line object-cover">
        <?php endif; ?>
        <div>
          <p class="text-[10px] font-bold tracking-[.18em] opacity-60">ABOUT</p>
          <h2 class="mt-2 font-serif text-xl">여행을 기록합니다.</h2>
          <p class="mt-3 max-w-md text-sm leading-7 opacity-70"><?=nl2br(e($TRAVEL_SITE['profile_intro']))?></p>
        </div>
      </section>
      <section>
        <p class="text-[10px] font-bold tracking-[.18em] opacity-60">PROFILE</p>
        <p class="mt-3 text-sm font-semibold"><?=e($TRAVEL_SITE['profile_nickname'])?></p>
        <?php if ($TRAVEL_SITE['profile_email'] !== ''): ?><a class="mt-1 block text-sm opacity-70" href="mailto:<?=e($TRAVEL_SITE['profile_email'])?>"><?=e($TRAVEL_SITE['profile_email'])?></a><?php endif; ?>
        <button type="button" class="tv-install mt-5" data-install hidden>📲 홈 화면에 추가</button>
        <p class="mt-6 flex items-center gap-3 text-[11px] opacity-60"><span><?=e($TRAVEL_SITE['footer_copyright'])?></span><a class="inline-flex items-center gap-1 hover:opacity-100 hover:underline" href="<?=is_admin_session() ? 'admin/index.php' : 'admin/login.php'?>" title="관리자"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span class="sr-only">관리자</span></a><a class="hover:opacity-100 hover:underline" href="feed.php" title="RSS 구독">RSS</a></p>
      </section>
    </div>
  </div>
</footer>
<button id="backToTop" type="button" aria-label="맨 위로" class="fixed bottom-5 right-5 z-[1400] flex h-11 w-11 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-lg transition duration-200" style="opacity:0;transform:translateY(12px);pointer-events:none">↑</button>

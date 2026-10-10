/* Naeggeolog — 공용 UI 스크립트 (헤더 · 검색 팔레트 · 가로 줄 · 필터 · 갤러리 · 다크 모드 · 페이지 전환)
 * 지도는 assets/maps.js 가 담당합니다. */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var root = document.documentElement;

  /* ── 헤더: 스크롤하면 투명 → 불투명 (홈 히어로 위에서만 투명) ── */
  (function () {
    var sentinel = document.createElement('div');
    sentinel.setAttribute('aria-hidden', 'true');
    sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:12px;pointer-events:none;visibility:hidden';
    document.body.appendChild(sentinel);
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) {
        if (en[0].isIntersecting) delete root.dataset.scrolled; else root.dataset.scrolled = '1';
      }).observe(sentinel);
    }
  })();

  /* ── 여행지 메가 메뉴: 터치/클릭 토글 (마우스는 CSS hover) ── */
  $$('[data-mega-toggle]').forEach(function (btn) {
    var wrap = btn.closest('.tv-mega-wrap');
    var setOpen = function (v) { if (v) wrap.setAttribute('data-open', ''); else wrap.removeAttribute('data-open'); btn.setAttribute('aria-expanded', v ? 'true' : 'false'); };
    btn.addEventListener('click', function (e) { e.stopPropagation(); setOpen(!wrap.hasAttribute('data-open')); });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
  });

  /* ── 검색 팔레트 (/ 또는 Ctrl·⌘+K) — 입력하는 대로 서버에서 본문까지 검색 ── */
  (function () {
    var dlg = $('#searchDialog'); if (!dlg || !dlg.showModal) return;
    var input = $('#searchInput'), box = $('#searchResults'), idle = $('#searchIdle'), form = input.form;
    var timer = 0, seq = 0, items = [], active = -1;
    var esc = function (t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var open = function () {
      if (dlg.open) return;
      var d = $('#mobileNav'); if (d && d.matches(':popover-open')) d.hidePopover();
      dlg.showModal(); input.focus(); input.select();
    };
    var close = function () { if (dlg.open) dlg.close(); };
    $$('[data-search-open]').forEach(function (b) { b.addEventListener('click', open); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });         // 바깥(backdrop) 클릭
    document.addEventListener('keydown', function (e) {
      var t = e.target, typing = t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
      if ((e.key === '/' && !typing && !e.metaKey && !e.ctrlKey) || ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k')) { e.preventDefault(); open(); }
    });
    var setActive = function (i) {
      items.forEach(function (el, k) { el.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
      active = i; if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
    };
    var render = function (data) {
      var q = data.q;
      if (!data.items.length) { box.innerHTML = '<p class="tv-search-empty">“' + esc(q) + '”에 대한 결과가 없습니다.</p>'; items = []; active = -1; return; }
      box.innerHTML = data.items.map(function (t) {
        return '<a class="tv-search-item" role="option" aria-selected="false" href="trip.php?id=' + encodeURIComponent(t.id) + '">' +
          '<span class="tv-search-thumb">' + (t.thumb ? '<img src="' + esc(t.thumb) + '" alt="" width="56" height="56" loading="lazy">' : '') + '</span>' +
          '<span class="min-w-0"><b>' + esc(t.title) + '</b><small>' + esc([t.date, t.country, t.location].filter(Boolean).join(' · ')) + '</small></span></a>';
      }).join('') + (data.total > data.items.length ? '<a class="tv-search-more" href="index.php?q=' + encodeURIComponent(q) + '#archive">전체 ' + data.total + '건 모두 보기 →</a>' : '');
      items = $$('.tv-search-item', box); setActive(0);
    };
    var run = function () {
      var q = input.value.trim();
      input.setAttribute('aria-expanded', q ? 'true' : 'false');
      if (!q) { box.hidden = true; idle.hidden = false; items = []; active = -1; return; }
      var my = ++seq;
      fetch('search.php?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (my !== seq) return; idle.hidden = true; box.hidden = false; render(d); })
        .catch(function () { if (my !== seq) return; box.hidden = false; idle.hidden = true; box.innerHTML = '<p class="tv-search-empty">검색하지 못했습니다. Enter 로 전체 검색을 시도해 보세요.</p>'; });
    };
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 140); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); setActive((active + 1) % items.length); }
      else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); setActive((active - 1 + items.length) % items.length); }
      else if (e.key === 'Enter' && items[active] && !e.isComposing) { e.preventDefault(); location.href = items[active].href; }   // 선택된 결과가 있으면 바로 이동
    });
    dlg.addEventListener('close', function () { input.value = ''; box.hidden = true; idle.hidden = false; items = []; active = -1; });
  })();

  /* ── 최근 여행 가로 줄: 좌우 버튼 + 끝 감지 ── */
  (function () {
    var rail = $('#recentRail'); if (!rail) return;
    var prev = $('[data-rail="-1"]'), next = $('[data-rail="1"]');
    var step = function () { var c = rail.querySelector('.tv-poster'); return c ? c.getBoundingClientRect().width + 20 : 320; };
    var upd = function () {
      if (prev) prev.disabled = rail.scrollLeft < 8;
      if (next) next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 8;
    };
    [prev, next].forEach(function (b) { if (b) b.addEventListener('click', function () { rail.scrollBy({ left: step() * (+b.dataset.rail) * 1.5, behavior: reduce ? 'auto' : 'smooth' }); }); });
    rail.addEventListener('scroll', function () { window.requestAnimationFrame(upd); }, { passive: true });
    window.addEventListener('resize', upd); upd();
    // 마우스 드래그로 넘기기 (터치는 기본 스크롤)
    var down = false, sx = 0, sl = 0, moved = false;
    rail.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse' || e.button !== 0) return; down = true; moved = false; sx = e.clientX; sl = rail.scrollLeft; });
    window.addEventListener('pointermove', function (e) {
      if (!down) return; var dx = e.clientX - sx;
      if (Math.abs(dx) > 6) { moved = true; rail.style.scrollSnapType = 'none'; rail.style.cursor = 'grabbing'; }
      if (moved) rail.scrollLeft = sl - dx;
    });
    window.addEventListener('pointerup', function () { if (!down) return; down = false; rail.style.cursor = ''; if (moved) { rail.style.scrollSnapType = ''; } });
    rail.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);   // 드래그 끝에 링크가 열리지 않도록
  })();

  /* ── 히어로 숫자 카운트업 ── */
  (function () {
    var nums = $$('[data-count]'); if (!nums.length) return;
    var ease = function (t) { return 1 - Math.pow(1 - t, 3); };
    var run = function (el) {
      var to = parseInt(el.dataset.count, 10) || 0, t0 = null;
      if (reduce || to < 2) { el.textContent = to.toLocaleString(); return; }
      var step = function (t) { if (t0 === null) t0 = t; var p = Math.min(1, (t - t0) / 1100); el.textContent = Math.round(to * ease(p)).toLocaleString(); if (p < 1) requestAnimationFrame(step); };
      el.textContent = '0'; requestAnimationFrame(step);
    };
    if (!('IntersectionObserver' in window)) return;
    var io = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) { io.unobserve(x.target); run(x.target); } }); }, { threshold: .6 });
    nums.forEach(function (n) { io.observe(n); });
  })();

  /* ── 아카이브 필터: 나라 · 연도 · 정렬 (결과 수 갱신) ── */
  var filters = $('#countryFilters'), grid = $('#tripGrid');
  if (filters && grid) {
    var cards = $$('article[data-country]', grid);
    var count = $('#searchResultCount'), empty = $('#tripEmpty'), yearSel = $('#yearFilter'), sortBtn = $('#sortToggle');
    var query = (count && count.dataset.query) || '';
    var state = { country: 'all', year: 'all', sort: 'desc' };
    var apply = function () {
      var n = 0;
      cards.forEach(function (c) {
        var show = (state.country === 'all' || c.dataset.country === state.country) && (state.year === 'all' || c.dataset.year === state.year);
        c.hidden = !show; if (show) n++;
      });
      var dir = state.sort === 'desc' ? -1 : 1;
      cards.slice().sort(function (a, b) { return a.dataset.date < b.dataset.date ? dir * -1 : a.dataset.date > b.dataset.date ? dir : 0; }).forEach(function (c) { grid.appendChild(c); });
      $$('button[data-country]', filters).forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.country === state.country ? 'true' : 'false'); });
      if (sortBtn) sortBtn.textContent = state.sort === 'desc' ? '최신순 ↓' : '오래된순 ↑';
      if (count) count.textContent = (query ? '“' + query + '” 검색 결과 ' : '') + n + '개의 여행';
      if (empty) empty.hidden = n !== 0;
    };
    filters.addEventListener('click', function (e) { var b = e.target.closest('button[data-country]'); if (b) { state.country = b.dataset.country; apply(); } });
    if (yearSel) yearSel.addEventListener('change', function () { state.year = yearSel.value; apply(); });
    if (sortBtn) sortBtn.addEventListener('click', function () { state.sort = state.sort === 'desc' ? 'asc' : 'desc'; sortBtn.dataset.sort = state.sort; apply(); });
    apply();
  }

  /* 맨 위로 버튼 — 상단 센티널이 화면에서 벗어나면 표시 */
  var top = document.getElementById('backToTop');
  if (top) {
    var show = function (v) {
      top.style.opacity = v ? '1' : '0';
      top.style.transform = v ? 'translateY(0)' : 'translateY(12px)';
      top.style.pointerEvents = v ? 'auto' : 'none';
    };
    if ('IntersectionObserver' in window) {
      var s = document.createElement('div');
      s.setAttribute('aria-hidden', 'true');
      s.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:480px;pointer-events:none;visibility:hidden';
      document.body.appendChild(s);
      new IntersectionObserver(function (en) { show(!en[0].isIntersecting); }).observe(s);
    } else show(true);
    top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });
  }

  /* 사진 갤러리 (PhotoSwipe) — 첫 클릭 때 gallery.js / gallery.css 를 불러옵니다.
     이벤트 위임: "더 보기"로 나중에 추가된 사진도 그대로 열립니다. */
  var galleryMod = null, galLoading = false;
  var loadGallery = function () {
    if (galleryMod) return galleryMod;
    if (!document.querySelector('link[data-gallery-css]')) {
      var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = 'assets/gallery.css?v=' + (window.TV_GALLERY_V || 1); l.setAttribute('data-gallery-css', ''); document.head.appendChild(l);
    }
    galleryMod = import('./gallery.js?v=' + (window.TV_GALLERY_V || 1));
    return galleryMod;
  };
  var warm = function () { if (!galLoading) { galLoading = true; loadGallery().catch(function () { galLoading = false; galleryMod = null; }); } };
  var photoSel = 'a.photo-link[data-pswp-width]';
  document.addEventListener('pointerover', function (e) { if (e.target.closest && e.target.closest(photoSel)) warm(); }, { passive: true });
  document.addEventListener('touchstart', function (e) { if (e.target.closest && e.target.closest(photoSel)) warm(); }, { passive: true });
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest(photoSel);
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
    e.preventDefault();
    var links = Array.prototype.slice.call(document.querySelectorAll(photoSel));
    var i = links.indexOf(a);
    loadGallery().then(function (m) { m.open(links, i); }).catch(function () { window.open(a.href, '_blank', 'noopener'); });
  });

  /* 사진 모아보기: 스크롤하면 이어서 불러오기 */
  var galGrid = document.getElementById('galGrid'), galMore = document.getElementById('galMore'), galBtn = document.getElementById('galMoreBtn');
  if (galGrid && galBtn) {
    var galBusy = false;
    var galNext = function () {
      var url = galBtn.getAttribute('data-next');
      if (galBusy || !url) return;
      galBusy = true; galBtn.textContent = '불러오는 중…';
      fetch(url, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); }).then(function (html) {
        var box = document.createElement('div'); box.innerHTML = html;
        var tpl = box.querySelector('template[data-next]');
        var next = tpl ? tpl.getAttribute('data-next') : '';
        if (tpl) tpl.remove();
        while (box.firstChild) galGrid.appendChild(box.firstChild);
        if (next) { galBtn.setAttribute('data-next', next); galBtn.setAttribute('href', next.replace(/[?&]frag=1/, '').replace(/^gallery\.php&/, 'gallery.php?')); galBtn.textContent = '사진 더 보기'; }
        else { galBtn.removeAttribute('data-next'); galMore.hidden = true; }
        galBusy = false;
      }).catch(function () { galBusy = false; galBtn.textContent = '다시 시도'; });
    };
    galBtn.addEventListener('click', function (e) { e.preventDefault(); galNext(); });
    if ('IntersectionObserver' in window) new IntersectionObserver(function (en) { if (en[0].isIntersecting) galNext(); }, { rootMargin: '600px 0px' }).observe(galMore);
  }

  /* 공유 · 인쇄 */
  var toastEl = null, toastT = 0;
  var toast = function (msg) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'tv-toast'; toastEl.setAttribute('role', 'status'); document.body.appendChild(toastEl); }
    toastEl.textContent = msg; toastEl.classList.add('is-on');
    clearTimeout(toastT); toastT = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2200);
  };
  document.addEventListener('click', function (e) {
    var sh = e.target.closest && e.target.closest('[data-share]');
    if (sh) {
      var c = document.querySelector('link[rel="canonical"]'), url = c ? c.href : location.href, title = sh.getAttribute('data-title') || document.title;
      if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
      var done = function () { toast('링크를 복사했어요'); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(done, function () { window.prompt('링크를 복사하세요', url); });
      else window.prompt('링크를 복사하세요', url);
      return;
    }
    if (e.target.closest && e.target.closest('[data-print]')) window.print();
  });
  window.addEventListener('beforeprint', function () { document.querySelectorAll('img[loading="lazy"]').forEach(function (im) { im.loading = 'eager'; }); });

  /* PWA: 서비스 워커(오프라인 안내·재방문 속도) + 홈 화면 설치 버튼 */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); });
  }
  var installEv = null, installBtns = document.querySelectorAll('[data-install]');
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); installEv = e; installBtns.forEach(function (b) { b.hidden = false; }); });
  installBtns.forEach(function (b) { b.addEventListener('click', function () { if (!installEv) return; installEv.prompt(); installEv.userChoice.finally(function () { installEv = null; b.hidden = true; }); }); });
  window.addEventListener('appinstalled', function () { installBtns.forEach(function (b) { b.hidden = true; }); });

  /* 다크 모드 토글 — 저장된 선택이 없으면 시스템 설정을 따름(초기값은 <head> 스크립트가 지정) */
  var syncToggle = function () {
    var dark = root.dataset.theme === 'dark';
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', dark ? 'true' : 'false'); });
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = getComputedStyle(document.body).backgroundColor;
  };
  document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = next;
      try { localStorage.setItem('naeggeolog-theme', next); } catch (e) {}
      syncToggle();
    });
  });
  syncToggle();

  /* 목차 하이라이트 (읽고 있는 섹션) + 지도 아래 진행 목차 + 읽기 진행 막대 */
  var tocLinks = Array.prototype.slice.call(document.querySelectorAll('.tv-toc a[href^="#"], .tv-toc-mini a[href^="#"]'));
  if (tocLinks.length && 'IntersectionObserver' in window) {
    var byId = {}, order = [];
    tocLinks.forEach(function (a) {
      var id = decodeURIComponent(a.getAttribute('href').slice(1));
      (byId[id] = byId[id] || []).push(a);
      if (order.indexOf(id) < 0) order.push(id);
    });
    var mini = document.querySelector('[data-toc-mini]'), posEl = document.querySelector('[data-toc-pos]');
    var article = document.querySelector('.tv-trip-body article');
    var setCurrent = function (id) {
      var idx = order.indexOf(id);
      tocLinks.forEach(function (a) {
        a.removeAttribute('aria-current'); a.classList.remove('is-read');
        var i = order.indexOf(decodeURIComponent(a.getAttribute('href').slice(1)));
        if (i < idx) a.classList.add('is-read');
      });
      byId[id].forEach(function (a) { a.setAttribute('aria-current', 'true'); });
      if (mini) {
        var cur = mini.querySelector('a[aria-current="true"]');
        if (cur && mini.classList.contains('is-on')) { var r = cur.getBoundingClientRect(), m = mini.getBoundingClientRect(); if (r.top < m.top + 34 || r.bottom > m.bottom - 4) mini.scrollTop += (r.top - m.top) - m.height / 2; }
      }
      updatePos();
    };
    var pct = function () {
      if (!article) return 0;
      var r = article.getBoundingClientRect(), h = r.height - window.innerHeight * 0.5;
      return Math.max(0, Math.min(100, Math.round((-r.top + window.innerHeight * 0.35) / Math.max(h, 1) * 100)));
    };
    var curIdx = -1;
    var updatePos = function () { if (posEl && mini) posEl.textContent = (curIdx + 1 > 0 ? (curIdx + 1) + ' / ' + order.length + ' · ' : '') + pct() + '%'; };
    var spy = new IntersectionObserver(function (en) {
      en.forEach(function (x) {
        if (!x.isIntersecting || !byId[x.target.id]) return;
        curIdx = order.indexOf(x.target.id); setCurrent(x.target.id);
      });
    }, { rootMargin: '-40% 0px -50% 0px' });
    order.forEach(function (id) { var sec = document.getElementById(id); if (sec) spy.observe(sec); });

    /* 본문 목차가 화면 위로 지나가면 지도 아래 진행 목차를 보여준다 */
    var bodyToc = document.querySelector('.tv-toc');
    if (mini && bodyToc) {
      new IntersectionObserver(function (en) {
        var x = en[0]; mini.classList.toggle('is-on', !x.isIntersecting && x.boundingClientRect.bottom < 90);
      }, { rootMargin: '-80px 0px 0px 0px' }).observe(bodyToc);
      var tk = 0; window.addEventListener('scroll', function () { if (tk) return; tk = requestAnimationFrame(function () { tk = 0; if (mini.classList.contains('is-on')) updatePos(); }); }, { passive: true });
    }
  }

  /* 읽기 진행 막대: 스크롤 기반 애니메이션이 없는 브라우저(Firefox 등)용 대체 */
  var bar = document.querySelector('.tv-progress');
  if (bar && !(window.CSS && CSS.supports && CSS.supports('animation-timeline: scroll()'))) {
    var bt = 0, upd = function () { bt = 0; var h = document.documentElement.scrollHeight - window.innerHeight; bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, window.scrollY / h) : 0) + ')'; };
    window.addEventListener('scroll', function () { if (!bt) bt = requestAnimationFrame(upd); }, { passive: true }); upd();
  }

  /* 페이지 전환: 카드 사진 ↔ 상세 커버가 이어지도록 이름 부여 (Chrome/Safari 등 cross-document VT 지원 시) */
  if ('onpagereveal' in window && window.navigation) {
    var isTrip = function (u) { return /\/trip\.php$/.test(u.pathname); };
    var cardImg = function (id) {
      var c = Array.prototype.find.call(document.querySelectorAll('.tv-card[data-trip]'), function (x) { return x.dataset.trip === id; });
      return c && c.querySelector('.tv-thumb img');
    };
    var cover = function () { return document.querySelector('[data-vt-cover] img'); };
    window.addEventListener('pageswap', function (e) {
      if (!e.viewTransition || !e.activation || !e.activation.entry) return;
      var to = new URL(e.activation.entry.url), here = location;
      if (isTrip(to) && !isTrip(here)) { var im = cardImg(to.searchParams.get('id')); if (im) im.style.viewTransitionName = 'trip-cover'; }
      else if (isTrip(here) && !isTrip(to)) { var cv = cover(); if (cv) cv.style.viewTransitionName = 'trip-cover'; }
    });
    window.addEventListener('pagereveal', function (e) {
      if (!e.viewTransition || !navigation.activation || !navigation.activation.from) return;
      var from = new URL(navigation.activation.from.url);
      if (isTrip(location) && !isTrip(from)) { var cv = cover(); if (cv) cv.style.viewTransitionName = 'trip-cover'; }
      else if (!isTrip(location) && isTrip(from)) { var im = cardImg(from.searchParams.get('id')); if (im) im.style.viewTransitionName = 'trip-cover'; }
    });
  }

  /* 본문 안의 외부 링크는 새 탭으로 */
  document.querySelectorAll('.story a[href]').forEach(function (a) {
    var h = a.getAttribute('href') || '';
    if (h && h.charAt(0) !== '#' && !/^javascript:/i.test(h)) { a.target = '_blank'; a.rel = 'noopener noreferrer'; }
  });
})();

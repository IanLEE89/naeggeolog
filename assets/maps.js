/* Naeggeolog — Google Maps (홈 여행 지도 + 여행 상세 이동경로)
 * - Google 스크립트는 지도가 화면 근처에 왔을 때만 로드(초기 로딩·API 호출량 절약)
 * - 마커/팝업은 DOM API(textContent)로 만들어 XSS 위험 없음
 */
(function () {
  'use strict';
  var cfg = window.TRAVEL_MAPS || {};
  var homeEl = document.getElementById('worldMap');
  var routeEl = document.getElementById('tripRouteMap');
  if (!homeEl && !routeEl) return;

  function fail(el, msg) {
    if (!el) return;
    el.innerHTML = '';
    var d = document.createElement('div');
    d.style.cssText = 'display:flex;height:100%;min-height:160px;align-items:center;justify-content:center;padding:24px;text-align:center;font-size:13px;line-height:1.7;color:#78716c';
    d.textContent = msg;
    el.appendChild(d);
  }
  var targets = [homeEl, routeEl].filter(Boolean);
  if (!cfg.key) { targets.forEach(function (el) { fail(el, 'Google Maps API 키가 설정되지 않았습니다. (관리자 → 홈페이지 설정)'); }); return; }
  window.gm_authFailure = function () {
    targets.forEach(function (el) { fail(el, 'Google Maps 인증에 실패했습니다. API 키·결제 설정·HTTP 리퍼러 제한을 확인하세요.'); });
  };

  // Google 공식 동적 로더(부트스트랩): importLibrary 가 처음 호출될 때 스크립트를 로드
  (function (g) {
    var h, a, k, p = 'The Google Maps JavaScript API', c = 'google', l = 'importLibrary', q = '__ib__', m = document, b = window;
    b = b[c] || (b[c] = {});
    var d = b.maps || (b.maps = {}), r = new Set(), e = new URLSearchParams();
    var u = function () {
      return h || (h = new Promise(function (f, n) {
        a = m.createElement('script');
        e.set('libraries', Array.from(r) + '');
        for (k in g) e.set(k.replace(/[A-Z]/g, function (t) { return '_' + t[0].toLowerCase(); }), g[k]);
        e.set('callback', c + '.maps.' + q);
        a.src = 'https://maps.' + c + 'apis.com/maps/api/js?' + e;
        d[q] = f;
        a.onerror = function () { h = n(Error(p + ' could not load.')); };
        m.head.append(a);
      }));
    };
    if (d[l]) console.warn(p + ' only loads once. Ignoring:', g);
    else d[l] = function (f) { var n = Array.prototype.slice.call(arguments, 1); r.add(f); return u().then(function () { return d[l].apply(d, [f].concat(n)); }); };
  })({ key: cfg.key, v: 'weekly', language: 'ko' });

  var isDark = document.documentElement.dataset.theme === 'dark';
  var colorScheme = isDark ? 'DARK' : 'LIGHT';

  // 클러스터링 라이브러리는 핀이 2개 이상일 때만, 지도가 열린 뒤 불러온다
  function loadClusterer() {
    if (window.markerClusterer) return Promise.resolve(window.markerClusterer);
    return new Promise(function (resolve, reject) {
      if (!cfg.clusterer) return reject(new Error('no clusterer'));
      var sc = document.createElement('script'); sc.src = cfg.clusterer;
      sc.onload = function () { window.markerClusterer ? resolve(window.markerClusterer) : reject(new Error('clusterer missing')); };
      sc.onerror = function () { reject(new Error('clusterer load failed')); };
      document.head.appendChild(sc);
    });
  }

  function cssVar(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }
  function safeUrl(u) { return /^https?:\/\//i.test(u || '') ? u : ''; }
  function el(tag, props, text) {
    var n = document.createElement(tag);
    if (props) Object.keys(props).forEach(function (k) { n.setAttribute(k, props[k]); });
    if (text != null) n.textContent = text;
    return n;
  }
  function pinStyle(PinElement, glyph, color) {
    return new PinElement({ background: color, borderColor: '#ffffff', glyphColor: '#ffffff', glyph: glyph || undefined, scale: glyph ? 1.15 : 1 });
  }

  /* ───── 홈: 전체 여행 지도 ───── */
  async function initHome(node) {
    var libs = await Promise.all([google.maps.importLibrary('maps'), google.maps.importLibrary('marker')]);
    var GMap = libs[0].Map, InfoWindow = libs[0].InfoWindow, LatLngBounds = libs[0].LatLngBounds || google.maps.LatLngBounds;
    var Marker = libs[1].AdvancedMarkerElement, PinElement = libs[1].PinElement;
    var trips = (window.TRIPS || []).filter(function (t) { return isFinite(t.lat) && isFinite(t.lng); });
    var map = new GMap(node, {
      center: { lat: 26, lng: 20 }, zoom: 2, minZoom: 2, mapId: cfg.mapId, colorScheme: colorScheme,
      gestureHandling: 'cooperative', mapTypeControl: false, streetViewControl: false, fullscreenControl: true
    });
    if (!trips.length) return;

    // 같은 장소(소수점 3자리 ≈ 100m)의 여행은 마커 하나로 묶어서 목록으로 보여줌
    var groups = {};
    trips.forEach(function (t) {
      var key = Number(t.lat).toFixed(3) + ',' + Number(t.lng).toFixed(3);
      (groups[key] = groups[key] || []).push(t);
    });
    var accent = cssVar('--theme-accent', '#3368a0');
    var info = new InfoWindow({ maxWidth: 280 });
    var bounds = new LatLngBounds();
    var markers = [];
    Object.keys(groups).forEach(function (key) {
      var list = groups[key], first = list[0];
      var pos = { lat: Number(first.lat), lng: Number(first.lng) };
      var marker = new Marker({
        position: pos, title: first.location || first.title,
        content: pinStyle(PinElement, list.length > 1 ? String(list.length) : '', accent).element
      });
      marker.addListener('click', function () {
        var box = el('div'); box.style.cssText = 'font:13px/1.6 -apple-system,"Noto Sans KR",sans-serif;min-width:180px;color:#292824';
        box.appendChild(el('div', { style: 'font-size:11px;color:#78716c;margin-bottom:4px' }, (first.country || '') + ' · ' + (first.location || '')));
        list.forEach(function (t) {
          var a = el('a', { href: 'trip.php?id=' + encodeURIComponent(t.id), style: 'display:block;padding:5px 0;color:inherit;text-decoration:none;border-top:1px solid #eee' });
          a.appendChild(el('b', null, t.title));
          a.appendChild(el('div', { style: 'font-size:11px;color:#78716c' }, (t.date || '') + ' · 여행기 보기 →'));
          box.appendChild(a);
        });
        info.setContent(box); info.open({ anchor: marker, map: map });
      });
      markers.push(marker);
      bounds.extend(pos);
    });

    // 가까운 핀은 확대 수준에 따라 숫자 원으로 묶음(클릭하면 그 영역으로 확대). 실패하면 일반 핀으로 표시.
    var clustered = false;
    if (markers.length > 1) {
      try {
        var MC = await loadClusterer();
        new MC.MarkerClusterer({
          map: map, markers: markers,
          algorithm: new MC.SuperClusterAlgorithm({ radius: 70, maxZoom: 11 }),
          renderer: {
            render: function (c) {
              var n = c.count, size = Math.min(54, 34 + Math.round(Math.log2(n) * 6));
              var d = document.createElement('div');
              d.style.cssText = 'display:flex;align-items:center;justify-content:center;border-radius:50%;color:#fff;font:700 13px -apple-system,"Noto Sans KR",sans-serif;cursor:pointer;' +
                'width:' + size + 'px;height:' + size + 'px;background:' + accent + ';border:3px solid rgba(255,255,255,.9);box-shadow:0 2px 8px rgba(0,0,0,.28)';
              d.textContent = String(n);
              return new Marker({ position: c.position, content: d, zIndex: 1000 + n, title: n + '개의 핀' });
            }
          }
        });
        clustered = true;
      } catch (e) { console.warn('클러스터링을 사용할 수 없어 일반 핀으로 표시합니다.', e); }
    }
    if (!clustered) markers.forEach(function (m) { m.map = map; });

    if (markers.length === 1) { map.setCenter(bounds.getCenter()); map.setZoom(6); }
    else map.fitBounds(bounds, 70);
  }

  /* ───── 여행 상세: 이동경로 (스크롤 연동) ─────
   * 본문 섹션이 화면 중앙에 오면 해당 마커를 강조하고, 지도를 그 장소로 부드럽게 이동하며,
   * 지금까지 지나온 경로를 실선으로 그린다. 마커를 누르면 본문 해당 위치로 스크롤. */
  async function initRoute(node) {
    var points = [];
    try { points = JSON.parse(node.dataset.points || '[]'); } catch (e) { points = []; }
    points = points.filter(function (p) { return isFinite(p.lat) && isFinite(p.lng); });
    if (!points.length) { var aside = node.closest('aside'); (aside || node).style.display = 'none'; return; }
    var libs = await Promise.all([google.maps.importLibrary('maps'), google.maps.importLibrary('marker')]);
    var GMap = libs[0].Map, InfoWindow = libs[0].InfoWindow, Polyline = libs[0].Polyline || google.maps.Polyline;
    var Marker = libs[1].AdvancedMarkerElement, PinElement = libs[1].PinElement;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var map = new GMap(node, {
      center: { lat: Number(points[0].lat), lng: Number(points[0].lng) }, zoom: 14, mapId: cfg.mapId, colorScheme: colorScheme,
      gestureHandling: 'cooperative', mapTypeControl: false, streetViewControl: false, fullscreenControl: true
    });
    var info = new InfoWindow({ maxWidth: 260 });
    var path = [], bounds = new google.maps.LatLngBounds();
    var base = isDark ? '#9ca3af' : '#57534e', accent = cssVar('--theme-accent', '#3368a0');
    var pins = [], markers = [];

    points.forEach(function (p, i) {
      var pos = { lat: Number(p.lat), lng: Number(p.lng) };
      path.push(pos); bounds.extend(pos);
      var pin = new PinElement({ background: base, borderColor: '#ffffff', glyphColor: '#ffffff', glyph: String(i + 1) });
      var m = new Marker({ map: map, position: pos, title: p.title || p.location || '', content: pin.element });
      pins.push(pin); markers.push(m);
      m.addListener('click', function () {
        var box = el('div'); box.style.cssText = 'font:13px/1.6 -apple-system,"Noto Sans KR",sans-serif;color:#292824';
        box.appendChild(el('b', null, String(i + 1).padStart(2, '0') + '. ' + (p.title || '')));
        var name = p.location || p.title || '장소', url = safeUrl(p.url);
        var line = el('div');
        if (url) line.appendChild(el('a', { href: url, target: '_blank', rel: 'noopener noreferrer', style: 'color:inherit;text-decoration:underline' }, name + ' ↗'));
        else line.textContent = name;
        box.appendChild(line);
        var target = p.anchor && document.getElementById(p.anchor);
        if (target) {
          var go = el('a', { href: '#' + p.anchor, style: 'display:inline-block;margin-top:6px;color:#3368a0' }, '본문으로 이동 ↓');
          go.addEventListener('click', function (ev) { ev.preventDefault(); info.close(); target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); });
          box.appendChild(go);
        }
        info.setContent(box); info.open({ anchor: m, map: map });
      });
    });

    var done = null;
    if (path.length > 1) {
      new Polyline({
        map: map, path: path, strokeOpacity: 0, geodesic: false,
        icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 0.6, strokeColor: base, scale: 2.5 }, offset: '0', repeat: '14px' }]
      });
      done = new Polyline({ map: map, path: [], strokeColor: accent, strokeOpacity: 0.95, strokeWeight: 4, zIndex: 5 });
      map.fitBounds(bounds, 40);
    } else { map.setCenter(path[0]); map.setZoom(15); }

    var current = -1;
    function activate(i) {
      if (i === current || i < 0 || i >= pins.length) return;
      if (current >= 0) { pins[current].background = base; pins[current].scale = 1; markers[current].zIndex = 1; }
      current = i;
      pins[i].background = accent; pins[i].scale = 1.45; markers[i].zIndex = 10;
      if (done) done.setPath(path.slice(0, i + 1));
      var b = map.getBounds();
      if (!b || !b.contains(path[i])) { if (reduce) map.setCenter(path[i]); else map.panTo(path[i]); }
    }

    // 섹션이 화면 중앙 띠(위 40% ~ 아래 50% 제외)에 들어오면 활성화
    var secs = Array.prototype.slice.call(document.querySelectorAll('[data-route-index]'));
    if ('IntersectionObserver' in window && secs.length) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) activate(parseInt(en.target.getAttribute('data-route-index'), 10)); });
      }, { rootMargin: '-40% 0px -50% 0px' });
      secs.forEach(function (sec) { io.observe(sec); });
    }
  }

  // 화면 근처에 왔을 때만 초기화
  function whenNear(node, fn) {
    var run = function () { fn(node).catch(function (e) { console.error(e); fail(node, '지도를 불러오지 못했습니다. 잠시 후 다시 시도해주세요.'); }); };
    if (!('IntersectionObserver' in window)) { run(); return; }
    var io = new IntersectionObserver(function (entries) {
      if (entries.some(function (x) { return x.isIntersecting; })) { io.disconnect(); run(); }
    }, { rootMargin: '400px 0px' });
    io.observe(node);
  }
  if (homeEl) whenNear(homeEl, initHome);
  if (routeEl) whenNear(routeEl, initRoute);
})();

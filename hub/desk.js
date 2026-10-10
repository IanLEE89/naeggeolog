/* 내 공간 홈(데스크톱) — 배경화면 · 기능 창 · 업무 위젯
 * 위젯 데이터는 desk-api.php 로 사용자별 저장(어느 기기에서나 같음). 집중 타이머 진행 상태만 이 브라우저에 저장.
 * 외부 요청: 날씨 위젯만 Open-Meteo(무료·키 없음)에 지역 좌표를 보냅니다.
 */
(function () {
  'use strict';
  var boot = JSON.parse(document.getElementById('dk-boot').textContent);
  var D = boot.data || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var LS = {
    get: function (k, d) { try { var v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  };
  var uid = function () { return Date.now().toString(36) + Math.random().toString(36).slice(2, 7); };

  /** el('div.cls', {attr}, [children|text]) */
  function el(tag, attrs, kids) {
    var p = tag.split('.'), n = document.createElement(p[0] || 'div');
    if (p.length > 1) n.className = p.slice(1).join(' ');
    if (attrs) for (var k in attrs) {
      var v = attrs[k];
      if (v == null || v === false) continue;
      if (k === 'text') n.textContent = v;
      else if (k === 'html') n.innerHTML = v;
      else if (k.slice(0, 2) === 'on') n.addEventListener(k.slice(2), v);
      else if (k === 'style') n.style.cssText = v;
      else n.setAttribute(k, v === true ? '' : v);
    }
    (Array.isArray(kids) ? kids : kids != null ? [kids] : []).forEach(function (c) {
      if (c == null || c === false) return;
      n.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
    });
    return n;
  }
  var SV = function (p, cls) { return '<svg class="' + (cls || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
  var IC = {
    plus: '<path d="M12 5v14M5 12h14"/>', x: '<path d="M6 6l12 12M18 6 6 18"/>', star: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
    left: '<path d="m15 6-6 6 6 6"/>', right: '<path d="m9 6 6 6-6 6"/>', pin: '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
    play: '<path d="M7 4.5v15l12-7.5z"/>', pause: '<path d="M8 5v14M16 5v14"/>', reset: '<path d="M4 12a8 8 0 1 0 2.3-5.6M4 4v4h4"/>', skip: '<path d="M5 5v14l10-7zM19 5v14"/>',
    edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/>', copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h10"/>'
  };

  /* ───────── 저장 ───────── */
  var pending = {}, saveTimer = null;
  function save(key, value, now) {
    D[key] = value;
    pending[key] = true;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(flush, now ? 0 : 700);
  }
  function flush(keepalive) {
    Object.keys(pending).forEach(function (key) {
      delete pending[key];
      fetch('desk-api.php', {
        method: 'POST', credentials: 'same-origin', keepalive: keepalive === true,
        headers: { 'Content-Type': 'application/json', 'X-CSRF': boot.csrf },
        body: JSON.stringify({ key: key, value: D[key] })
      }).then(function (r) {
        if (r.ok) return;
        pending[key] = true;
        if (r.status === 401) toast('로그인이 만료됐어요. 새 탭에서 다시 로그인하면 자동으로 저장됩니다.', 'login.php');
        else if (r.status === 413) { delete pending[key]; toast('저장할 내용이 너무 많아요. 오래된 항목을 정리해 주세요.'); }
        else toast('저장하지 못했어요. 잠시 후 다시 시도합니다.');
      }).catch(function () { pending[key] = true; toast('네트워크 오류로 저장하지 못했어요. 연결되면 다시 저장합니다.'); });
    });
  }
  window.addEventListener('focus', function () { if (Object.keys(pending).length) flush(); });
  window.addEventListener('online', flush);
  // 창을 닫거나 다른 페이지로 갈 때 남은 저장을 마저 보냄
  window.addEventListener('pagehide', function () { clearTimeout(saveTimer); if (Object.keys(pending).length) flush(true); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && Object.keys(pending).length) { clearTimeout(saveTimer); flush(true); } });

  var toastEl = null, toastT = null;
  function toast(msg, link) {
    if (!toastEl) { toastEl = el('div.dk-toast', { role: 'status' }); document.body.appendChild(toastEl); }
    toastEl.innerHTML = '';
    toastEl.appendChild(document.createTextNode(msg));
    if (link) toastEl.appendChild(el('a', { href: link, target: '_blank', text: ' 로그인 ↗' }));
    toastEl.classList.add('is-on');
    clearTimeout(toastT); toastT = setTimeout(function () { toastEl.classList.remove('is-on'); }, 5000);
  }

  /* ───────── 날짜 · 공휴일 ───────── */
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var ymd = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
  var parseYmd = function (s) { var p = String(s).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
  var WD = ['일', '월', '화', '수', '목', '금', '토'];
  var today = function () { return ymd(new Date()); };
  var daysBetween = function (a, b) { return Math.round((parseYmd(b) - parseYmd(a)) / 864e5); };
  // 대한민국 공휴일(대체공휴일 포함). 해가 바뀌면 다음 해 날짜를 추가하세요.
  var HOLI = {
    '2025-01-01': '신정', '2025-01-27': '임시공휴일', '2025-01-28': '설날 연휴', '2025-01-29': '설날', '2025-01-30': '설날 연휴', '2025-03-01': '삼일절', '2025-03-03': '대체공휴일',
    '2025-05-05': '어린이날·부처님오신날', '2025-05-06': '대체공휴일', '2025-06-03': '대통령 선거일', '2025-06-06': '현충일', '2025-08-15': '광복절', '2025-10-03': '개천절',
    '2025-10-05': '추석 연휴', '2025-10-06': '추석', '2025-10-07': '추석 연휴', '2025-10-08': '대체공휴일', '2025-10-09': '한글날', '2025-12-25': '성탄절',
    '2026-01-01': '신정', '2026-02-16': '설날 연휴', '2026-02-17': '설날', '2026-02-18': '설날 연휴', '2026-03-01': '삼일절', '2026-03-02': '대체공휴일',
    '2026-05-05': '어린이날', '2026-05-24': '부처님오신날', '2026-05-25': '대체공휴일', '2026-06-03': '지방선거일', '2026-06-06': '현충일', '2026-08-15': '광복절', '2026-08-17': '대체공휴일',
    '2026-09-24': '추석 연휴', '2026-09-25': '추석', '2026-09-26': '추석 연휴', '2026-10-03': '개천절', '2026-10-05': '대체공휴일', '2026-10-09': '한글날', '2026-12-25': '성탄절',
    '2027-01-01': '신정', '2027-02-06': '설날 연휴', '2027-02-07': '설날', '2027-02-08': '설날 연휴', '2027-02-09': '대체공휴일', '2027-03-01': '삼일절',
    '2027-05-05': '어린이날', '2027-05-13': '부처님오신날', '2027-06-06': '현충일', '2027-08-15': '광복절', '2027-08-16': '대체공휴일',
    '2027-09-14': '추석 연휴', '2027-09-15': '추석', '2027-09-16': '추석 연휴', '2027-10-03': '개천절', '2027-10-04': '대체공휴일', '2027-10-09': '한글날', '2027-10-11': '대체공휴일',
    '2027-12-25': '성탄절', '2027-12-27': '대체공휴일'
  };

  /* ───────── 시계 ───────── */
  var lastDay = today();
  function tickClock() {
    var d = new Date();
    $('#dk-time').textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    $('#dk-date').textContent = d.getFullYear() + '년 ' + (d.getMonth() + 1) + '월 ' + d.getDate() + '일 ' + WD[d.getDay()] + '요일';
    var h = HOLI[ymd(d)];
    $('#dk-holi').textContent = h || '';
    $('#dk-holi').hidden = !h;
    if (ymd(d) !== lastDay) { lastDay = ymd(d); Object.keys(W).forEach(function (k) { if (W[k].onNewDay) W[k].onNewDay(); }); }
  }
  tickClock();
  setInterval(tickClock, 1000);

  /* ───────── 배경화면 ───────── */
  var PRESETS = {
    linen:  { name: '리넨',   tone: 'light', bg: 'radial-gradient(120% 90% at 10% 0%,#fbf6ec 0%,transparent 60%),radial-gradient(90% 80% at 100% 100%,#e6ddcc 0%,transparent 60%),#f1ebe0' },
    dawn:   { name: '새벽',   tone: 'light', bg: 'radial-gradient(90% 70% at 15% 10%,#ffd9c7 0%,transparent 60%),radial-gradient(80% 70% at 90% 30%,#cfd8f0 0%,transparent 60%),radial-gradient(90% 80% at 50% 100%,#f3e6f0 0%,transparent 70%),#eee6ec' },
    mint:   { name: '민트',   tone: 'light', bg: 'radial-gradient(100% 80% at 0% 0%,#dff1e6 0%,transparent 60%),radial-gradient(90% 90% at 100% 100%,#cfe5ea 0%,transparent 60%),#e5efe9' },
    forest: { name: '숲',     tone: 'dark',  bg: 'radial-gradient(80% 70% at 85% 15%,#6f8f6a 0%,transparent 60%),radial-gradient(100% 90% at 0% 100%,#14271f 0%,transparent 70%),linear-gradient(160deg,#20392f,#3b5b4b)' },
    ocean:  { name: '바다',   tone: 'dark',  bg: 'radial-gradient(80% 70% at 80% 10%,#7fb3d5 0%,transparent 55%),radial-gradient(100% 90% at 0% 100%,#0b1f33 0%,transparent 70%),linear-gradient(165deg,#123a5c,#24648f)' },
    dusk:   { name: '노을',   tone: 'dark',  bg: 'radial-gradient(90% 70% at 85% 85%,#f2a57a 0%,transparent 55%),radial-gradient(80% 70% at 20% 20%,#5b3a6e 0%,transparent 60%),linear-gradient(160deg,#2a1f3d,#8a4a68)' },
    night:  { name: '밤',     tone: 'dark',  bg: 'radial-gradient(70% 60% at 75% 20%,#2c3e66 0%,transparent 60%),radial-gradient(60% 50% at 10% 90%,#1d2b45 0%,transparent 60%),#0d1220' },
    stone:  { name: '스톤',   tone: 'dark',  bg: 'radial-gradient(90% 80% at 80% 0%,#5a5752 0%,transparent 60%),linear-gradient(170deg,#2b2a27,#45423d)' }
  };
  var wallEl = $('#dk-wall'), wall = Object.assign({ type: 'preset', preset: 'linen', dim: 0, blur: 0 }, D.wall || {});
  if (wall.type === 'image' && !boot.wallImg) wall.type = 'preset';
  function lum(hex) {
    var n = parseInt(hex.slice(1), 16), c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(function (v) { v /= 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); });
    return .2126 * c[0] + .7152 * c[1] + .0722 * c[2];
  }
  function applyWall() {
    var tone = '', bg = 'none';
    if (wall.type === 'preset' && PRESETS[wall.preset]) { bg = PRESETS[wall.preset].bg; tone = PRESETS[wall.preset].tone; }
    else if (wall.type === 'color' && /^#[0-9a-f]{6}$/i.test(wall.color || '')) { bg = wall.color; tone = lum(wall.color) > .35 ? 'light' : 'dark'; }
    else if (wall.type === 'image' && boot.wallImg) { bg = 'url("' + boot.wallImg + '") center/cover no-repeat'; tone = 'dark'; }
    wallEl.style.setProperty('--dk-bg', bg);
    wallEl.style.setProperty('--dk-dim', String((+wall.dim || 0) / 100));
    wallEl.style.setProperty('--dk-blur', (+wall.blur || 0) + 'px');
    if (tone && +wall.dim >= 35) tone = 'dark';
    document.body.dataset.wall = wall.type === 'none' ? '' : 'on';
    document.body.dataset.tone = wall.type === 'none' ? '' : tone;
  }
  applyWall();

  var dlg = $('#dk-wall-dlg');
  function renderPresets() {
    var box = $('#dk-presets'); box.innerHTML = '';
    if (boot.wallImg) box.appendChild(el('button.dk-preset', { type: 'button', 'aria-pressed': String(wall.type === 'image'), style: 'background:url("' + boot.wallImg + '") center/cover', onclick: function () { wall.type = 'image'; commitWall(); } }, el('span', { text: '내 사진' })));
    Object.keys(PRESETS).forEach(function (k) {
      box.appendChild(el('button.dk-preset', { type: 'button', 'aria-pressed': String(wall.type === 'preset' && wall.preset === k), style: 'background:' + PRESETS[k].bg, 'data-tone': PRESETS[k].tone, onclick: function () { wall.type = 'preset'; wall.preset = k; commitWall(); } }, el('span', { text: PRESETS[k].name })));
    });
    $('#dk-wall-dim').value = wall.dim || 0;
    $('#dk-wall-blur').value = wall.blur || 0;
    if (wall.color) $('#dk-wall-color').value = wall.color;
  }
  function commitWall() { applyWall(); renderPresets(); save('wall', { type: wall.type, preset: wall.preset, color: wall.color, dim: +wall.dim || 0, blur: +wall.blur || 0 }); }
  $('#dk-wall-dim').addEventListener('input', function () { wall.dim = +this.value; commitWall(); });
  $('#dk-wall-blur').addEventListener('input', function () { wall.blur = +this.value; commitWall(); });
  $('#dk-wall-color').addEventListener('input', function () { wall.type = 'color'; wall.color = this.value; commitWall(); });
  $('#dk-wall-file').addEventListener('change', function () {
    var f = this.files[0], err = $('#dk-wall-err'); if (!f) return;
    err.hidden = true;
    var fd = new FormData(); fd.append('img', f); fd.append('csrf', boot.csrf);
    var lab = this.parentNode; lab.classList.add('is-busy'); lab.lastChild.textContent = '올리는 중…';
    fetch('desk-wall.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json().catch(function () { return { ok: false, error: '업로드 실패 (' + r.status + ')' }; }); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || '업로드 실패');
        boot.wallImg = j.url; wall.type = 'image'; if (!wall.dim) wall.dim = 15; commitWall();
      }).catch(function (e) { err.textContent = e.message; err.hidden = false; })
      .then(function () { lab.classList.remove('is-busy'); lab.lastChild.textContent = '내 사진 올리기'; });
    this.value = '';
  });

  /* ───────── 상단 도구 ───────── */
  var editing = false, wakeLock = null;
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var a = b.dataset.act;
    if (a === 'wall') { renderPresets(); dlg.showModal(); }
    else if (a === 'wall-none') { wall.type = 'none'; commitWall(); }
    else if (a === 'edit' || a === 'edit-done') setEdit(a === 'edit' ? !editing : false);
    else if (a === 'full') {
      if (document.fullscreenElement) document.exitFullscreen();
      else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().then(keepAwake).catch(function () {});
    }
    else if (a === 'wide') { var w = b.closest('.dk-w'); w.classList.toggle('is-wide'); saveLayout(); }
    else if (a === 'hide') { b.closest('.dk-w').hidden = true; saveLayout(); renderHiddenList(); }
    else if (a === 'win-min') minimizeWin();
    else if (a === 'win-max') $('#dk-win').classList.toggle('is-max');
    else if (a === 'win-close') closeWin(curWin);
  });
  // 전체 화면일 때는 화면이 꺼지지 않게(지원하는 브라우저만)
  function keepAwake() {
    if (!('wakeLock' in navigator) || wakeLock) return;
    navigator.wakeLock.request('screen').then(function (l) { wakeLock = l; l.addEventListener('release', function () { wakeLock = null; }); }).catch(function () {});
  }
  document.addEventListener('fullscreenchange', function () {
    var on = !!document.fullscreenElement;
    $('[data-act="full"] span').textContent = on ? '전체 화면 끝내기' : '전체 화면';
    if (!on && wakeLock) { wakeLock.release(); wakeLock = null; }
  });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible' && document.fullscreenElement) keepAwake(); });

  /* ───────── 위젯 배치 (편집 모드) ───────── */
  var grid = $('#dk-grid');
  function setEdit(on) {
    editing = on;
    document.body.classList.toggle('dk-editing', on);
    $('[data-act="edit"]').setAttribute('aria-pressed', String(on));
    $('#dk-edit-bar').hidden = !on;
    $$('.dk-w', grid).forEach(function (w) { w.draggable = on; });
    renderHiddenList();
  }
  function saveLayout() {
    save('layout', $$('.dk-w', grid).map(function (w) { return { id: w.dataset.w, size: w.classList.contains('is-wide') ? 2 : 1, hidden: w.hidden }; }));
  }
  function renderHiddenList() {
    var box = $('#dk-hidden-list'); box.innerHTML = '';
    $$('.dk-w[hidden]', grid).forEach(function (w) {
      box.appendChild(el('button.dk-chip', { type: 'button', onclick: function () { w.hidden = false; saveLayout(); renderHiddenList(); } }, ['+ ' + $('h2', w).textContent]));
    });
  }
  var dragW = null;
  grid.addEventListener('dragstart', function (e) {
    var w = e.target.closest && e.target.closest('.dk-w'); if (!editing || !w) return;
    dragW = w; w.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', w.dataset.w); } catch (x) {}
  });
  grid.addEventListener('dragover', function (e) {
    if (!dragW) return; e.preventDefault();
    var t = e.target.closest('.dk-w'); if (!t || t === dragW) return;
    var r = t.getBoundingClientRect(), after = (e.clientY - r.top) / r.height > .5 || (e.clientX - r.left) / r.width > .5 && (e.clientY - r.top) / r.height > .25;
    grid.insertBefore(dragW, after ? t.nextSibling : t);
  });
  grid.addEventListener('dragend', function () { if (!dragW) return; dragW.classList.remove('is-drag'); dragW = null; saveLayout(); });

  // 벽돌 배치: 위젯마다 실제 높이만큼 2px 행을 차지하게 해서 세로 빈틈을 없앰
  function fit(w) { if (!w.hidden) w.style.gridRowEnd = 'span ' + Math.ceil((w.getBoundingClientRect().height + 14) / 2); }
  if (window.ResizeObserver) { var ro = new ResizeObserver(function (en) { en.forEach(function (x) { fit(x.target); }); }); $$('.dk-w', grid).forEach(function (w) { ro.observe(w); }); }
  else grid.style.gridAutoRows = 'auto';

  /* ───────── 기능 창 (iframe) ───────── */
  var wins = {}, curWin = null, winEl = $('#dk-win');
  var appById = {}; (boot.apps || []).forEach(function (a) { appById[a.id] = a; });
  $$('.dk-app[data-app]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0 || matchMedia('(max-width:720px)').matches) return;
      e.preventDefault(); openWin(a.dataset.app);
    });
  });
  function openWin(id) {
    var app = appById[id]; if (!app) return;
    if (!wins[id]) {
      var f = el('iframe', { src: app.href, title: app.title, loading: 'eager' });
      f.addEventListener('load', function () { try { f.contentDocument.documentElement.classList.add('hb-embedded'); } catch (x) {} });
      wins[id] = { frame: f };
      $('#dk-win-stage').appendChild(f);
    }
    curWin = id;
    Object.keys(wins).forEach(function (k) { wins[k].frame.hidden = k !== id; });
    $('#dk-win-title').textContent = app.title;
    $('#dk-win-ico').innerHTML = SV(app.icon);
    $('#dk-win-ext').onclick = function (e) { e.preventDefault(); var href = app.href; try { href = wins[id].frame.contentWindow.location.href; } catch (x) {} window.open(href, '_blank'); };
    winEl.hidden = false;
    document.body.classList.add('dk-win-open');
    renderTasks();
  }
  function minimizeWin() { winEl.hidden = true; document.body.classList.remove('dk-win-open'); curWin = null; renderTasks(); }
  function closeWin(id) {
    if (id && wins[id]) { wins[id].frame.remove(); delete wins[id]; }
    minimizeWin();
  }
  function renderTasks() {
    var box = $('#dk-tasks'); box.innerHTML = '';
    Object.keys(wins).forEach(function (id) {
      var app = appById[id];
      box.appendChild(el('span.dk-task' + (id === curWin ? '.is-cur' : ''), null, [
        el('button', { type: 'button', title: app.title + ' 창 열기', html: SV(app.icon) + '<span>' + app.title.replace(/[<&]/g, '') + '</span>', onclick: function () { id === curWin ? minimizeWin() : openWin(id); } }),
        el('button.dk-task-x', { type: 'button', title: '창 닫기', html: SV(IC.x), onclick: function () { closeWin(id); } })
      ]));
    });
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !winEl.hidden && !dlg.open) minimizeWin(); });

  /* ───────── 위젯 ───────── */
  var W = {};
  function body(id) { return $('.dk-w[data-w="' + id + '"] .dk-w-body', grid); }
  function extra(id) { return $('.dk-w[data-w="' + id + '"] .dk-w-extra', grid); }
  function list(key) { var v = D[key]; return v && Array.isArray(v.items) ? v.items : []; }
  function iconBtn(icon, title, fn, cls) { return el('button.dk-ib' + (cls ? '.' + cls : ''), { type: 'button', title: title, 'aria-label': title, html: SV(icon), onclick: fn }); }
  function emptyMsg(t) { return el('p.dk-empty', { text: t }); }
  function ddayLabel(date) { var n = daysBetween(today(), date); return n === 0 ? 'D-Day' : n > 0 ? 'D-' + n : 'D+' + (-n); }

  /* 1) 오늘 할 일 */
  W.todo = (function () {
    var items = list('todo').slice(), b = body('todo'), ul, foot;
    function persist() { save('todo', { items: items }); }
    function render() {
      ul.innerHTML = '';
      var sorted = items.slice().sort(function (a, c) { return (a.done - c.done) || ((c.star ? 1 : 0) - (a.star ? 1 : 0)) || (a.at - c.at); });
      if (!sorted.length) ul.appendChild(el('li.dk-empty', { text: '할 일을 적고 Enter 를 누르세요.' }));
      sorted.forEach(function (it) {
        var txt = el('span.dk-todo-t', { text: it.t, title: '두 번 눌러 고치기' });
        txt.addEventListener('dblclick', function () {
          txt.contentEditable = 'true'; txt.focus();
          document.getSelection().selectAllChildren(txt);
          var done = function () { txt.contentEditable = 'false'; var v = txt.textContent.trim(); if (v && v !== it.t) { it.t = v.slice(0, 300); persist(); } else txt.textContent = it.t; };
          txt.addEventListener('blur', done, { once: true });
          txt.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); txt.blur(); } if (e.key === 'Escape') { txt.textContent = it.t; txt.blur(); } });
        });
        ul.appendChild(el('li.dk-todo' + (it.done ? '.is-done' : '') + (it.star ? '.is-star' : ''), null, [
          el('label.dk-check', null, [el('input', { type: 'checkbox', checked: it.done ? true : null, onchange: function () { it.done = this.checked; it.doneAt = it.done ? Date.now() : 0; persist(); render(); } }), el('i')]),
          txt,
          iconBtn(IC.star, '중요', function () { it.star = !it.star; persist(); render(); }, 'dk-star'),
          iconBtn(IC.x, '지우기', function () { items = items.filter(function (x) { return x !== it; }); persist(); render(); }, 'dk-del')
        ]));
      });
      var left = items.filter(function (x) { return !x.done; }).length, doneN = items.length - left;
      foot.innerHTML = '';
      foot.appendChild(el('span', { text: items.length ? '남은 일 ' + left + '개' : '' }));
      if (doneN) foot.appendChild(el('button.dk-link', { type: 'button', text: '완료 ' + doneN + '개 지우기', onclick: function () { items = items.filter(function (x) { return !x.done; }); persist(); render(); } }));
      extra('todo').textContent = left ? String(left) : '';
    }
    var input = el('input.dk-in', { type: 'text', placeholder: '할 일 추가…', maxlength: 300, 'aria-label': '할 일 추가' });
    input.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.isComposing) return;
      var v = input.value.trim(); if (!v) return;
      items.push({ id: uid(), t: v, done: false, star: false, at: Date.now() }); input.value = ''; persist(); render();
    });
    b.appendChild(input);
    ul = el('ul.dk-todos'); b.appendChild(ul);
    foot = el('div.dk-foot'); b.appendChild(foot);
    render();
    return {};
  })();

  /* 2) 캘린더 (공휴일 · 내 일정 · D-day 표시) */
  W.calendar = (function () {
    var b = body('calendar'), view = new Date(), sel = today(), events = list('cal').slice();
    view.setDate(1);
    function persist() { save('cal', { items: events }); }
    function marks(dateStr) {
      var m = events.filter(function (e) { return e.date === dateStr; }).length;
      m += list('dday').filter(function (e) { return e.date === dateStr; }).length;
      return m;
    }
    function render() {
      b.innerHTML = '';
      var y = view.getFullYear(), mo = view.getMonth();
      var ex = extra('calendar'); ex.innerHTML = '';
      ex.appendChild(iconBtn(IC.left, '이전 달', function () { view.setMonth(view.getMonth() - 1); render(); }));
      ex.appendChild(el('button.dk-link', { type: 'button', text: '오늘', onclick: function () { view = new Date(); view.setDate(1); sel = today(); render(); } }));
      ex.appendChild(iconBtn(IC.right, '다음 달', function () { view.setMonth(view.getMonth() + 1); render(); }));
      b.appendChild(el('p.dk-cal-title', { text: y + '년 ' + (mo + 1) + '월' }));
      var g = el('div.dk-cal', { role: 'grid' });
      WD.forEach(function (w, i) { g.appendChild(el('span.dk-cal-wd' + (i === 0 ? '.is-sun' : i === 6 ? '.is-sat' : ''), { text: w })); });
      var start = new Date(y, mo, 1 - view.getDay()), t = today();
      for (var i = 0; i < 42; i++) {
        var d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i), s = ymd(d);
        if (i >= 35 && d.getMonth() !== mo) break;
        var cls = ['dk-cal-d'];
        if (d.getMonth() !== mo) cls.push('is-out');
        if (d.getDay() === 0 || HOLI[s]) cls.push('is-sun'); else if (d.getDay() === 6) cls.push('is-sat');
        if (s === t) cls.push('is-today');
        if (s === sel) cls.push('is-sel');
        var n = marks(s);
        g.appendChild(el('button.' + cls.join('.'), { type: 'button', title: HOLI[s] || null, 'data-d': s, onclick: function () { sel = this.dataset.d; render(); } }, [String(d.getDate()), n ? el('i') : null]));
      }
      b.appendChild(g);
      // 선택한 날의 일정
      var day = el('div.dk-cal-day');
      var sd = parseYmd(sel);
      day.appendChild(el('p.dk-cal-day-h', null, [(sd.getMonth() + 1) + '월 ' + sd.getDate() + '일 (' + WD[sd.getDay()] + ')', HOLI[sel] ? el('em', { text: HOLI[sel] }) : null]));
      var ul = el('ul.dk-cal-ev');
      list('dday').filter(function (e) { return e.date === sel; }).forEach(function (e) { ul.appendChild(el('li.is-dday', null, [el('span', { text: e.t }), el('small', { text: 'D-day' })])); });
      events.filter(function (e) { return e.date === sel; }).forEach(function (e) {
        ul.appendChild(el('li', null, [el('span', { text: (e.time ? e.time + ' ' : '') + e.t }), iconBtn(IC.x, '일정 지우기', function () { events = events.filter(function (x) { return x !== e; }); persist(); render(); }, 'dk-del')]));
      });
      day.appendChild(ul);
      var inp = el('input.dk-in', { type: 'text', placeholder: '일정 추가 (예: 14:00 회의)', maxlength: 200, 'aria-label': '일정 추가' });
      inp.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.isComposing) return;
        var v = inp.value.trim(); if (!v) return;
        var m = v.match(/^(\d{1,2}):(\d{2})\s+(.+)$/), ev = { id: uid(), date: sel, t: v };
        if (m) { ev.time = pad(+m[1]) + ':' + m[2]; ev.t = m[3]; }
        events.push(ev);
        events.sort(function (a, c) { return a.date === c.date ? String(a.time || '').localeCompare(String(c.time || '')) : a.date < c.date ? -1 : 1; });
        // 1년 넘게 지난 일정은 정리
        var cut = ymd(new Date(Date.now() - 400 * 864e5)); events = events.filter(function (x) { return x.date >= cut; });
        persist(); render();
        var again = $('.dk-cal-day .dk-in', b); if (again) again.focus();
      });
      day.appendChild(inp);
      b.appendChild(day);
    }
    render();
    return { onNewDay: render, refresh: render };
  })();

  /* 3) 메모 (포스트잇) */
  W.notes = (function () {
    var COLORS = ['yellow', 'green', 'blue', 'pink', 'gray'];
    var items = list('notes').slice(), b = body('notes'), box = el('div.dk-notes');
    function persist() { save('notes', { items: items }); }
    function render() {
      box.innerHTML = '';
      if (!items.length) box.appendChild(emptyMsg('＋ 를 눌러 메모를 추가하세요. 적는 즉시 저장됩니다.'));
      items.forEach(function (n) {
        var ta = el('textarea', { 'aria-label': '메모', placeholder: '메모…', maxlength: 5000 });
        ta.value = n.t || '';
        ta.addEventListener('input', function () { n.t = ta.value; n.at = Date.now(); persist(); });
        box.appendChild(el('div.dk-note', { 'data-c': n.c || 'yellow' }, [
          ta,
          el('div.dk-note-bar', null, [
            el('button.dk-note-c', { type: 'button', title: '색 바꾸기', 'aria-label': '색 바꾸기', onclick: function () { n.c = COLORS[(COLORS.indexOf(n.c || 'yellow') + 1) % COLORS.length]; this.parentNode.parentNode.dataset.c = n.c; persist(); } }),
            el('small', { text: n.at ? new Date(n.at).toLocaleDateString('ko-KR', { month: 'numeric', day: 'numeric' }) : '' }),
            iconBtn(IC.copy, '복사', function () { navigator.clipboard && navigator.clipboard.writeText(ta.value).then(function () { toast('메모를 복사했어요.'); }); }),
            iconBtn(IC.x, '메모 지우기', function () { if (ta.value.trim() && !confirm('이 메모를 지울까요?')) return; items = items.filter(function (x) { return x !== n; }); persist(); render(); }, 'dk-del')
          ])
        ]));
      });
    }
    extra('notes').appendChild(iconBtn(IC.plus, '새 메모', function () {
      items.unshift({ id: uid(), t: '', c: COLORS[items.length % COLORS.length], at: Date.now() }); persist(); render();
      var ta = $('textarea', box); if (ta) ta.focus();
    }));
    b.appendChild(box); render();
    return {};
  })();

  /* 4) 집중 타이머 (뽀모도로) */
  W.pomo = (function () {
    var b = body('pomo');
    var cfg = Object.assign({ focus: 25, short: 5, long: 15, every: 4 }, (D.pomo && D.pomo.cfg) || {});
    var log = (D.pomo && D.pomo.log) || { date: today(), n: 0 };
    var MODES = { focus: '집중', short: '짧은 휴식', long: '긴 휴식' };
    var st = LS.get('dk-pomo', null) || { mode: 'focus', left: cfg.focus * 60, endAt: 0 };
    var timer = null, baseTitle = document.title;
    function persist() { save('pomo', { cfg: cfg, log: log }); }
    function total() { return cfg[st.mode] * 60; }
    function left() { return st.endAt ? Math.max(0, Math.round((st.endAt - Date.now()) / 1000)) : st.left; }
    function fmt(s) { return pad(Math.floor(s / 60)) + ':' + pad(s % 60); }
    var ring, timeEl, playBtn, modeBox, cnt;
    function build() {
      modeBox = el('div.dk-seg');
      Object.keys(MODES).forEach(function (m) { modeBox.appendChild(el('button', { type: 'button', 'data-m': m, text: MODES[m], onclick: function () { setMode(m); } })); });
      b.appendChild(modeBox);
      var C = 2 * Math.PI * 52;
      var wrap = el('div.dk-pomo', { html: '<svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="dk-pomo-bg"/><circle cx="60" cy="60" r="52" class="dk-pomo-fg" stroke-dasharray="' + C + '" stroke-dashoffset="0" transform="rotate(-90 60 60)"/></svg>' });
      ring = $('.dk-pomo-fg', wrap); ring.dataset.c = C;
      timeEl = el('b.dk-pomo-t', { role: 'timer', 'aria-live': 'off' }); wrap.appendChild(timeEl);
      b.appendChild(wrap);
      playBtn = iconBtn(IC.play, '시작', toggle, 'dk-pomo-play');
      b.appendChild(el('div.dk-pomo-ctl', null, [iconBtn(IC.reset, '처음부터', reset), playBtn, iconBtn(IC.skip, '다음 단계로', function () { finish(true); })]));
      cnt = el('p.dk-pomo-n'); b.appendChild(cnt);
      var setRow = el('details.dk-pomo-set', null, [el('summary', { text: '시간 설정' })]);
      [['focus', '집중'], ['short', '짧은 휴식'], ['long', '긴 휴식']].forEach(function (p) {
        var inp = el('input', { type: 'number', min: 1, max: 180, value: cfg[p[0]] });
        inp.addEventListener('change', function () { cfg[p[0]] = Math.max(1, Math.min(180, parseInt(inp.value, 10) || 1)); inp.value = cfg[p[0]]; persist(); if (!st.endAt && st.mode === p[0]) { st.left = total(); keep(); paint(); } });
        setRow.appendChild(el('label', null, [p[1] + ' ', inp, ' 분']));
      });
      b.appendChild(setRow);
    }
    function keep() { LS.set('dk-pomo', st); }
    function setMode(m) { st = { mode: m, left: cfg[m] * 60, endAt: 0 }; keep(); stopTick(); paint(); }
    function toggle() {
      if (st.endAt) { st.left = left(); st.endAt = 0; stopTick(); }
      else { st.endAt = Date.now() + left() * 1000; startTick(); if (window.Notification && Notification.permission === 'default') Notification.requestPermission(); unlockAudio(); }
      keep(); paint();
    }
    function reset() { st.left = total(); st.endAt = 0; keep(); stopTick(); paint(); }
    function startTick() { stopTick(); timer = setInterval(function () { if (left() <= 0) finish(false); else paint(); }, 250); }
    function stopTick() { clearInterval(timer); timer = null; document.title = baseTitle; }
    function finish(skipped) {
      var was = st.mode;
      if (log.date !== today()) log = { date: today(), n: 0 };
      if (was === 'focus' && !skipped) { log.n++; persist(); }
      if (!skipped) { beep(); notify(was === 'focus' ? '집중 시간이 끝났어요. 잠깐 쉬어 가세요.' : '휴식이 끝났어요. 다시 집중해 볼까요?'); }
      var next = was === 'focus' ? (log.n && log.n % cfg.every === 0 ? 'long' : 'short') : 'focus';
      setMode(next);
    }
    var actx = null;
    function unlockAudio() { try { actx = actx || new (window.AudioContext || window.webkitAudioContext)(); if (actx.state === 'suspended') actx.resume(); } catch (e) {} }
    function beep() {
      try { unlockAudio(); [0, .28, .56].forEach(function (t) { var o = actx.createOscillator(), g = actx.createGain(); o.frequency.value = 880; o.connect(g); g.connect(actx.destination); g.gain.setValueAtTime(.0001, actx.currentTime + t); g.gain.exponentialRampToValueAtTime(.25, actx.currentTime + t + .02); g.gain.exponentialRampToValueAtTime(.0001, actx.currentTime + t + .22); o.start(actx.currentTime + t); o.stop(actx.currentTime + t + .25); }); } catch (e) {}
    }
    function notify(msg) {
      toast(msg);
      try { if (window.Notification && Notification.permission === 'granted' && document.visibilityState !== 'visible') new Notification('집중 타이머', { body: msg, icon: '../assets/logo_icon.png' }); } catch (e) {}
    }
    function paint() {
      var s = left(), C = +ring.dataset.c;
      timeEl.textContent = fmt(s);
      ring.setAttribute('stroke-dashoffset', String(C * (1 - s / total())));
      b.dataset.mode = st.mode;
      playBtn.innerHTML = SV(st.endAt ? IC.pause : IC.play); playBtn.title = st.endAt ? '일시정지' : '시작';
      $$('button', modeBox).forEach(function (x) { x.setAttribute('aria-pressed', String(x.dataset.m === st.mode)); });
      if (log.date !== today()) log = { date: today(), n: 0 };
      cnt.textContent = '오늘 집중 ' + log.n + '회 · ' + (log.n * cfg.focus) + '분';
      document.title = st.endAt ? '⏱ ' + fmt(s) + ' ' + MODES[st.mode] + ' · ' + baseTitle : baseTitle;
      extra('pomo').textContent = st.endAt ? fmt(s) : '';
    }
    build();
    if (st.endAt) { if (left() <= 0) finish(false); else startTick(); }
    paint();
    return { onNewDay: paint };
  })();

  /* 5) 날씨 (Open-Meteo) */
  W.weather = (function () {
    var b = body('weather');
    var loc = Object.assign({ name: '서울', lat: 37.5665, lon: 126.978 }, D.weather || {});
    var WMO = function (c) {
      if (c === 0) return ['☀️', '맑음']; if (c <= 2) return ['🌤️', '구름 조금']; if (c === 3) return ['☁️', '흐림'];
      if (c <= 48) return ['🌫️', '안개']; if (c <= 57) return ['🌦️', '이슬비']; if (c <= 67) return ['🌧️', '비'];
      if (c <= 77) return ['🌨️', '눈']; if (c <= 82) return ['🌧️', '소나기']; if (c <= 86) return ['🌨️', '눈 소나기']; return ['⛈️', '뇌우'];
    };
    var grade = function (v, t) { if (v == null) return null; var i = v <= t[0] ? 0 : v <= t[1] ? 1 : v <= t[2] ? 2 : 3; return [['좋음', '보통', '나쁨', '매우 나쁨'][i], i]; };
    function load(force) {
      var key = 'dk-wx-' + loc.lat.toFixed(2) + ',' + loc.lon.toFixed(2), c = LS.get(key, null);
      if (c && !force && Date.now() - c.at < 30 * 60e3) return render(c);
      b.innerHTML = ''; b.appendChild(emptyMsg('날씨를 불러오는 중…'));
      var q = 'latitude=' + loc.lat + '&longitude=' + loc.lon + '&timezone=auto';
      Promise.all([
        fetch('https://api.open-meteo.com/v1/forecast?' + q + '&current=temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max&forecast_days=5').then(function (r) { return r.json(); }),
        fetch('https://air-quality-api.open-meteo.com/v1/air-quality?' + q + '&current=pm10,pm2_5').then(function (r) { return r.json(); }).catch(function () { return null; })
      ]).then(function (res) {
        if (!res[0] || !res[0].current) throw new Error();
        var data = { at: Date.now(), wx: res[0], air: res[1] && res[1].current ? res[1].current : null };
        LS.set(key, data); render(data);
      }).catch(function () { b.innerHTML = ''; b.appendChild(emptyMsg('날씨를 불러오지 못했어요.')); b.appendChild(el('button.dk-link', { type: 'button', text: '다시 시도', onclick: function () { load(true); } })); });
    }
    function render(c) {
      b.innerHTML = '';
      var cur = c.wx.current, dly = c.wx.daily, w = WMO(cur.weather_code);
      b.appendChild(el('div.dk-wx-now', null, [
        el('span.dk-wx-ico', { text: w[0] }),
        el('div', null, [el('b.dk-wx-t', { text: Math.round(cur.temperature_2m) + '°' }), el('span', { text: w[1] + ' · 체감 ' + Math.round(cur.apparent_temperature) + '°' })]),
      ]));
      var meta = ['습도 ' + cur.relative_humidity_2m + '%', '바람 ' + (cur.wind_speed_10m / 3.6).toFixed(1) + 'm/s'];
      if (dly && dly.temperature_2m_max) meta.unshift('최고 ' + Math.round(dly.temperature_2m_max[0]) + '° / 최저 ' + Math.round(dly.temperature_2m_min[0]) + '°');
      b.appendChild(el('p.dk-wx-meta', { text: meta.join(' · ') }));
      if (c.air) {
        var p10 = grade(c.air.pm10, [30, 80, 150]), p25 = grade(c.air.pm2_5, [15, 35, 75]);
        b.appendChild(el('div.dk-wx-air', null, [
          p10 ? el('span', { 'data-g': p10[1] }, ['미세먼지 ', el('b', { text: p10[0] })]) : null,
          p25 ? el('span', { 'data-g': p25[1] }, ['초미세 ', el('b', { text: p25[0] })]) : null
        ]));
      }
      if (dly && dly.time) {
        var row = el('div.dk-wx-days');
        dly.time.forEach(function (t, i) {
          var d = parseYmd(t), ww = WMO(dly.weather_code[i]);
          row.appendChild(el('div', { title: ww[1] }, [el('small', { text: i === 0 ? '오늘' : WD[d.getDay()] }), el('span', { text: ww[0] }), el('b', { text: Math.round(dly.temperature_2m_max[i]) + '°' }), el('small', { text: Math.round(dly.temperature_2m_min[i]) + '°' }), dly.precipitation_probability_max && dly.precipitation_probability_max[i] >= 30 ? el('em', { text: dly.precipitation_probability_max[i] + '%' }) : null]));
        });
        b.appendChild(row);
      }
      b.appendChild(el('p.dk-src', { text: new Date(c.at).toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' }) + ' 기준 · Open-Meteo' }));
    }
    function pickLoc() {
      b.innerHTML = '';
      var inp = el('input.dk-in', { type: 'search', placeholder: '도시 이름 (예: 부산, 도쿄)', 'aria-label': '지역 검색' });
      var res = el('ul.dk-wx-res');
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') return load();
        if (e.key !== 'Enter' || e.isComposing) return;
        res.innerHTML = '<li class="dk-empty">찾는 중…</li>';
        fetch('https://geocoding-api.open-meteo.com/v1/search?count=6&language=ko&name=' + encodeURIComponent(inp.value.trim())).then(function (r) { return r.json(); }).then(function (j) {
          res.innerHTML = '';
          (j.results || []).forEach(function (r) {
            res.appendChild(el('li', null, el('button', { type: 'button', text: r.name + (r.admin1 ? ', ' + r.admin1 : '') + (r.country ? ' · ' + r.country : ''), onclick: function () { loc = { name: r.name, lat: r.latitude, lon: r.longitude }; save('weather', loc); setTitle(); load(); } })));
          });
          if (!res.children.length) res.appendChild(el('li.dk-empty', { text: '찾지 못했어요.' }));
        }).catch(function () { res.innerHTML = '<li class="dk-empty">검색하지 못했어요.</li>'; });
      });
      b.appendChild(inp); b.appendChild(res);
      b.appendChild(el('button.dk-link', { type: 'button', text: '취소', onclick: function () { load(); } }));
      inp.focus();
    }
    function setTitle() {
      var ex = extra('weather'); ex.innerHTML = '';
      ex.appendChild(el('button.dk-loc', { type: 'button', title: '지역 바꾸기', html: SV(IC.pin) + '<span></span>', onclick: pickLoc }));
      $('span', ex).textContent = loc.name;
    }
    setTitle(); load();
    setInterval(function () { if (document.visibilityState === 'visible') load(); }, 10 * 60e3);
    return {};
  })();

  /* 6) D-day */
  W.dday = (function () {
    var items = list('dday').slice(), b = body('dday'), ul = el('ul.dk-dday');
    function persist() { save('dday', { items: items }); if (W.calendar && W.calendar.refresh) W.calendar.refresh(); }
    function render() {
      ul.innerHTML = '';
      var t = today();
      items.sort(function (a, c) { var pa = a.date < t, pc = c.date < t; return pa !== pc ? (pa ? 1 : -1) : (pa ? (a.date < c.date ? 1 : -1) : (a.date < c.date ? -1 : 1)); });
      if (!items.length) ul.appendChild(el('li.dk-empty', { text: '마감일·기념일을 추가해 보세요.' }));
      items.forEach(function (it) {
        var n = daysBetween(t, it.date), d = parseYmd(it.date);
        ul.appendChild(el('li' + (n < 0 ? '.is-past' : n <= 3 ? '.is-near' : ''), null, [
          el('b.dk-dday-n', { text: ddayLabel(it.date) }),
          el('span', null, [el('span.dk-dday-t', { text: it.t }), el('small', { text: d.getFullYear() + '.' + (d.getMonth() + 1) + '.' + d.getDate() + ' (' + WD[d.getDay()] + ')' })]),
          iconBtn(IC.x, '지우기', function () { items = items.filter(function (x) { return x !== it; }); persist(); render(); }, 'dk-del')
        ]));
      });
    }
    var f = el('form.dk-row');
    var tIn = el('input.dk-in', { type: 'text', placeholder: '이름 (예: 보고 마감)', maxlength: 100, required: true, 'aria-label': 'D-day 이름' });
    var dIn = el('input.dk-in.dk-in-date', { type: 'date', required: true, 'aria-label': '날짜' });
    f.appendChild(tIn); f.appendChild(dIn); f.appendChild(iconBtn(IC.plus, '추가', null, 'dk-add'));
    $('button', f).type = 'submit';
    f.addEventListener('submit', function (e) { e.preventDefault(); if (!tIn.value.trim() || !dIn.value) return; items.push({ id: uid(), t: tIn.value.trim(), date: dIn.value }); tIn.value = ''; dIn.value = ''; persist(); render(); });
    b.appendChild(ul); b.appendChild(f);
    render();
    return { onNewDay: render };
  })();

  /* 7) 바로가기 */
  W.links = (function () {
    var items = list('links').slice(), b = body('links'), box = el('div.dk-links'), edit = false;
    function persist() { save('links', { items: items }); }
    function hue(s) { var h = 0; for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 360; return h; }
    function host(u) { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return u; } }
    function render() {
      box.innerHTML = ''; box.classList.toggle('is-edit', edit);
      if (!items.length && !edit) box.appendChild(emptyMsg('자주 여는 사이트를 등록하세요. (메일, 그룹웨어, 드라이브 …)'));
      items.forEach(function (it, i) {
        var a = el('a.dk-lnk', { href: it.url, target: '_blank', rel: 'noopener noreferrer', title: it.url }, [
          el('span.dk-lnk-ico', { text: (it.t || host(it.url)).trim().charAt(0).toUpperCase(), style: '--h:' + hue(host(it.url)) }),
          el('span.dk-lnk-t', { text: it.t || host(it.url) })
        ]);
        if (edit) {
          a.addEventListener('click', function (e) { e.preventDefault(); });
          a.appendChild(el('button.dk-lnk-x', { type: 'button', title: '지우기', 'aria-label': '지우기', html: SV(IC.x), onclick: function (e) { e.preventDefault(); items.splice(i, 1); persist(); render(); } }));
        }
        box.appendChild(a);
      });
      if (edit) {
        var f = el('form.dk-row.dk-lnk-form');
        var t = el('input.dk-in', { type: 'text', placeholder: '이름', maxlength: 40, 'aria-label': '이름' });
        var u = el('input.dk-in', { type: 'text', placeholder: 'https://…', required: true, 'aria-label': '주소' });
        f.appendChild(t); f.appendChild(u); f.appendChild(iconBtn(IC.plus, '추가', null, 'dk-add'));
        $('button', f).type = 'submit';
        f.addEventListener('submit', function (e) {
          e.preventDefault();
          var url = u.value.trim(); if (!url) return;
          if (!/^[a-z][a-z0-9+.-]*:/i.test(url)) url = 'https://' + url;
          if (!/^(https?|mailto):/i.test(url)) { toast('http(s) 주소만 등록할 수 있어요.'); return; }
          items.push({ id: uid(), t: t.value.trim(), url: url }); persist(); render();
          var nf = $('.dk-lnk-form .dk-in', b); if (nf) nf.focus();
        });
        box.appendChild(f);
      }
    }
    extra('links').appendChild(iconBtn(IC.edit, '바로가기 편집', function () { edit = !edit; this.setAttribute('aria-pressed', String(edit)); render(); }));
    b.appendChild(box); render();
    return {};
  })();

  /* 8) 계산기 · 단위 변환 · 부가세 */
  W.calc = (function () {
    var b = body('calc'), tabs = el('div.dk-seg'), pane = el('div.dk-calc-pane');
    var TABS = { calc: '계산기', unit: '단위 변환', vat: '부가세' }, cur = LS.get('dk-calc-tab', 'calc');
    Object.keys(TABS).forEach(function (k) { tabs.appendChild(el('button', { type: 'button', 'data-t': k, text: TABS[k], onclick: function () { cur = k; LS.set('dk-calc-tab', k); show(); } })); });
    b.appendChild(tabs); b.appendChild(pane);
    var nf = function (v, max) { return isFinite(v) ? Number(v).toLocaleString('ko-KR', { maximumFractionDigits: max == null ? 10 : max }) : '오류'; };

    // 안전한 사칙연산 계산(eval 사용 안 함): 숫자, + − × ÷, %, 괄호
    function evaluate(src) {
      var s = src.replace(/×/g, '*').replace(/÷/g, '/').replace(/−/g, '-').replace(/,/g, ''), i = 0;
      function ws() { while (s[i] === ' ') i++; }
      function num() { ws(); var m = /^\d*\.?\d+(e[+-]?\d+)?/i.exec(s.slice(i)); if (!m) throw 0; i += m[0].length; return parseFloat(m[0]); }
      function atom() {
        ws(); var v;
        if (s[i] === '-') { i++; return -atom(); }
        if (s[i] === '+') { i++; return atom(); }
        if (s[i] === '(') { i++; v = expr(); ws(); if (s[i] === ')') i++; } else v = num();
        ws(); while (s[i] === '%') { v /= 100; i++; ws(); }
        return v;
      }
      function term() { var v = atom(); for (;;) { ws(); var o = s[i]; if (o !== '*' && o !== '/') return v; i++; var r = atom(); v = o === '*' ? v * r : v / r; } }
      function expr() { var v = term(); for (;;) { ws(); var o = s[i]; if (o !== '+' && o !== '-') return v; i++; var r = term(); v = o === '+' ? v + r : v - r; } }
      var r = expr(); ws(); if (i < s.length) throw 0;
      return Math.round(r * 1e10) / 1e10;
    }
    function calcPane() {
      var exprStr = '', last = null;
      var exp = el('div.dk-calc-exp', { 'aria-live': 'polite' }), out = el('output.dk-calc-out', { title: '눌러서 복사' }, '0');
      out.addEventListener('click', function () { if (navigator.clipboard) navigator.clipboard.writeText(String(last == null ? 0 : last)).then(function () { toast('결과를 복사했어요.'); }); });
      function upd() {
        exp.textContent = exprStr || ' ';
        try { var v = exprStr ? evaluate(exprStr) : 0; last = v; out.textContent = nf(v); out.classList.remove('is-err'); }
        catch (e) { out.classList.add('is-err'); }
      }
      function press(k) {
        if (k === 'C') exprStr = '';
        else if (k === '⌫') exprStr = exprStr.slice(0, -1);
        else if (k === '=') { try { exprStr = String(evaluate(exprStr)); } catch (e) {} }
        else if (k === '±') exprStr = exprStr ? (exprStr[0] === '-' && exprStr.indexOf('(') < 0 ? exprStr.slice(1) : '-(' + exprStr + ')') : '-';
        else exprStr += k;
        upd();
      }
      var keys = ['C', '(', ')', '÷', '7', '8', '9', '×', '4', '5', '6', '−', '1', '2', '3', '+', '%', '0', '.', '=', ];
      var pad_ = el('div.dk-keys');
      keys.forEach(function (k) { pad_.appendChild(el('button' + (/[÷×−+=]/.test(k) ? '.is-op' : k === 'C' ? '.is-fn' : ''), { type: 'button', text: k, onclick: function () { press(k); } })); });
      var wrap = el('div.dk-calc', { tabindex: 0, 'aria-label': '계산기 (키보드 입력 가능)' }, [exp, out, pad_]);
      wrap.addEventListener('keydown', function (e) {
        var k = e.key, map = { '*': '×', '/': '÷', '-': '−', 'Enter': '=', 'Backspace': '⌫', 'Escape': 'C', 'Delete': 'C' };
        if (/^[0-9.+()%]$/.test(k)) press(k); else if (map[k]) press(map[k]); else return;
        e.preventDefault();
      });
      upd();
      return wrap;
    }
    var UNITS = {
      '길이': { mm: .001, cm: .01, m: 1, km: 1000, 'in': .0254, ft: .3048, yd: .9144, mi: 1609.344 },
      '넓이': { '㎡': 1, '평': 400 / 121, 'ha': 10000, '㎢': 1e6, 'ft²': .09290304, '에이커': 4046.8564224 },
      '무게': { g: .001, kg: 1, t: 1000, '근(600g)': .6, oz: .028349523125, lb: .45359237 },
      '부피': { mL: .001, L: 1, '㎥': 1000, 'gal(US)': 3.785411784, 'fl oz': .0295735295625, '컵(200mL)': .2 },
      '데이터': { B: 1, KB: 1024, MB: 1048576, GB: 1073741824, TB: 1099511627776 },
      '온도': { '°C': 'C', '°F': 'F', K: 'K' }
    };
    function toBase(cat, u, v) { var f = UNITS[cat][u]; if (cat !== '온도') return v * f; return f === 'C' ? v : f === 'F' ? (v - 32) * 5 / 9 : v - 273.15; }
    function fromBase(cat, u, v) { var f = UNITS[cat][u]; if (cat !== '온도') return v / f; return f === 'C' ? v : f === 'F' ? v * 9 / 5 + 32 : v + 273.15; }
    function unitPane() {
      var st = LS.get('dk-unit', { cat: '넓이', a: '평', b: '㎡' });
      if (!UNITS[st.cat]) st = { cat: '길이', a: 'm', b: 'cm' };
      var wrap = el('div.dk-unit');
      var catSel = el('select.dk-in', { 'aria-label': '종류' });
      Object.keys(UNITS).forEach(function (c) { catSel.appendChild(el('option', { value: c, text: c, selected: c === st.cat ? true : null })); });
      var aIn = el('input.dk-in', { type: 'number', step: 'any', value: 1, 'aria-label': '값' }), bIn = el('input.dk-in', { type: 'number', step: 'any', 'aria-label': '변환 값' });
      var aSel = el('select.dk-in', { 'aria-label': '단위' }), bSel = el('select.dk-in', { 'aria-label': '변환 단위' });
      function fill() {
        [aSel, bSel].forEach(function (s, i) { s.innerHTML = ''; Object.keys(UNITS[st.cat]).forEach(function (u) { s.appendChild(el('option', { value: u, text: u, selected: u === (i ? st.b : st.a) ? true : null })); }); });
        if (!UNITS[st.cat][st.a]) st.a = aSel.value; if (!UNITS[st.cat][st.b]) st.b = bSel.value;
      }
      function conv(dir) {
        st.a = aSel.value; st.b = bSel.value; LS.set('dk-unit', st);
        var src = dir ? bIn : aIn, dst = dir ? aIn : bIn, v = parseFloat(src.value);
        if (!isFinite(v)) { dst.value = ''; return; }
        var r = fromBase(st.cat, dir ? st.a : st.b, toBase(st.cat, dir ? st.b : st.a, v));
        dst.value = String(Math.round(r * 1e6) / 1e6);
      }
      catSel.addEventListener('change', function () { st.cat = catSel.value; st.a = st.b = ''; fill(); st.a = aSel.value; bSel.selectedIndex = Math.min(1, bSel.options.length - 1); conv(0); });
      aIn.addEventListener('input', function () { conv(0); }); bIn.addEventListener('input', function () { conv(1); });
      aSel.addEventListener('change', function () { conv(0); }); bSel.addEventListener('change', function () { conv(0); });
      fill();
      wrap.appendChild(catSel);
      wrap.appendChild(el('div.dk-unit-row', null, [aIn, aSel]));
      wrap.appendChild(el('button.dk-swap', { type: 'button', text: '⇅', title: '단위 바꾸기', onclick: function () { var t = aSel.value; aSel.value = bSel.value; bSel.value = t; conv(0); } }));
      wrap.appendChild(el('div.dk-unit-row', null, [bIn, bSel]));
      conv(0);
      return wrap;
    }
    function vatPane() {
      var st = LS.get('dk-vat', { mode: 'incl', rate: 10 });
      var wrap = el('div.dk-vat');
      var amt = el('input.dk-in', { type: 'text', inputmode: 'numeric', placeholder: '금액 (원)', 'aria-label': '금액' });
      var seg = el('div.dk-seg');
      [['incl', '부가세 포함 금액'], ['excl', '공급가액']].forEach(function (m) { seg.appendChild(el('button', { type: 'button', 'data-m': m[0], text: m[1], onclick: function () { st.mode = m[0]; LS.set('dk-vat', st); calc(); } })); });
      var out = el('dl.dk-vat-out');
      function calc() {
        $$('button', seg).forEach(function (x) { x.setAttribute('aria-pressed', String(x.dataset.m === st.mode)); });
        var v = parseFloat(amt.value.replace(/[^\d.]/g, '')) || 0, r = st.rate / 100, sup, tax;
        if (st.mode === 'incl') { sup = Math.round(v / (1 + r)); tax = v - sup; } else { sup = v; tax = Math.floor(v * r); }
        out.innerHTML = '';
        [['공급가액', sup], ['부가세 (' + st.rate + '%)', tax], ['합계', sup + tax]].forEach(function (p, i) {
          out.appendChild(el('dt', { text: p[0] }));
          out.appendChild(el('dd' + (i === 2 ? '.is-total' : ''), { title: '눌러서 복사', text: nf(p[1], 0) + '원', onclick: function () { navigator.clipboard && navigator.clipboard.writeText(String(p[1])).then(function () { toast(p[0] + ' 복사됨'); }); } }));
        });
      }
      amt.addEventListener('input', function () { var raw = amt.value.replace(/[^\d]/g, ''); amt.value = raw ? Number(raw).toLocaleString('ko-KR') : ''; calc(); });
      wrap.appendChild(seg); wrap.appendChild(amt); wrap.appendChild(out);
      calc();
      return wrap;
    }
    function show() {
      $$('button', tabs).forEach(function (x) { x.setAttribute('aria-pressed', String(x.dataset.t === cur)); });
      pane.innerHTML = '';
      pane.appendChild(cur === 'unit' ? unitPane() : cur === 'vat' ? vatPane() : calcPane());
    }
    if (!TABS[cur]) cur = 'calc';
    show();
    return {};
  })();
})();

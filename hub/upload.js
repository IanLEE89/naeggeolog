/* 내 공간 · 파일 올리기 (주인 전용)
 * - 파일 선택 / 폴더째 올리기 / 끌어다 놓기(폴더 포함)
 * - 조각(data-chunk 바이트)으로 나눠 한 파일씩 순서대로 → 저사양 NAS 에 부담 적음
 * - 끊기면 자동 재시도, 같은 파일을 다시 올리면 받은 곳부터 이어서
 */
(function () {
  var cfg = document.querySelector('[data-uploader]');
  if (!cfg) return;
  var S = cfg.dataset.s, P = cfg.dataset.p, CSRF = cfg.dataset.csrf, URL_ = cfg.dataset.url, CHUNK = Math.max(262144, +cfg.dataset.chunk || 4194304);
  var queue = [], running = false, cur = null, panel, listEl, headEl, startT = 0, sentTotal = 0;

  function fmt(b) { var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0; while (b >= 1024 && i < 4) { b /= 1024; i++; } return (i ? b.toFixed(b >= 100 ? 0 : 1) : b) + ' ' + u[i]; }
  function skipName(n) { var l = n.toLowerCase(); return !n || n[0] === '.' || n[0] === '@' || n[0] === '#' || l === 'thumbs.db' || l === 'desktop.ini'; }
  function el(tag, cls, txt) { var x = document.createElement(tag); if (cls) x.className = cls; if (txt != null) x.textContent = txt; return x; }

  /* 같은 파일이면 같은 값 → 이어올리기 */
  function cyrb(str, seed) { var h1 = 0xdeadbeef ^ seed, h2 = 0x41c6ce57 ^ seed; for (var i = 0; i < str.length; i++) { var c = str.charCodeAt(i); h1 = Math.imul(h1 ^ c, 2654435761); h2 = Math.imul(h2 ^ c, 1597334677); } h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909); h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909); return (h2 >>> 0).toString(16).padStart(8, '0') + (h1 >>> 0).toString(16).padStart(8, '0'); }
  function tokenFor(f, sub) { var k = [S, P, sub, f.name, f.size, f.lastModified].join('|'); return cyrb(k, 1) + cyrb(k, 7); }

  /* ── 패널 ── */
  function ensurePanel() {
    if (panel) { panel.hidden = false; return; }
    panel = el('section', 'hb-up-panel'); panel.setAttribute('aria-live', 'polite');
    var head = el('div', 'hb-up-head'); headEl = el('b', '', '올리는 중');
    var tools = el('span', 'hb-up-tools');
    var fold = el('button', 'hb-up-x', '–'); fold.type = 'button'; fold.title = '접기/펴기';
    fold.onclick = function () { panel.classList.toggle('is-folded'); };
    var stop = el('button', 'hb-up-x', '✕'); stop.type = 'button'; stop.title = '모두 취소 / 닫기';
    stop.onclick = function () {
      if (running && !confirm('올리는 중인 파일을 모두 취소할까요?')) return;
      queue.forEach(function (it) { if (it.state === 'wait' || it.state === 'up') cancelItem(it); });
      if (!running) { panel.hidden = true; panel.remove(); panel = null; queue = []; }
    };
    tools.append(fold, stop); head.append(headEl, tools);
    listEl = el('ul', 'hb-up-list');
    panel.append(head, listEl); document.body.appendChild(panel);
  }
  function renderItem(it) {
    if (!it.li) {
      it.li = el('li', 'hb-up-item');
      it.nameEl = el('span', 'hb-up-name', (it.sub ? it.sub + '/' : '') + it.file.name);
      it.stEl = el('small', 'hb-up-st');
      it.bar = el('i'); var track = el('span', 'hb-up-bar'); track.appendChild(it.bar);
      it.x = el('button', 'hb-up-x', '✕'); it.x.type = 'button'; it.x.title = '취소'; it.x.onclick = function () { cancelItem(it); };
      var top = el('div', 'hb-up-row'); top.append(it.nameEl, it.x);
      it.li.append(top, track, it.stEl); listEl.appendChild(it.li);
    }
    var pct = it.size ? Math.min(100, it.sent / it.size * 100) : (it.state === 'done' ? 100 : 0);
    it.bar.style.width = pct.toFixed(1) + '%';
    it.li.dataset.state = it.state;
    it.stEl.textContent = it.state === 'done' ? (it.renamed ? '완료 · 같은 이름이 있어 “' + it.finalName + '”(으)로 저장' : '완료 · ' + fmt(it.size))
      : it.state === 'err' ? '실패 · ' + it.msg
      : it.state === 'cancel' ? '취소됨'
      : it.state === 'up' ? fmt(it.sent) + ' / ' + fmt(it.size) + (it.msg ? ' · ' + it.msg : '')
      : '대기 · ' + fmt(it.size);
    it.x.hidden = !(it.state === 'wait' || it.state === 'up');
  }
  function renderHead() {
    if (!headEl) return;
    var done = queue.filter(function (q) { return q.state === 'done'; }).length, err = queue.filter(function (q) { return q.state === 'err'; }).length;
    var total = queue.filter(function (q) { return q.state !== 'cancel'; }).length;
    if (running) {
      var sec = (Date.now() - startT) / 1000, sp = sec > 1 ? sentTotal / sec : 0;
      headEl.textContent = '올리는 중 ' + (done + 1) + ' / ' + total + (sp ? ' · ' + fmt(sp) + '/s' : '');
    } else headEl.textContent = err ? '완료 ' + done + '개 · 실패 ' + err + '개' : '완료 ' + done + '개';
  }

  /* ── 추가 ── */
  function add(files) {
    var n = 0;
    files.forEach(function (o) {
      var f = o.file, sub = (o.sub || '').split('/').filter(Boolean);
      if (skipName(f.name) || sub.some(skipName)) return;
      sub = sub.join('/');
      queue.push({ file: f, sub: sub, size: f.size, sent: 0, state: 'wait', token: tokenFor(f, sub) }); n++;
    });
    if (!n) return;
    ensurePanel(); panel.classList.remove('is-folded');
    queue.forEach(renderItem); renderHead();
    if (!running) run();
  }

  /* ── 요청 ── */
  function post(fields, blob, onProgress, item) {
    return new Promise(function (resolve) {
      var fd = new FormData(); fd.append('csrf', CSRF); fd.append('s', S); fd.append('p', P);
      Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
      if (blob) fd.append('chunk', blob, 'chunk');
      var x = new XMLHttpRequest(); x.open('POST', URL_);
      if (onProgress) x.upload.onprogress = function (e) { onProgress(e.loaded); };
      x.onload = function () { var d = {}; try { d = JSON.parse(x.responseText); } catch (e) { d = { error: '서버 응답을 읽지 못했습니다 (' + x.status + ')', retry: x.status >= 500 || x.status === 0 }; } resolve({ status: x.status, data: d }); };
      x.onerror = function () { resolve({ status: 0, data: { error: '연결이 끊겼습니다', retry: true } }); };
      x.onabort = function () { resolve({ status: -1, data: {} }); };
      if (item) item.xhr = x;
      x.send(fd);
    });
  }
  function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  async function uploadItem(it) {
    var base = { token: it.token, sub: it.sub };
    var st = await post(Object.assign({ action: 'status' }, base), null, null, it);
    if (it.state === 'cancel') return;
    if (st.status === 200 && st.data.bytes > 0 && st.data.bytes < it.size) { it.sent = st.data.bytes; it.msg = '이어서 올림'; }
    var tries = 0;
    while (it.state === 'up') {
      var end = Math.min(it.size, it.sent + CHUNK), blob = it.file.slice(it.sent, end), before = it.sent;
      var r = await post(Object.assign({ action: 'chunk', name: it.file.name, size: it.size, offset: it.sent }, base), blob, function (loaded) {
        it.live = before + Math.min(loaded, blob.size);
        it.stEl.textContent = fmt(it.live) + ' / ' + fmt(it.size); it.bar.style.width = (it.size ? it.live / it.size * 100 : 0).toFixed(1) + '%';
      }, it);
      if (it.state !== 'up') return;
      if (r.status === 200 && r.data.done) { sentTotal += end - before; it.sent = it.size; it.state = 'done'; it.finalName = r.data.name; it.renamed = r.data.renamed; return; }
      if (r.status === 200) { sentTotal += r.data.bytes - before; it.sent = r.data.bytes; tries = 0; it.msg = ''; renderItem(it); renderHead(); continue; }
      if (r.status === 409 && typeof r.data.bytes === 'number') { it.sent = r.data.bytes; continue; }   // 서버가 받은 위치로 맞춤
      if (r.status === 401 || r.status === 403 || r.status === 400 || r.status === 404 || r.status === 507 || (!r.data.retry && r.status !== 0 && r.status < 500) || ++tries > 6) {
        it.state = 'err'; it.msg = r.data.error || ('오류 ' + r.status); return;
      }
      it.msg = '다시 시도 중(' + tries + ')…'; renderItem(it);
      await wait(Math.min(30000, 1000 * Math.pow(2, tries - 1)));
    }
  }
  async function run() {
    running = true; startT = Date.now(); sentTotal = 0;
    var it;
    while ((it = queue.find(function (q) { return q.state === 'wait'; }))) {
      cur = it; it.state = 'up'; renderItem(it); renderHead();
      try { await uploadItem(it); } catch (e) { it.state = 'err'; it.msg = String(e && e.message || e); }
      renderItem(it); renderHead();
    }
    running = false; cur = null; renderHead();
    var ok = queue.some(function (q) { return q.state === 'done'; }), bad = queue.some(function (q) { return q.state === 'err'; });
    if (ok && !bad) setTimeout(function () { location.reload(); }, 900);
    else if (ok && panel && !panel.querySelector('.hb-up-reload')) { var b = el('button', 'hb-btn hb-up-reload', '목록 새로고침'); b.type = 'button'; b.onclick = function () { location.reload(); }; panel.appendChild(b); }
  }
  function cancelItem(it) {
    var was = it.state; it.state = 'cancel';
    if (was === 'up' && it.xhr) it.xhr.abort();
    if (was === 'up' || it.sent > 0) post({ action: 'cancel', token: it.token, sub: it.sub });
    renderItem(it); renderHead();
  }
  window.addEventListener('beforeunload', function (e) { if (running) { e.preventDefault(); e.returnValue = ''; } });

  /* ── 파일 선택 ── */
  document.querySelectorAll('[data-pick]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('details').removeAttribute('open'); document.querySelector('[data-input="' + b.dataset.pick + '"]').click(); });
  });
  document.querySelectorAll('[data-input]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var arr = Array.prototype.map.call(inp.files, function (f) {
        var rp = f.webkitRelativePath || ''; return { file: f, sub: rp ? rp.split('/').slice(0, -1).join('/') : '' };
      });
      inp.value = ''; add(arr);
    });
  });
  document.addEventListener('click', function (e) { document.querySelectorAll('details.hb-up-menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); }); });

  /* ── 끌어다 놓기 (폴더 포함) ── */
  var drop = document.querySelector('[data-drop]'), depth = 0;
  function hasFiles(e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1; }
  document.addEventListener('dragenter', function (e) { if (!hasFiles(e)) return; e.preventDefault(); depth++; drop.hidden = false; });
  document.addEventListener('dragover', function (e) { if (!hasFiles(e)) return; e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; });
  document.addEventListener('dragleave', function (e) { if (!hasFiles(e)) return; if (--depth <= 0) { depth = 0; drop.hidden = true; } });
  document.addEventListener('drop', function (e) {
    if (!hasFiles(e)) return; e.preventDefault(); depth = 0; drop.hidden = true;
    var items = e.dataTransfer.items, entries = [];
    if (items && items.length && items[0].webkitGetAsEntry) {
      for (var i = 0; i < items.length; i++) { var en = items[i].webkitGetAsEntry && items[i].webkitGetAsEntry(); if (en) entries.push(en); }
      walk(entries).then(add);
    } else add(Array.prototype.map.call(e.dataTransfer.files, function (f) { return { file: f, sub: '' }; }));
  });
  function readAll(reader) {
    return new Promise(function (res) { var all = []; (function next() { reader.readEntries(function (b) { if (!b.length) return res(all); all = all.concat(Array.prototype.slice.call(b)); next(); }, function () { res(all); }); })(); });
  }
  async function walk(entries, prefix) {
    var out = [];
    for (var i = 0; i < entries.length; i++) {
      var en = entries[i];
      if (en.isFile) { var f = await new Promise(function (r) { en.file(r, function () { r(null); }); }); if (f) out.push({ file: f, sub: prefix || '' }); }
      else if (en.isDirectory && !skipName(en.name)) out = out.concat(await walk(await readAll(en.createReader()), (prefix ? prefix + '/' : '') + en.name));
    }
    return out;
  }

  /* ── 새 폴더 ── */
  var mk = document.querySelector('[data-mkdir]');
  if (mk) mk.addEventListener('click', function () {
    var d = el('dialog', 'hb-dialog'), f = el('form', 'hb-form');
    f.method = 'dialog';
    f.innerHTML = '<b class="hb-dialog-title">새 폴더</b><label>이름<input name="n" required maxlength="200" autocomplete="off"></label><p class="hb-err" hidden></p><div class="hb-dialog-acts"><button type="button" class="hb-btn" value="cancel">취소</button><button class="hb-btn hb-btn-primary">만들기</button></div>';
    d.appendChild(f); document.body.appendChild(d); d.showModal();
    var inp = f.querySelector('input'), err = f.querySelector('.hb-err');
    f.querySelector('[value=cancel]').onclick = function () { d.close(); };
    d.addEventListener('close', function () { d.remove(); });
    f.addEventListener('submit', async function (e) {
      e.preventDefault(); err.hidden = true;
      var r = await post({ action: 'mkdir', name: inp.value });
      if (r.status === 200 && r.data.ok) { location.reload(); return; }
      err.textContent = r.data.error || '만들지 못했습니다.'; err.hidden = false;
    });
  });
})();

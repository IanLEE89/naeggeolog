/* 내 공간 · 파일: 이름 거르기, 목록/크게 보기, 사진·동영상 미리보기(← → 이동, Esc 닫기) */
(function () {
  var list = document.querySelector('[data-files]');
  if (!list) return;

  /* 이름 거르기 */
  var input = document.querySelector('[data-filter]'), nomatch = document.querySelector('[data-nomatch]');
  if (input) input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase(), n = 0;
    list.querySelectorAll('.hb-row').forEach(function (li) {
      var hit = !q || li.dataset.name.indexOf(q) !== -1; li.hidden = !hit; if (hit) n++;
    });
    if (nomatch) nomatch.hidden = n > 0;
  });

  /* 보기 방식 (이 기기에 기억) */
  var btns = document.querySelectorAll('[data-view]');
  function setView(v) {
    list.classList.toggle('is-grid', v === 'grid');
    btns.forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.view === v)); });
    try { localStorage.setItem('hub-files-view', v); } catch (e) {}
  }
  btns.forEach(function (b) { b.addEventListener('click', function () { setView(b.dataset.view); }); });
  var saved = null; try { saved = localStorage.getItem('hub-files-view'); } catch (e) {}
  if (saved === 'grid') setView('grid');
  else if (!saved && list.querySelectorAll('[data-kind="image"],[data-kind="video"]').length > list.querySelectorAll('.hb-row').length / 2) setView('grid');

  /* 미리보기 */
  var dlg = document.querySelector('[data-viewer]');
  if (!dlg || typeof dlg.showModal !== 'function') return;
  var stage = dlg.querySelector('[data-v-stage]'), nameEl = dlg.querySelector('[data-v-name]'), dlEl = dlg.querySelector('[data-v-dl]');
  var items = [], cur = -1;
  function collect() { items = Array.prototype.filter.call(list.querySelectorAll('[data-preview]'), function (a) { return !a.closest('.hb-row').hidden; }); }
  function show(i) {
    if (i < 0 || i >= items.length) return;
    cur = i; var a = items[i], type = a.dataset.type, src = a.dataset.preview, el;
    stage.textContent = '';
    if (type === 'image') { el = document.createElement('img'); el.alt = ''; el.decoding = 'async'; }
    else { el = document.createElement(type === 'audio' ? 'audio' : 'video'); el.controls = true; el.autoplay = true; el.preload = 'metadata'; el.playsInline = true; }
    el.src = src;
    if (type !== 'image') el.addEventListener('error', function () { stage.innerHTML = '<p class="hb-viewer-msg">이 브라우저에서는 바로 재생할 수 없는 형식이에요.<br>내려받아서 열어 주세요.</p>'; });
    stage.appendChild(el);
    nameEl.textContent = a.querySelector('.hb-name').textContent;
    dlEl.href = a.dataset.dl;
    dlg.querySelector('[data-v-prev]').hidden = i === 0;
    dlg.querySelector('[data-v-next]').hidden = i === items.length - 1;
    // 다음 사진 미리 불러오기
    var nx = items[i + 1]; if (nx && nx.dataset.type === 'image') { var pre = new Image(); pre.src = nx.dataset.preview; }
  }
  function close() { stage.textContent = ''; if (dlg.open) dlg.close(); }
  list.addEventListener('click', function (e) {
    var a = e.target.closest('[data-preview]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;
    e.preventDefault(); collect(); dlg.showModal(); show(items.indexOf(a));
  });
  dlg.querySelector('[data-v-close]').addEventListener('click', close);
  dlg.querySelector('[data-v-prev]').addEventListener('click', function () { show(cur - 1); });
  dlg.querySelector('[data-v-next]').addEventListener('click', function () { show(cur + 1); });
  dlg.addEventListener('close', function () { stage.textContent = ''; });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target === stage) close(); });
  dlg.addEventListener('keydown', function (e) {
    if (e.target.tagName === 'VIDEO' || e.target.tagName === 'AUDIO') return;
    if (e.key === 'ArrowLeft') show(cur - 1); else if (e.key === 'ArrowRight') show(cur + 1);
  });
  /* 휴대폰: 좌우로 밀어 넘기기 */
  var sx = null;
  stage.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
  stage.addEventListener('touchend', function (e) {
    if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; sx = null;
    if (Math.abs(dx) > 50 && items[cur] && items[cur].dataset.type === 'image') show(cur + (dx < 0 ? 1 : -1));
  });
})();

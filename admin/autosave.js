/* 편집 화면 자동저장: 사용자가 내용을 바꾼 뒤 15초마다(변경이 있을 때만) 초안을 서버에 보관한다. 저장 버튼을 누르기 전까지 실제 여행은 바뀌지 않는다. */
(function () {
  const form = document.getElementById('tripForm'), stateEl = document.getElementById('autosaveState');
  if (!form) return;
  let baseline = null, last = null, touched = false, submitting = false, busy = false, failed = false;
  const params = () => {
    const a = window.NaeggeologEditorApi;
    if (a) [...a.wrap.querySelectorAll('.section-editor')].forEach(a.syncSection);
    const p = new URLSearchParams();
    for (const [k, v] of new FormData(form)) if (typeof v === 'string') p.append(k, v);
    return p;
  };
  const sig = p => { p = new URLSearchParams(p); p.delete('csrf'); return p.toString(); };
  const say = (t, cls) => { if (stateEl) { stateEl.textContent = t; stateEl.dataset.s = cls || ''; } };
  const takeBase = () => { if (baseline !== null) return; try { baseline = sig(params()); last = baseline; } catch (e) {} };
  setTimeout(takeBase, 4000);                                                 // 편집기가 모두 뜬 뒤의 기준 상태
  ['keydown', 'pointerdown', 'beforeinput'].forEach(ev => form.addEventListener(ev, takeBase, true));   // 4초 안에 먼저 손댄 경우: 바꾸기 직전 상태가 기준
  ['input', 'change', 'keyup', 'drop'].forEach(ev => form.addEventListener(ev, () => { touched = true; }, true));
  form.addEventListener('click', e => { if (e.target.closest('button,[data-action],.section-handle')) touched = true; }, true);
  form.addEventListener('submit', () => { submitting = true; });
  const body = p => { p.set('csrf', window.AUTOSAVE_CSRF); p.set('autosave_key', window.AUTOSAVE_KEY); p.set('upload_key', window.ADMIN_UPLOAD_KEY || ''); return p; };
  async function run() {
    if (busy || submitting || !touched || baseline === null) return;
    const p = params(), s = sig(p);
    if (s === last) return;
    busy = true;
    try {
      const r = await fetch('trip-autosave.php', { method: 'POST', body: body(p), credentials: 'same-origin' });
      const j = await r.json();
      if (j.ok) { if (j.saved !== false) { last = s; failed = false; say('자동저장됨 ' + (j.at || '').slice(0, 5), 'ok'); } }
      else { failed = true; say('자동저장 실패', 'err'); }
    } catch (e) { failed = true; say('자동저장 실패', 'err'); }
    busy = false;
  }
  setInterval(run, 15000);
  const flush = () => {
    if (submitting || !touched || baseline === null) return;
    try { const p = params(); if (sig(p) !== last && navigator.sendBeacon) navigator.sendBeacon('trip-autosave.php', body(p)); } catch (e) {}
  };
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });
  window.addEventListener('pagehide', flush);
  window.addEventListener('beforeunload', e => {
    if (submitting || !touched || baseline === null) return;
    try { if (sig(params()) !== last) { e.preventDefault(); e.returnValue = ''; } } catch (x) {}
  });
})();

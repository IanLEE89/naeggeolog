/* 백그라운드 썸네일 — 업로드 후 저장이 끝나면 이 화면에서 한 장씩 조용히 만든다(창을 닫아도 다음 접속 때 이어서). */
(function () {
  var csrf = window.BG_CSRF; if (!csrf || !window.fetch) return;
  var pill = null;
  function show(txt, done) {
    if (!pill) {
      pill = document.createElement('div'); pill.setAttribute('role', 'status');
      pill.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:3000;padding:9px 15px;border-radius:999px;background:#1c1917;color:#fff;font-size:12px;box-shadow:0 8px 24px rgba(0,0,0,.2);transition:opacity .4s';
      document.body.appendChild(pill);
    }
    pill.style.opacity = '1'; pill.textContent = txt;
    if (done) setTimeout(function () { pill.style.opacity = '0'; }, 2800);
  }
  fetch('thumb-bg.php', { cache: 'no-store', credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(async function (d) {
    var items = d.items || []; if (!items.length) return;
    var total = items.length, n = 0, fails = 0;
    for (var rel of items) {
      show('🖼 썸네일 준비 중 ' + n + ' / ' + total);
      try {
        var fd = new FormData(); fd.append('csrf', csrf); fd.append('rel', rel);
        var r = await fetch('thumb-bg.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        if (!r.ok) throw new Error(r.status);
      } catch (e) { if (++fails >= 3) { show('썸네일 준비를 잠시 멈췄습니다 (다음 접속 때 이어서 진행)', true); return; } }
      n++;
    }
    show('✓ 썸네일 ' + total + '장 준비 완료', true);
  }).catch(function () {});
})();

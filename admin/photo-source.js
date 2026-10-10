/* Naeggeolog — 사진 가져오기 공용 모듈 (관리자 여행 편집 화면)
 *  1) HEIC(아이폰·갤럭시 고효율 사진): 브라우저에서 JPG 로 바꿔 올린다.
 *     - 사파리처럼 HEIC 를 직접 열 수 있는 브라우저는 내장 기능으로, 아니면 vendor/heic-to.js(libheif)를 그때만 불러와 변환
 *     - 변환하면 EXIF 가 사라지므로, 원본에서 촬영 시각·위치를 먼저 읽어(vendor/exifr-lite.js) 함께 보낸다
 *  2) NAS 에서 선택: "내 공간 → 공유 폴더"에 연결한 시놀로지 폴더(예: 사진 백업 폴더)를 탐색해 여러 장 고르기
 *     - JPG/PNG/WEBP 는 서버가 NAS 에서 바로 복사(사진 데이터가 내 기기를 거치지 않음)
 *     - HEIC 는 원본을 받아 1)과 같은 방식으로 변환해 올림
 *  window.NaeggeologPhotos 로 노출: editor.js(소제목 사진) · photo-organizer.js(자동 정리)가 사용
 */
(function () {
  'use strict';
  const base = (document.currentScript && document.currentScript.src) ? new URL('.', document.currentScript.src).href : '';
  const MAX_EDGE = typeof window.PHOTO_MAX_EDGE === 'number' && window.PHOTO_MAX_EDGE >= 0 ? window.PHOTO_MAX_EDGE : 3200;   // 0 = 줄이지 않음 (config.php PHOTO_MAX_EDGE)
  const ACCEPT = 'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const csrfDefault = () => document.querySelector('input[name="csrf"]')?.value || '';

  /* ───────── 형식 판별 ───────── */
  const isHeic = f => !!f && (/^image\/hei[cf](-sequence)?$/i.test(f.type || '') || /\.(heic|heif)$/i.test(f.name || ''));
  const isImage = f => !!f && (/^image\/(jpeg|png|webp)$/i.test(f.type || '') || /\.(jpe?g|png|webp)$/i.test(f.name || '') || isHeic(f));

  /* ───────── 스크립트 지연 로드 ───────── */
  const loaded = new Map();
  function loadScript(file, globalName) {
    if (window[globalName]) return Promise.resolve(window[globalName]);
    if (!loaded.has(file)) {
      loaded.set(file, new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = base + file; s.async = true;
        s.onload = () => window[globalName] ? resolve(window[globalName]) : reject(new Error(file + ' 를 불러왔지만 사용할 수 없습니다.'));
        s.onerror = () => { loaded.delete(file); reject(new Error(file + ' 를 불러오지 못했습니다. 네트워크를 확인하세요.')); };
        document.head.appendChild(s);
      }));
    }
    return loaded.get(file);
  }

  /* ───────── HEIC → JPG ───────── */
  // 원본에서 촬영 시각(카메라 기준 현지 시각 문자열)과 GPS 를 읽는다. 실패해도 업로드는 계속
  async function readMeta(blob) {
    try {
      const exifr = await loadScript('vendor/exifr-lite.js', 'exifr');
      const r = await exifr.parse(blob, { reviveValues: false, translateValues: false });
      if (!r) return {};
      const m = {};
      const t = r.DateTimeOriginal || r.CreateDate || r.ModifyDate;
      const x = typeof t === 'string' && t.match(/^(\d{4}):(\d{2}):(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
      if (x && x[1] !== '0000') m.taken = `${x[1]}-${x[2]}-${x[3]} ${x[4]}:${x[5]}:${x[6]}`;
      if (Number.isFinite(r.latitude) && Number.isFinite(r.longitude) && !(r.latitude === 0 && r.longitude === 0)) { m.lat = r.latitude; m.lng = r.longitude; }
      return m;
    } catch (e) { return {}; }
  }
  // 저사양 기기 보호: 변환은 한 번에 한 장씩
  let chain = Promise.resolve();
  const serial = fn => { const r = chain.then(fn, fn); chain = r.catch(() => {}); return r; };
  async function decode(blob) {
    if (window.createImageBitmap) {
      try { return await createImageBitmap(blob, { imageOrientation: 'from-image' }); } catch (e) { /* 이 브라우저는 HEIC 를 직접 못 엶 → 라이브러리 */ }
    }
    const heicTo = await loadScript('vendor/heic-to.js', 'HeicTo');
    return await heicTo({ blob, type: 'bitmap' });
  }
  function bitmapToJpeg(bm) {
    return new Promise((resolve, reject) => {
      let w = bm.width, h = bm.height;
      const long = Math.max(w, h);
      if (MAX_EDGE > 0 && long > MAX_EDGE) { w = Math.round(w * MAX_EDGE / long); h = Math.round(h * MAX_EDGE / long); }
      const c = document.createElement('canvas'); c.width = w; c.height = h;
      const ctx = c.getContext('2d'); ctx.imageSmoothingQuality = 'high'; ctx.drawImage(bm, 0, 0, w, h);
      if (bm.close) bm.close();
      c.toBlob(b => { c.width = c.height = 0; b ? resolve(b) : reject(new Error('JPG 로 저장하지 못했습니다.')); }, 'image/jpeg', 0.92);
    });
  }
  /** HEIC 파일 → { file: JPG File, meta: {taken, lat, lng} } */
  function convertHeic(file) {
    return serial(async () => {
      const meta = await readMeta(file);
      let bm;
      try { bm = await decode(file); }
      catch (e) { throw new Error('HEIC 사진을 열지 못했습니다' + (e && e.message ? ' (' + e.message + ')' : '') + '. 휴대폰에서 JPG 로 내보내 올려 주세요.'); }
      const jpg = await bitmapToJpeg(bm);
      const name = String(file.name || 'photo.heic').replace(/\.(heic|heif)$/i, '') + '.jpg';
      return { file: new File([jpg], name, { type: 'image/jpeg', lastModified: file.lastModified || Date.now() }), meta };
    });
  }

  /* ───────── 서버로 보내기 ───────── */
  function xhr(url, fd, onProgress, responseType) {
    return new Promise((resolve, reject) => {
      const x = new XMLHttpRequest();
      x.open(fd ? 'POST' : 'GET', url);
      if (responseType) x.responseType = responseType;
      const tgt = fd ? x.upload : x;
      if (onProgress) tgt.onprogress = e => { if (e.lengthComputable) onProgress(e.loaded / e.total); };
      x.onerror = () => reject(new Error('네트워크 오류로 처리하지 못했습니다.'));
      x.onload = () => {
        if (responseType === 'blob') {
          if (x.status !== 200) return reject(new Error('NAS 에서 사진을 받지 못했습니다. (' + x.status + ')'));
          return resolve(x.response);
        }
        let d = null; try { d = JSON.parse(x.responseText); } catch (e) {}
        if (!d) return reject(new Error('서버 응답을 읽을 수 없습니다. (용량 제한을 확인하세요)'));
        if (!d.ok) return reject(new Error(d.message || '실패'));
        resolve(d);
      };
      x.send(fd || null);
    });
  }
  const nasUrl = (a, item) => `nas-photos.php?a=${a}&s=${encodeURIComponent(item.s)}&p=${encodeURIComponent(item.p)}`;
  let heicServer = false;   // 서버가 HEIC 를 직접 처리할 수 있으면(Imagick + PHP 8.2) NAS 의 HEIC 도 서버에서 바로

  /**
   * 사진 한 장(File 또는 NAS 항목)을 여행 폴더에 넣는다.
   * opts: { folder, uploadKey, csrf, onStatus(text), onPreview(url) }
   * 반환: upload.php / nas-photos.php 응답 { path, name, taken, lat, lng, w, h }
   */
  async function uploadItem(item, opts) {
    const status = opts.onStatus || (() => {});
    const csrf = opts.csrf || csrfDefault();
    let file = item;
    if (item && item.nas) {
      if (!item.heic || heicServer) {
        status('NAS 에서 가져오는 중…');
        const fd = new FormData();
        fd.append('a', 'import'); fd.append('csrf', csrf); fd.append('s', item.s); fd.append('p', item.p); fd.append('photo_folder', opts.folder);
        return await xhr('nas-photos.php', fd);
      }
      status('NAS 에서 받는 중… 0%');
      const blob = await xhr(nasUrl('raw', item), null, p => status('NAS 에서 받는 중… ' + Math.round(p * 100) + '%'), 'blob');
      file = new File([blob], item.name, { type: 'image/heic', lastModified: (item.mtime || 0) * 1000 || Date.now() });
    }
    let meta = {};
    if (isHeic(file)) {
      status('HEIC → JPG 변환 중…');
      const r = await convertHeic(file);
      file = r.file; meta = r.meta || {};
      if (opts.onPreview) { try { opts.onPreview(URL.createObjectURL(file)); } catch (e) {} }
    }
    const fd = new FormData();
    fd.append('csrf', csrf); fd.append('upload_key', opts.uploadKey || ''); fd.append('photo_folder', opts.folder);
    if (meta.taken) fd.append('client_taken', meta.taken);
    if (meta.lat != null) { fd.append('client_lat', String(meta.lat)); fd.append('client_lng', String(meta.lng)); }
    fd.append('image', file);
    status('업로드 중… 0%');
    return await xhr('upload.php', fd, p => status('업로드 중… ' + Math.round(p * 100) + '%'));
  }

  /* ───────── NAS 선택 창 ───────── */
  const CSS = `
html:has(dialog.nasp[open]){overflow:hidden}
dialog.nasp{width:min(980px,calc(100vw - 24px));height:min(780px,calc(100dvh - 24px));max-width:none;max-height:none;padding:0;border:0;border-radius:14px;background:#fff;color:#292524;box-shadow:0 24px 60px rgba(28,25,23,.28);overflow:hidden}
dialog.nasp[open]{display:flex;flex-direction:column}
dialog.nasp::backdrop{background:rgba(28,25,23,.45)}
.nasp-head{display:flex;flex-wrap:wrap;align-items:center;gap:8px 10px;padding:14px 16px 10px;border-bottom:1px solid #e7e5e4}
.nasp-head h2{margin:0 auto 0 0;font-size:15px;font-weight:700;letter-spacing:-.01em}
.nasp select{height:36px;border:1px solid #d6d3d1;border-radius:7px;background:#fff;padding:0 8px;font-size:12px;color:#44403c;max-width:220px}
.nasp-x{width:36px;height:36px;border:1px solid #e7e5e4;border-radius:8px;background:#fff;color:#57534e;font-size:16px;line-height:1;cursor:pointer}
.nasp-crumbs{display:flex;flex-wrap:wrap;align-items:center;gap:2px;padding:8px 14px;font-size:12px;color:#78716c;border-bottom:1px solid #f5f5f4}
.nasp-crumbs button{border:0;background:none;padding:4px 6px;border-radius:5px;color:#57534e;cursor:pointer;font:inherit}
.nasp-crumbs button:hover{background:#f5f5f4;color:#1c1917}
.nasp-crumbs button[aria-current]{color:#1c1917;font-weight:600;cursor:default;background:none}
.nasp-body{flex:1;overflow:auto;padding:12px 14px;overscroll-behavior:contain}
.nasp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:8px}
.nasp-tile{position:relative;display:flex;flex-direction:column;border:1px solid #e7e5e4;border-radius:9px;background:#fafaf9;padding:0;overflow:hidden;cursor:pointer;text-align:left;color:inherit;font:inherit}
.nasp-tile:hover{border-color:#a8a29e}
.nasp-tile:focus-visible{outline:2px solid #57534e;outline-offset:2px}
.nasp-dir{flex-direction:row;align-items:center;gap:8px;padding:10px;min-height:52px;background:#fff}
.nasp-dir svg{flex:none;width:22px;height:22px;color:#a8a29e}
.nasp-dir span{font-size:12px;font-weight:600;color:#44403c;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nasp-ph .nasp-img{position:relative;aspect-ratio:1;background:#e7e5e4;display:flex;align-items:center;justify-content:center;color:#a8a29e;font-size:11px;font-weight:700;letter-spacing:.04em}
.nasp-ph img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.nasp-ph .nasp-name{padding:5px 7px;font-size:10.5px;color:#78716c;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nasp-badge{position:absolute;left:5px;bottom:5px;padding:1px 5px;border-radius:4px;background:rgba(28,25,23,.62);color:#fff;font-size:9.5px;font-weight:700;letter-spacing:.03em}
.nasp-chk{position:absolute;right:6px;top:6px;width:22px;height:22px;border-radius:50%;border:2px solid #fff;background:rgba(28,25,23,.25);box-shadow:0 1px 3px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700}
.nasp-ph[aria-pressed="true"]{border-color:#292524;box-shadow:0 0 0 2px #292524}
.nasp-ph[aria-pressed="true"] .nasp-chk{background:#292524}
.nasp-ph[aria-pressed="true"] .nasp-chk::after{content:"✓"}
.nasp-sec{margin:14px 2px 8px;font-size:11px;font-weight:600;color:#a8a29e}
.nasp-sec:first-child{margin-top:2px}
.nasp-msg{padding:40px 16px;text-align:center;font-size:13px;line-height:1.7;color:#78716c}
.nasp-msg a{color:#292524;text-decoration:underline;text-underline-offset:3px}
.nasp-more{display:block;margin:14px auto 4px;height:38px;padding:0 18px;border:1px solid #d6d3d1;border-radius:8px;background:#fff;font-size:12px;color:#57534e;cursor:pointer}
.nasp-foot{display:flex;flex-wrap:wrap;align-items:center;gap:8px 10px;padding:12px 16px;border-top:1px solid #e7e5e4;background:#fafaf9}
.nasp-info{margin-right:auto;font-size:12px;color:#78716c}
.nasp-info b{color:#292524}
.nasp-btn{height:40px;padding:0 14px;border:1px solid #d6d3d1;border-radius:8px;background:#fff;color:#44403c;font-size:12px;font-weight:600;cursor:pointer}
.nasp-btn:disabled{opacity:.45;cursor:default}
.nasp-btn.primary{background:#292524;border-color:#292524;color:#fff}
@media (max-width:640px){
  dialog.nasp{width:100vw;height:100dvh;border-radius:0}
  .nasp-grid{grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:6px}
  .nasp select{max-width:46vw}
  .nasp-info{width:100%}
}`;
  const FOLDER_ICON = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h3.6c.66 0 1.3.26 1.77.73L12.1 6h6.4A2.5 2.5 0 0 1 21 8.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5z"/></svg>';
  const LS_KEY = 'tt-nas-pick';
  const store = { get() { try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}') || {}; } catch (e) { return {}; } }, set(v) { try { localStorage.setItem(LS_KEY, JSON.stringify(v)); } catch (e) {} } };

  let dlg = null, ui = {}, shares = [], cur = { s: 0, p: '', sort: 'new' }, listing = null, selected = new Map(), lastIdx = -1, resolver = null, reqNo = 0;

  function build() {
    if (dlg) return;
    const st = document.createElement('style'); st.textContent = CSS; document.head.appendChild(st);
    dlg = document.createElement('dialog');
    dlg.className = 'nasp';
    dlg.setAttribute('aria-labelledby', 'naspTitle');
    dlg.innerHTML = `
      <div class="nasp-head">
        <h2 id="naspTitle">NAS 에서 사진 선택</h2>
        <select class="nasp-share" aria-label="공유 폴더"></select>
        <select class="nasp-sort" aria-label="정렬"><option value="new">최신순</option><option value="old">오래된 순</option><option value="name">이름순</option></select>
        <button type="button" class="nasp-x" aria-label="닫기">✕</button>
      </div>
      <nav class="nasp-crumbs" aria-label="폴더 위치"></nav>
      <div class="nasp-body" tabindex="-1"></div>
      <div class="nasp-foot">
        <span class="nasp-info" aria-live="polite"></span>
        <button type="button" class="nasp-btn nasp-all">이 폴더 모두 선택</button>
        <button type="button" class="nasp-btn nasp-cancel">취소</button>
        <button type="button" class="nasp-btn primary nasp-ok" disabled>가져오기</button>
      </div>`;
    document.body.appendChild(dlg);
    ui = { share: dlg.querySelector('.nasp-share'), sort: dlg.querySelector('.nasp-sort'), crumbs: dlg.querySelector('.nasp-crumbs'), body: dlg.querySelector('.nasp-body'),
           info: dlg.querySelector('.nasp-info'), all: dlg.querySelector('.nasp-all'), ok: dlg.querySelector('.nasp-ok') };
    dlg.querySelector('.nasp-x').addEventListener('click', () => dlg.close('cancel'));
    dlg.querySelector('.nasp-cancel').addEventListener('click', () => dlg.close('cancel'));
    ui.ok.addEventListener('click', () => dlg.close('ok'));
    dlg.addEventListener('close', () => {
      const items = dlg.returnValue === 'ok' ? [...selected.values()].sort((a, b) => (a.mtime - b.mtime) || a.name.localeCompare(b.name, undefined, { numeric: true })) : [];
      selected.clear();
      const r = resolver; resolver = null; if (r) r(items);
    });
    ui.share.addEventListener('change', () => open(+ui.share.value, ''));
    ui.sort.addEventListener('change', () => { cur.sort = ui.sort.value; open(cur.s, cur.p); });
    ui.crumbs.addEventListener('click', e => { const b = e.target.closest('button[data-p]'); if (b && !b.hasAttribute('aria-current')) open(cur.s, b.dataset.p); });
    ui.all.addEventListener('click', () => {
      if (!listing) return;
      const items = listing.files.map(f => toItem(f));
      const allOn = items.length && items.every(it => selected.has(key(it)));
      items.forEach(it => allOn ? selected.delete(key(it)) : selected.set(key(it), it));
      syncTiles(); updateInfo();
    });
    ui.body.addEventListener('click', e => {
      const d = e.target.closest('.nasp-dir'); if (d) { open(cur.s, d.dataset.p); return; }
      const m = e.target.closest('.nasp-more'); if (m) { loadMore(); return; }
      const t = e.target.closest('.nasp-ph'); if (!t) return;
      const idx = +t.dataset.i, f = listing.files[idx]; if (!f) return;
      const it = toItem(f), on = !selected.has(key(it));
      if (e.shiftKey && lastIdx >= 0 && lastIdx !== idx) {      // Shift+클릭: 범위 선택
        const [a, b] = idx > lastIdx ? [lastIdx, idx] : [idx, lastIdx];
        for (let i = a; i <= b; i++) { const x = toItem(listing.files[i]); on ? selected.set(key(x), x) : selected.delete(key(x)); }
      } else { on ? selected.set(key(it), it) : selected.delete(key(it)); }
      lastIdx = idx; syncTiles(); updateInfo();
    });
  }
  const key = it => it.s + '|' + it.p;
  const joinPath = (dir, name) => dir ? dir + '/' + name : name;
  function toItem(f) {
    const p = joinPath(listing.path, f.name);
    const it = { nas: true, s: listing.share.id, p, name: f.name, size: f.size, mtime: f.mtime, heic: !!f.heic };
    it.thumb = nasUrl('thumb', it);
    return it;
  }
  function setMsg(html) { ui.body.innerHTML = `<div class="nasp-msg">${html}</div>`; }
  function updateInfo() {
    const n = selected.size;
    const here = listing ? listing.total : 0;
    ui.info.innerHTML = (n ? `<b>${n}장</b> 선택` : '사진을 눌러 고르세요 (Shift+클릭: 범위)') + (listing ? ` · 이 폴더 사진 ${here}장` + (listing.other ? ` · 동영상 등 ${listing.other}개는 제외` : '') : '');
    ui.ok.disabled = !n; ui.ok.textContent = n ? `${n}장 가져오기` : '가져오기';
    const all = listing && listing.files.length && listing.files.every(f => selected.has(listing.share.id + '|' + joinPath(listing.path, f.name)));
    ui.all.textContent = all ? '이 폴더 선택 해제' : '이 폴더 모두 선택';
    ui.all.disabled = !listing || !listing.files.length;
  }
  function syncTiles() {
    ui.body.querySelectorAll('.nasp-ph').forEach(t => {
      const f = listing.files[+t.dataset.i];
      t.setAttribute('aria-pressed', selected.has(listing.share.id + '|' + joinPath(listing.path, f.name)) ? 'true' : 'false');
    });
  }
  function photoTile(f, i) {
    const it = toItem(f), ext = (f.name.split('.').pop() || '').toUpperCase();
    return `<button type="button" class="nasp-tile nasp-ph" data-i="${i}" aria-pressed="${selected.has(key(it)) ? 'true' : 'false'}" title="${esc(f.name)}">
      <span class="nasp-img">${f.heic ? '미리보기 없음' : esc(ext)}<img loading="lazy" decoding="async" alt="" src="${esc(it.thumb)}" onerror="this.remove()">${f.heic ? '<span class="nasp-badge">HEIC</span>' : ''}<span class="nasp-chk" aria-hidden="true"></span></span>
      <span class="nasp-name">${esc(f.name)}</span></button>`;
  }
  function render() {
    const L = listing;
    ui.crumbs.innerHTML = `<button type="button" data-p="" ${L.crumbs.length ? '' : 'aria-current="page"'}>${esc(L.share.name)}</button>` +
      L.crumbs.map((c, i) => `<span aria-hidden="true">›</span><button type="button" data-p="${esc(c.path)}" ${i === L.crumbs.length - 1 ? 'aria-current="page"' : ''}>${esc(c.name)}</button>`).join('');
    let h = '';
    if (L.dirs.length) h += `<p class="nasp-sec">폴더 ${L.dirs.length}</p><div class="nasp-grid">` + L.dirs.map(d => `<button type="button" class="nasp-tile nasp-dir" data-p="${esc(joinPath(L.path, d.name))}">${FOLDER_ICON}<span>${esc(d.name)}</span></button>`).join('') + '</div>';
    if (L.files.length) h += `<p class="nasp-sec">사진 ${L.total}</p><div class="nasp-grid nasp-photos">` + L.files.map(photoTile).join('') + '</div>';
    if (L.more) h += '<button type="button" class="nasp-more">사진 더 보기</button>';
    if (!L.dirs.length && !L.files.length) h = `<div class="nasp-msg">이 폴더에는 사진(JPG·PNG·WEBP·HEIC)이 없습니다.${L.other ? `<br>(다른 파일 ${L.other}개)` : ''}</div>`;
    ui.body.innerHTML = h;
    updateInfo();
  }
  async function fetchList(s, p, o) {
    const r = await fetch(`nas-photos.php?a=list&s=${encodeURIComponent(s)}&p=${encodeURIComponent(p)}&o=${o}&sort=${encodeURIComponent(cur.sort)}`, { credentials: 'same-origin' });
    let d = null; try { d = await r.json(); } catch (e) {}
    if (!d) throw new Error(r.status === 200 ? '서버 응답을 읽을 수 없습니다.' : '로그인이 풀렸거나 서버 오류입니다. (' + r.status + ')');
    if (!d.ok) throw new Error(d.message || '폴더를 열 수 없습니다.');
    return d;
  }
  async function open(s, p) {
    const no = ++reqNo;
    cur.s = s; cur.p = p; lastIdx = -1;
    store.set({ s, p, sort: cur.sort });
    setMsg('불러오는 중…');
    try {
      const d = await fetchList(s, p, 0);
      if (no !== reqNo) return;
      listing = d; render(); ui.body.scrollTop = 0;
    } catch (e) {
      if (no !== reqNo) return;
      if (p) { open(s, ''); return; }           // 기억해 둔 하위 폴더가 사라졌으면 맨 위로
      listing = null; ui.crumbs.innerHTML = ''; setMsg(esc(e.message)); updateInfo();
    }
  }
  async function loadMore() {
    const L = listing, btn = ui.body.querySelector('.nasp-more'); if (!L || !btn) return;
    btn.disabled = true; btn.textContent = '불러오는 중…';
    try {
      const d = await fetchList(L.share.id, L.path, L.offset + L.files.length);
      if (listing !== L) return;
      const start = L.files.length; L.files = L.files.concat(d.files); L.more = d.more;
      btn.remove();
      ui.body.querySelector('.nasp-photos').insertAdjacentHTML('beforeend', d.files.map((f, j) => photoTile(f, start + j)).join(''));
      if (L.more) ui.body.insertAdjacentHTML('beforeend', '<button type="button" class="nasp-more">사진 더 보기</button>');
      updateInfo();
    } catch (e) { btn.disabled = false; btn.textContent = '다시 시도 — ' + e.message; }
  }

  /** 선택 창을 열고, 고른 NAS 항목 배열을 돌려준다(취소하면 빈 배열) */
  async function pickFromNas() {
    build();
    if (resolver) return [];
    selected.clear(); listing = null;
    const saved = store.get();
    cur.sort = ['new', 'old', 'name'].includes(saved.sort) ? saved.sort : 'new';
    ui.sort.value = cur.sort;
    ui.crumbs.innerHTML = ''; setMsg('공유 폴더 확인 중…'); updateInfo();
    const done = new Promise(r => { resolver = r; });
    dlg.showModal();
    try {
      const r = await fetch('nas-photos.php?a=shares', { credentials: 'same-origin' });
      const d = await r.json().catch(() => null);
      if (!d || !d.ok) throw new Error((d && d.message) || '공유 폴더 목록을 불러오지 못했습니다.');
      shares = d.shares || []; heicServer = !!d.heic_server;
    } catch (e) { setMsg(esc(e.message)); return done; }
    if (!shares.length) {
      ui.share.innerHTML = ''; ui.share.hidden = true;
      setMsg('아직 연결한 NAS 폴더가 없습니다.<br><a href="hub-folders.php" target="_blank" rel="noopener">내 공간 → 공유 폴더</a>에서 사진 백업 폴더를 먼저 연결해 주세요.<br><span style="font-size:12px">볼 사람을 아무도 지정하지 않으면 주인(나)만 볼 수 있습니다.</span>');
      return done;
    }
    ui.share.hidden = false;
    ui.share.innerHTML = shares.map(s => `<option value="${s.id}">${esc(s.name)}${s.hidden ? ' (숨김)' : ''}${s.ok ? '' : ' ⚠'}</option>`).join('');
    const start = shares.find(s => s.id === saved.s) ? saved.s : shares[0].id;
    ui.share.value = String(start);
    open(start, start === saved.s ? (saved.p || '') : '');
    return done;
  }

  window.NaeggeologPhotos = { ACCEPT, isHeic, isImage, convertHeic, readMeta, uploadItem, pickFromNas };
})();

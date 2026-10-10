/* 사진으로 자동 정리 — 여러 장 업로드 → 촬영시간/위치로 정렬·묶기 → 소제목(섹션) 생성
 * editor.js 가 노출한 window.NaeggeologEditorApi 를 사용합니다. */
(function () {
  'use strict';
  const api = window.NaeggeologEditorApi;
  const input = document.getElementById('poFiles');
  if (!api || !input || !window.NaeggeologPhotos) return;
  const nasBtn = document.getElementById('poNas');
  const $ = id => document.getElementById(id);
  const statusEl = $('poStatus'), groupsEl = $('poGroups'), actions = $('poActions'), hint = $('poHint');
  const esc = api.esc;
  let photos = [];      // 업로드된 사진 [{path,name,taken,takenSrc,lat,lng}]
  let groups = [];      // [{photos:[], title, place, lat, lng, on:true}]
  let busy = false;

  /* ── 유틸 ── */
  const pad = n => String(n).padStart(2, '0');
  const fmtLocal = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  const toMs = t => t ? Date.parse(t.replace(' ', 'T')) : NaN;
  function haversine(a, b) {
    const R = 6371, rad = x => x * Math.PI / 180;
    const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(h));
  }
  function centroid(list) {
    const g = list.filter(p => p.lat != null && p.lng != null);
    if (!g.length) return null;
    return { lat: g.reduce((s, p) => s + p.lat, 0) / g.length, lng: g.reduce((s, p) => s + p.lng, 0) / g.length };
  }
  function daypart(t) {
    const h = +(t || '').slice(11, 13);
    if (isNaN(h)) return '';
    return h < 6 ? '새벽' : h < 12 ? '오전' : h < 17 ? '오후' : h < 21 ? '저녁' : '밤';
  }
  const mdLabel = t => t ? `${+t.slice(5, 7)}월 ${+t.slice(8, 10)}일` : '';
  const hm = t => t ? t.slice(11, 16) : '';

  /* ── 업로드 (동시 3개) ── */
  // item: 내 기기의 File 또는 NAS 항목({nas:true, name, mtime, …}). HEIC 은 NaeggeologPhotos 가 JPG 로 바꾸면서 촬영 정보를 함께 보낸다
  async function uploadOne(item) {
    const data = await NaeggeologPhotos.uploadItem(item, { folder: api.currentPhotoFolder(), uploadKey: api.uploadKey, csrf: api.csrf });
    let taken = data.taken, src = 'exif';
    const fileMs = item.nas ? (item.mtime || 0) * 1000 : item.lastModified;
    if (!taken && fileMs) { taken = fmtLocal(new Date(fileMs)); src = 'file'; }
    return { path: data.path, name: item.name, taken, takenSrc: taken ? src : '', lat: data.lat, lng: data.lng };
  }
  async function handleFiles(files) {
    if (busy) return;
    if (!/^\d{4}-\d{2}-\d{2} [a-z]/.test(api.currentPhotoFolder())) { alert('먼저 위쪽의 여행날짜와 국가(영문)를 입력해 주세요. 사진 폴더 이름에 사용됩니다.'); return; }
    files = files.filter(f => f.nas || NaeggeologPhotos.isImage(f));
    if (!files.length) return;
    busy = true; input.disabled = true; if (nasBtn) nasBtn.disabled = true;
    const fails = []; let done = 0, next = 0;
    const worker = async () => {
      while (next < files.length) {
        const f = files[next++];
        try { photos.push(await uploadOne(f)); } catch (e) { fails.push(f.name + ' — ' + e.message); }
        statusEl.textContent = `업로드 중… ${++done} / ${files.length}`;
      }
    };
    statusEl.textContent = `업로드 중… 0 / ${files.length}`;
    await Promise.all([worker(), worker(), worker()]);
    busy = false; input.disabled = false; input.value = ''; if (nasBtn) nasBtn.disabled = false;
    statusEl.textContent = `${files.length - fails.length}장 업로드 완료` + (fails.length ? ` · 실패 ${fails.length}장: ${fails.slice(0, 3).join(' / ')}` : '');
    await regroup();
  }

  /* ── 묶기 ── */
  function buildGroups() {
    const gapSel = $('poGap').value;
    const sorted = photos.slice().sort((a, b) => (a.taken || '9999').localeCompare(b.taken || '9999') || a.name.localeCompare(b.name, undefined, { numeric: true }));
    const out = [];
    for (const p of sorted) {
      const g = out[out.length - 1];
      let split = !g;
      if (g) {
        const last = g.photos[g.photos.length - 1];
        if (!p.taken || !last.taken) split = !!p.taken !== !!last.taken;           // 시간 정보 유무가 바뀌면 분리
        else if (gapSel === 'day') split = p.taken.slice(0, 10) !== last.taken.slice(0, 10);
        else split = (toMs(p.taken) - toMs(last.taken)) / 60000 > +gapSel;
        // 직전 사진에서 1km 이상 떨어지면 다른 장면(걸으며 조금씩 이동하는 것은 같은 장면으로 본다)
        if (!split && last.lat != null && p.lat != null && haversine(last, p) > 1) split = true;
      }
      if (split) out.push({ photos: [p], on: true, title: '', place: '' }); else g.photos.push(p);
    }
    out.forEach(g => { const c = centroid(g.photos); g.lat = c ? c.lat : null; g.lng = c ? c.lng : null; });
    return out;
  }
  async function reverseGeocode(lat, lng) {
    try {
      if (!window.google || !google.maps || !google.maps.importLibrary) return '';
      const { Geocoder } = await google.maps.importLibrary('geocoding');
      const r = await new Geocoder().geocode({ location: { lat, lng }, language: 'ko' });
      const res = (r.results || [])[0]; if (!res) return '';
      const pick = types => (res.address_components || []).find(c => types.some(t => c.types.includes(t)));
      const comp = pick(['point_of_interest', 'premise', 'natural_feature']) || pick(['sublocality_level_1', 'sublocality', 'neighborhood']) || pick(['locality']) || pick(['administrative_area_level_1']);
      return comp ? comp.long_name : '';
    } catch (e) { return ''; }
  }
  async function regroup() {
    groups = buildGroups();
    if ($('poPlace').checked) {
      statusEl.textContent += ' · 장소 이름 확인 중…';
      for (const g of groups) if (g.lat != null && !g.place) g.place = await reverseGeocode(g.lat, g.lng);
    }
    groups.forEach(g => { if (!g.title) g.title = defaultTitle(g); });
    statusEl.textContent = statusEl.textContent.replace(' · 장소 이름 확인 중…', '');
    render();
  }
  function defaultTitle(g) {
    const t = g.photos[0].taken, dp = daypart(t);
    return g.place ? `${g.place}${dp ? ' ' + dp : ''}` : (t ? `${mdLabel(t)}${dp ? ' ' + dp : ''}` : '시간 정보 없는 사진');
  }

  /* ── 화면 ── */
  function render() {
    groupsEl.innerHTML = '';
    actions.classList.toggle('hidden', !groups.length);
    if (!groups.length) return;
    groups.forEach((g, gi) => {
      const first = g.photos[0], last = g.photos[g.photos.length - 1];
      const range = first.taken ? `${mdLabel(first.taken)} ${hm(first.taken)}${last.taken && last !== first ? ' – ' + (last.taken.slice(0, 10) !== first.taken.slice(0, 10) ? mdLabel(last.taken) + ' ' : '') + hm(last.taken) : ''}` : '촬영 시간 없음';
      const srcNote = g.photos.some(p => p.takenSrc === 'file') ? ' <span class="text-amber-700">(일부는 파일 수정 시각 기준)</span>' : '';
      const card = document.createElement('div');
      card.className = 'po-group rounded-xl border border-stone-200 bg-white p-4';
      card.innerHTML = `<div class="flex flex-wrap items-center gap-3">
          <label class="flex items-center gap-2 text-xs text-stone-500"><input type="checkbox" data-gi="${gi}" class="po-on" ${g.on ? 'checked' : ''}> 만들기</label>
          <input class="po-title admin-input min-w-[200px] flex-1" data-gi="${gi}" value="${esc(g.title)}" aria-label="소제목">
          ${gi > 0 ? `<button type="button" class="po-merge rounded-md border border-stone-300 bg-white px-2.5 py-1.5 text-[11px] text-stone-600" data-gi="${gi}">↑ 위 묶음과 합치기</button>` : ''}
        </div>
        <p class="mt-2 text-[11px] text-stone-500">${esc(range)} · ${g.photos.length}장${g.lat != null ? ` · 📍 ${g.lat.toFixed(4)}, ${g.lng.toFixed(4)}${g.place ? ' (' + esc(g.place) + ')' : ''}` : ' · 위치 정보 없음'}${srcNote}</p>
        <div class="mt-3 flex flex-wrap gap-1.5">${g.photos.slice(0, 10).map(p => `<img class="h-14 w-14 rounded object-cover" loading="lazy" alt="" src="../img.php?p=${encodeURIComponent(p.path)}&w=240">`).join('')}${g.photos.length > 10 ? `<span class="flex h-14 w-14 items-center justify-center rounded bg-stone-100 text-[11px] text-stone-500">+${g.photos.length - 10}</span>` : ''}</div>`;
      groupsEl.appendChild(card);
    });
    const n = groups.filter(g => g.on).length;
    hint.textContent = `${n}개 묶음 선택됨 · 소제목이 ${n}개 추가됩니다.`;
  }
  groupsEl.addEventListener('input', e => {
    const i = e.target.dataset.gi; if (i == null) return;
    if (e.target.classList.contains('po-title')) groups[i].title = e.target.value;
  });
  groupsEl.addEventListener('change', e => {
    if (e.target.classList.contains('po-on')) { groups[e.target.dataset.gi].on = e.target.checked; render(); }
  });
  groupsEl.addEventListener('click', e => {
    const b = e.target.closest('.po-merge'); if (!b) return;
    const i = +b.dataset.gi, prev = groups[i - 1], cur = groups[i];
    prev.photos = prev.photos.concat(cur.photos);
    const c = centroid(prev.photos); prev.lat = c ? c.lat : null; prev.lng = c ? c.lng : null;
    groups.splice(i, 1); render();
  });

  /* ── 섹션 만들기 ── */
  $('poCreate').addEventListener('click', () => {
    const chosen = groups.filter(g => g.on);
    if (!chosen.length) { alert('만들 묶음을 선택해 주세요.'); return; }
    // 비어 있는 기본 섹션 하나뿐이면 그 자리를 대체
    const secs = [...api.wrap.querySelectorAll('.section-editor')];
    if (secs.length === 1 && !secs[0].querySelector('.section-title').value.trim() && !secs[0].querySelector('.image-card')) { secs[0].remove(); }
    let lastEl = null;
    chosen.forEach(g => {
      const el = api.addSection(); lastEl = el;
      el.querySelector('.section-title').value = g.title || '';
      g.photos.forEach(p => api.addImageCard(el, p.path, '', true));
      api.syncImages(el);
      const set = (c, v) => { const n = el.querySelector(c); if (n) n.value = v == null ? '' : v; };
      if (g.lat != null) { set('.subsection-location', g.place || ''); set('.subsection-location-lat', g.lat.toFixed(6)); set('.subsection-location-lng', g.lng.toFixed(6)); }
      api.syncSubsection(el);
    });
    const cover = api.form.querySelector('input[name="cover"]');
    if (cover && !cover.value && chosen[0]) { cover.value = chosen[0].photos[0].path; }
    api.markCover();
    // 만든 묶음은 목록에서 제거하되, 사용하지 않은 묶음은 남김
    groups = groups.filter(g => !g.on);
    photos = groups.flatMap(g => g.photos);
    statusEl.textContent = `${chosen.length}개의 소제목을 만들었습니다. 아래 "여행 작성"에서 내용을 이어 작성하세요.`;
    render();
    if (lastEl) lastEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  $('poGap').addEventListener('change', () => { if (photos.length) { groups = []; regroup(); } });
  input.addEventListener('change', () => handleFiles([...input.files]));
  if (nasBtn) nasBtn.addEventListener('click', async () => {
    if (busy) return;
    if (!/^\d{4}-\d{2}-\d{2} [a-z]/.test(api.currentPhotoFolder())) { alert('먼저 위쪽의 여행날짜와 국가(영문)를 입력해 주세요. 사진 폴더 이름에 사용됩니다.'); return; }
    const items = await NaeggeologPhotos.pickFromNas();
    if (items.length) handleFiles(items);
  });
})();

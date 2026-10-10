/* Naeggeolog — 서비스 워커 (오프라인 · 빠른 재방문)
 * - 페이지(HTML): 네트워크 우선 → 실패하면 이전에 본 페이지 → 없으면 offline.html
 * - CSS/JS/아이콘·썸네일·사진: 캐시 우선(파일명에 버전/해시가 들어 있음) + 오래된 것 정리
 * - 관리자(/admin/)·내 공간(/hub/), POST, 다른 도메인(Google 지도 등)은 건드리지 않음
 */
const V = 'v1';
const PAGES = 'tv-pages-' + V, ASSETS = 'tv-assets-' + V, IMGS = 'tv-img-' + V;
const MAX_PAGES = 40, MAX_IMGS = 400;
const base = new URL('./', self.location).pathname;

self.addEventListener('install', e => {
  e.waitUntil(caches.open(PAGES).then(c => c.add(new Request('offline.html', { cache: 'reload' }))).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('tv-') && ![PAGES, ASSETS, IMGS].includes(k)).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

async function trim(name, max) {
  const c = await caches.open(name), keys = await c.keys();
  for (const k of keys.slice(0, Math.max(0, keys.length - max))) await c.delete(k);
}
const isImg = u => /\.(webp|jpe?g|png|gif|avif)$/i.test(u.pathname) || u.pathname.endsWith('/img.php');

self.addEventListener('fetch', e => {
  const req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== self.location.origin || req.headers.has('range')) return;
  if (url.pathname.startsWith(base + 'admin/') || url.pathname.startsWith(base + 'hub/') || url.pathname.endsWith('/sw.js')) return;

  if (req.mode === 'navigate') {
    e.respondWith((async () => {
      try {
        const res = await fetch(req);
        if (res.ok && !/no-store/i.test(res.headers.get('Cache-Control') || '')) { const c = await caches.open(PAGES); c.put(req, res.clone()).then(() => trim(PAGES, MAX_PAGES)); }
        return res;
      } catch (err) {
        return (await caches.match(req)) || (await caches.match('offline.html')) || Response.error();
      }
    })());
    return;
  }
  const cacheName = isImg(url) ? IMGS : (/\.(css|js|woff2?|webmanifest)$/i.test(url.pathname) ? ASSETS : null);
  if (!cacheName) return;
  e.respondWith((async () => {
    const c = await caches.open(cacheName), hit = await c.match(req);
    if (hit) return hit;
    try {
      const res = await fetch(req);
      if (res.ok) { c.put(req, res.clone()).then(() => cacheName === IMGS && trim(IMGS, MAX_IMGS)); }
      return res;
    } catch (err) { return Response.error(); }
  })());
});

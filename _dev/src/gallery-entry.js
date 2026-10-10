// 사진 갤러리 (PhotoSwipe v5) — 첫 사진 클릭 때 지연 로드됩니다.
// 기능: 스와이프 · 핀치/더블탭 확대 · 키보드(← → Esc) · 개수 표시 · 사진 설명 · 원본 열기 · 드래그 닫기
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import PhotoSwipe from 'photoswipe';

let lightbox = null;

/** links: 갤러리에 포함할 <a class="photo-link" data-pswp-width/height ...> 목록, index: 처음 열 사진 */
export function open(links, index) {
  const items = links.map(a => ({
    src: a.href,
    width: +a.dataset.pswpWidth,
    height: +a.dataset.pswpHeight,
    msrc: (a.querySelector('img') || {}).currentSrc || undefined,   // 확대 애니메이션 시작 이미지
    alt: (a.querySelector('img') || {}).alt || '',
    caption: a.dataset.caption || '',
    orig: a.dataset.orig || a.href,
    element: a,
  }));
  if (lightbox) lightbox.destroy();
  lightbox = new PhotoSwipeLightbox({
    dataSource: items,
    pswpModule: PhotoSwipe,
    bgOpacity: 0.94,
    wheelToZoom: true,
    preload: [1, 2],
    showHideAnimationType: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'none' : 'zoom',
    closeTitle: '닫기', zoomTitle: '확대/축소', arrowPrevTitle: '이전 사진', arrowNextTitle: '다음 사진', errorMsg: '사진을 불러오지 못했습니다.',
  });

  // 사진 설명
  lightbox.on('uiRegister', () => {
    lightbox.pswp.ui.registerElement({
      name: 'tv-caption', order: 9, isButton: false, appendTo: 'root',
      onInit: (el, pswp) => {
        el.className = 'pswp__tv-caption';
        pswp.on('change', () => {
          const c = pswp.currSlide && pswp.currSlide.data.caption;
          el.textContent = c || '';
          el.style.display = c ? '' : 'none';
        });
      },
    });
    // 원본 열기 버튼
    lightbox.pswp.ui.registerElement({
      name: 'tv-orig', order: 8, isButton: true, tagName: 'a', html: '원본',
      onInit: (el, pswp) => {
        el.setAttribute('target', '_blank'); el.setAttribute('rel', 'noopener'); el.setAttribute('title', '원본 사진 열기');
        el.className += ' pswp__tv-orig';
        pswp.on('change', () => { el.href = pswp.currSlide.data.orig; });
      },
    });
  });
  // 닫을 때 해당 사진 위치로 스크롤 맞춤(키보드로 넘긴 경우)
  lightbox.on('close', () => {
    const cur = lightbox.pswp && lightbox.pswp.currSlide && lightbox.pswp.currSlide.data.element;
    if (cur && cur.scrollIntoView) cur.scrollIntoView({ block: 'center', behavior: 'instant' });
  });
  lightbox.init();
  lightbox.loadAndOpen(index);
}

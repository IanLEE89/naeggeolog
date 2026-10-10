// Naeggeolog 본문 편집기 (TipTap/ProseMirror) — 관리자 전용 번들.
// 출력 HTML 은 includes/sanitize.php 허용 목록(p, strong, em, u, s, h3, h4, ul/ol/li, blockquote, a, hr, span[color])과 일치하도록 구성.
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Placeholder from '@tiptap/extension-placeholder';

/** 예전 contenteditable 편집기가 만든 HTML 을 새 스키마가 이해하는 형태로 변환 */
export function legacyToModern(html) {
  html = String(html || '').trim();
  if (!html) return '';
  const doc = new DOMParser().parseFromString('<div id="r">' + html + '</div>', 'text/html');
  const root = doc.getElementById('r');
  root.querySelectorAll('font').forEach(f => {
    const color = f.getAttribute('color');
    if (color) { const s = doc.createElement('span'); s.style.color = color; while (f.firstChild) s.appendChild(f.firstChild); f.replaceWith(s); }
    else { while (f.firstChild) f.parentNode.insertBefore(f.firstChild, f); f.remove(); }
  });
  const block = 'P,UL,OL,LI,BLOCKQUOTE,H1,H2,H3,H4,H5,H6,DIV,HR,TABLE';
  Array.from(root.querySelectorAll('div')).reverse().forEach(d => {
    const hasBlock = Array.from(d.children).some(c => block.split(',').includes(c.tagName));
    if (hasBlock) { while (d.firstChild) d.parentNode.insertBefore(d.firstChild, d); d.remove(); }
    else { const p = doc.createElement('p'); while (d.firstChild) p.appendChild(d.firstChild); d.replaceWith(p); }
  });
  return root.innerHTML;
}

const BTN = [
  { k: 'bold', t: '굵게', cls: 'font-semibold', run: e => e.chain().focus().toggleBold().run(), on: e => e.isActive('bold') },
  { k: 'italic', t: '기울임', cls: 'italic', run: e => e.chain().focus().toggleItalic().run(), on: e => e.isActive('italic') },
  { k: 'underline', t: '밑줄', cls: 'underline', run: e => e.chain().focus().toggleUnderline().run(), on: e => e.isActive('underline') },
  { k: 'strike', t: '취소선', cls: 'line-through', run: e => e.chain().focus().toggleStrike().run(), on: e => e.isActive('strike') },
  'color',
  '|',
  { k: 'h3', t: '제목', run: e => e.chain().focus().toggleHeading({ level: 3 }).run(), on: e => e.isActive('heading', { level: 3 }) },
  { k: 'h4', t: '소제목', run: e => e.chain().focus().toggleHeading({ level: 4 }).run(), on: e => e.isActive('heading', { level: 4 }) },
  '|',
  { k: 'ul', t: '• 목록', run: e => e.chain().focus().toggleBulletList().run(), on: e => e.isActive('bulletList') },
  { k: 'ol', t: '1. 목록', run: e => e.chain().focus().toggleOrderedList().run(), on: e => e.isActive('orderedList') },
  { k: 'quote', t: '인용', run: e => e.chain().focus().toggleBlockquote().run(), on: e => e.isActive('blockquote') },
  { k: 'hr', t: '구분선', run: e => e.chain().focus().setHorizontalRule().run() },
  '|',
  { k: 'link', t: '링크', run: e => linkPrompt(e), on: e => e.isActive('link') },
  '|',
  { k: 'undo', t: '↶', title: '되돌리기', run: e => e.chain().focus().undo().run(), can: e => e.can().undo() },
  { k: 'redo', t: '↷', title: '다시 실행', run: e => e.chain().focus().redo().run(), can: e => e.can().redo() },
];

function linkPrompt(editor) {
  const prev = editor.getAttributes('link').href || '';
  const url = window.prompt('링크 주소를 입력하세요. (비우면 링크 해제)\n예: https://www.google.com', prev);
  if (url === null) return;
  let href = url.trim();
  if (!href) { editor.chain().focus().extendMarkRange('link').unsetLink().run(); return; }
  if (!/^(https?:\/\/|mailto:|tel:|#)/i.test(href)) href = 'https://' + href;
  editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
}

/**
 * host 안에 툴바 + 편집 영역을 만든다.
 * opts: { html, placeholder, onUpdate(html) }
 * 반환: { editor, getHTML(), setHTML(html), destroy() }
 */
export function mount(host, opts = {}) {
  host.classList.add('tt-wrap');
  host.innerHTML = '';
  const bar = document.createElement('div');
  bar.className = 'tt-toolbar';
  bar.setAttribute('role', 'toolbar');
  bar.setAttribute('aria-label', '본문 서식');
  const area = document.createElement('div');
  area.className = 'tt-area';
  host.append(bar, area);

  const editor = new Editor({
    element: area,
    content: legacyToModern(opts.html || ''),
    extensions: [
      StarterKit.configure({ heading: { levels: [3, 4] }, code: false, codeBlock: false }),
      Underline,
      Link.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } }),
      TextStyle,
      Color,
      Placeholder.configure({ placeholder: opts.placeholder || '여행 이야기를 적어보세요…' }),
    ],
    editorProps: { attributes: { class: 'story tt-content', 'aria-label': '본문' } },
    onUpdate: () => { opts.onUpdate && opts.onUpdate(getHTML()); },
    onTransaction: () => refresh(),
  });

  const buttons = [];
  BTN.forEach(b => {
    if (b === '|') { const s = document.createElement('span'); s.className = 'toolbar-divider'; bar.appendChild(s); return; }
    if (b === 'color') {
      const color = document.createElement('input');
      color.type = 'color'; color.className = 'toolbar-color'; color.value = '#292524'; color.title = '글자 색상'; color.setAttribute('aria-label', '글자 색상');
      color.addEventListener('input', () => editor.chain().focus().setColor(color.value).run());
      const clear = document.createElement('button');
      clear.type = 'button'; clear.className = 'toolbar-btn'; clear.textContent = '색 해제';
      clear.addEventListener('mousedown', ev => ev.preventDefault());
      clear.addEventListener('click', () => editor.chain().focus().unsetColor().run());
      bar.append(color, clear);
      return;
    }
    const el = document.createElement('button');
    el.type = 'button'; el.className = 'toolbar-btn ' + (b.cls || ''); el.textContent = b.t;
    if (b.title) { el.title = b.title; el.setAttribute('aria-label', b.title); }
    el.addEventListener('mousedown', ev => ev.preventDefault());    // 선택 영역 유지
    el.addEventListener('click', () => b.run(editor));
    bar.appendChild(el); buttons.push([el, b]);
  });

  function refresh() {
    buttons.forEach(([el, b]) => {
      if (b.on) el.classList.toggle('is-active', !!b.on(editor));
      if (b.can) el.disabled = !b.can(editor);
    });
  }
  function getHTML() { return editor.isEmpty ? '' : editor.getHTML(); }
  refresh();
  return {
    editor, getHTML,
    setHTML(html) { editor.commands.setContent(legacyToModern(html), false); },
    destroy() { editor.destroy(); host.innerHTML = ''; },
  };
}

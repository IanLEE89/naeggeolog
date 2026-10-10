// JS 번들 빌드:  npm run build:js
//  - 관리자 본문 편집기(TipTap)          → ../admin/vendor/naeggeolog-editor.js   (전역 NaeggeologEditor)
//  - 사진 갤러리(PhotoSwipe, 지연 로드)  → ../assets/gallery.js + gallery.css
//  - 지도 핀 클러스터링                  → ../assets/vendor/markerclusterer.js (전역 markerClusterer)
import { build } from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '..');
const common = { bundle: true, minify: true, target: ['es2020'], legalComments: 'none', logLevel: 'info' };
fs.mkdirSync(path.join(root, 'admin/vendor'), { recursive: true });
fs.mkdirSync(path.join(root, 'assets/vendor'), { recursive: true });

await build({ ...common, entryPoints: ['src/editor-entry.js'], format: 'iife', globalName: 'NaeggeologEditor', outfile: path.join(root, 'admin/vendor/naeggeolog-editor.js') });
await build({ ...common, entryPoints: ['src/gallery-entry.js'], format: 'esm', outfile: path.join(root, 'assets/gallery.js') });
await build({ ...common, entryPoints: ['src/clusterer-entry.js'], format: 'iife', globalName: 'markerClusterer', outfile: path.join(root, 'assets/vendor/markerclusterer.js') });
// PhotoSwipe CSS + 사이트 전용 보정
const css = fs.readFileSync('node_modules/photoswipe/dist/photoswipe.css', 'utf8') + '\n' + fs.readFileSync('src/gallery.css', 'utf8');
fs.writeFileSync(path.join(root, 'assets/gallery.css'), css);
await build({ entryPoints: [path.join(root, 'assets/gallery.css')], bundle: false, minify: true, allowOverwrite: true, outfile: path.join(root, 'assets/gallery.css'), logLevel: 'info' });

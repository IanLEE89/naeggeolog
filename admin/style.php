<?php
if (!function_exists('admin_head')) {
function admin_head($title){
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?> · 관리자</title>
<link rel="icon" href="../favicon.ico" sizes="48x48">
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
<link rel="stylesheet" href="../assets/app.css?v=<?=@filemtime(__DIR__.'/../assets/app.css')?>">
<style>
  :root{color-scheme:light}
  html{scroll-behavior:smooth}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans KR",sans-serif}
  .admin-serif{font-family:Georgia,"Noto Serif KR",serif}
  .admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}
  .field-label{display:block;font-size:12px;font-weight:600;letter-spacing:-.01em;color:#57534e}
  .field-help{margin-top:5px;font-size:11px;line-height:1.55;color:#a8a29e}
  .admin-input,.admin-select,.admin-textarea{display:block;width:100%;border:1px solid #d6d3d1;background:#fff;color:#292524;outline:0;transition:border-color .18s,box-shadow .18s,background .18s}
  .admin-input,.admin-select{height:46px;padding:0 13px;font-size:13px;border-radius:7px}
  .admin-textarea{padding:12px 13px;font-size:13px;line-height:1.75;border-radius:7px;resize:vertical}
  .admin-input::placeholder,.admin-textarea::placeholder{color:#b5b0aa}
  .admin-input:focus,.admin-select:focus,.admin-textarea:focus{border-color:#78716c;box-shadow:0 0 0 3px rgba(120,113,108,.09)}
  .section-card{border:1px solid #e7e5e4;background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(28,25,23,.025)}
  .section-card:hover{border-color:#d6d3d1}
  .toolbar-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:32px;border:1px solid #e7e5e4;background:#fff;border-radius:6px;padding:0 9px;font-size:11px;color:#57534e;transition:.15s}
  .toolbar-btn:hover{background:#f5f5f4;border-color:#d6d3d1;color:#1c1917}
  .rich-editor{min-height:250px;padding:16px 17px;background:#fff;color:#292524;font-size:14px;line-height:1.95;outline:0}
  .rich-editor:focus{box-shadow:inset 0 0 0 2px rgba(120,113,108,.12)}
  .rich-editor:empty:before{content:"여행 내용을 입력하세요…";color:#b5b0aa}
  .rich-editor p{margin:1em 0}.rich-editor p:first-child{margin-top:0}.rich-editor p:last-child{margin-bottom:0}
  .rich-editor ul{list-style:disc;padding-left:1.5em}.rich-editor ol{list-style:decimal;padding-left:1.5em}.rich-editor li{margin:.35em 0}.rich-editor a{text-decoration:underline;text-underline-offset:3px}
  .image-card{display:grid;grid-template-columns:116px minmax(0,1fr) auto;gap:12px;align-items:center;border:1px solid #e7e5e4;background:#fafaf9;border-radius:9px;padding:9px;transition:.15s}
  .image-card:hover{background:#fff;border-color:#d6d3d1}
  .image-card img{display:block;width:116px;height:82px;object-fit:cover;border-radius:6px;background:#e7e5e4}
  .image-card .caption{width:100%;height:40px;border:1px solid #d6d3d1;background:#fff;border-radius:6px;padding:0 10px;font-size:12px;outline:0}
  .image-card .caption:focus{border-color:#78716c;box-shadow:0 0 0 3px rgba(120,113,108,.08)}
  .remove-image{padding:7px 8px;white-space:nowrap}
  .drop-zone{border:1px dashed #d6d3d1;background:#fafaf9;border-radius:8px}
  .weather-option,.transport-option{display:inline-flex;cursor:pointer}
  .weather-option input,.transport-option input{position:absolute;opacity:0;pointer-events:none}
  .weather-option span{min-width:70px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;padding:9px 11px;border:1px solid #e7e5e4;border-radius:9px;background:#fff;transition:.15s}
  .weather-option b{font-size:22px;line-height:1}.weather-option small{font-size:10px;color:#78716c}
  .weather-option:hover span,.transport-option:hover span{border-color:#a8a29e;background:#fafaf9}
  .weather-option input:checked + span{border-color:#57534e;background:#f5f5f4;box-shadow:0 0 0 2px rgba(87,83,78,.08)}
  .transport-option span{padding:8px 13px;border:1px solid #e7e5e4;border-radius:999px;background:#fff;font-size:11px;color:#57534e;transition:.15s}
  .transport-option input:checked + span{border-color:#57534e;background:#292524;color:#fff}
  .basic-info-panel,.subsection-info-panel{box-shadow:inset 0 1px 0 rgba(255,255,255,.65)}
  .rating-picker{display:flex;align-items:center;gap:2px;min-height:46px}
  .rating-star{border:0;background:transparent;color:#d6d3d1;font-size:27px;line-height:1;padding:4px 3px;cursor:pointer;transition:transform .12s,color .12s}
  .rating-star:hover,.rating-star.is-selected{color:#44403c}
  .rating-star:hover{transform:translateY(-1px)}
  .rating-label{margin-left:8px;font-size:11px;color:#78716c;min-width:58px}
  .feature-input-wrap{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}
  .feature-add{height:46px;padding:0 14px;border:1px solid #d6d3d1;border-radius:7px;background:#fff;font-size:12px;color:#57534e;cursor:pointer;transition:.15s}
  .feature-add:hover{border-color:#a8a29e;background:#f5f5f4;color:#1c1917}
  .feature-tags{display:flex;flex-wrap:wrap;gap:7px;min-height:2px}
  .feature-tag{display:inline-flex;align-items:center;gap:5px;padding:6px 9px;border:1px solid #d6d3d1;border-radius:999px;background:#fff;font-size:11px;color:#57534e}
  .feature-remove{border:0;background:transparent;color:#a8a29e;font-size:15px;line-height:1;padding:0 1px;cursor:pointer}
  .feature-remove:hover{color:#44403c}
  .subsection-info-panel.hidden,.basic-info-panel.hidden,.generic-section-fields.hidden,.section-media-fields.hidden{display:none}

  .step-badge{display:flex;align-items:center;justify-content:center;width:32px;height:32px;flex:0 0 32px;border-radius:999px;background:#f5f5f4;color:#78716c;font-size:11px;font-weight:700}
  .step-badge.dark{background:#292524;color:#fff}
  .mini-badge{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:999px;background:#292524;color:#fff;font-size:11px;font-weight:700}
  .section-heading{font-size:17px;font-weight:650;letter-spacing:-.025em;color:#1c1917}
  .section-description{margin-top:4px;font-size:12px;line-height:1.6;color:#a8a29e}
  .location-search-btn,.add-section-btn,.photo-select-btn{display:inline-flex;align-items:center;justify-content:center;height:46px;padding:0 16px;border:1px solid #d6d3d1;border-radius:8px;background:#fff;color:#57534e;font-size:12px;font-weight:600;cursor:pointer;transition:.15s;white-space:nowrap}
  .location-search-btn:hover,.add-section-btn:hover,.photo-select-btn:hover{border-color:#a8a29e;background:#fafaf9;color:#1c1917}
  .admin-map{height:280px;overflow:hidden;border:1px solid #e7e5e4;border-radius:10px;background:#e7e5e4}
  .location-results{overflow:hidden;border:1px solid #e7e5e4;border-radius:9px;background:#fff;box-shadow:0 8px 24px rgba(28,25,23,.06)}
  .location-result{display:block;width:100%;padding:11px 13px;text-align:left;border:0;border-bottom:1px solid #f0efed;background:#fff;cursor:pointer}.location-result:last-child{border-bottom:0}.location-result:hover{background:#faf9f6}.location-result b{display:block;font-size:12px;color:#292524}.location-result small{display:block;margin-top:3px;font-size:10px;line-height:1.5;color:#a8a29e}.location-loading,.location-empty{padding:12px;font-size:11px;color:#a8a29e}
  .google-autocomplete{min-height:46px;flex:1}.google-autocomplete gmp-place-autocomplete,.google-place-autocomplete gmp-place-autocomplete{display:block;width:100%}.google-autocomplete gmp-place-autocomplete{--gmp-mat-color-surface:#fff}.google-place-picker{position:relative}.google-place-autocomplete{min-height:46px;flex:1}.google-place-autocomplete gmp-place-autocomplete{width:100%}.google-search-row{display:flex;align-items:stretch;gap:8px}.google-search-btn{flex:0 0 auto;min-width:82px;border:1px solid #d6d3d1;border-radius:10px;background:#292524;color:#fff;padding:0 14px;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s}.google-search-btn:hover{background:#44403c}.google-search-btn:disabled{opacity:.55;cursor:wait}.google-search-results{margin-top:7px;overflow:hidden;border:1px solid #e7e5e4;border-radius:10px;background:#fff;box-shadow:0 8px 24px rgba(28,25,23,.08)}.google-search-results.hidden{display:none}.route-number{background:#292524!important;border:0!important;border-radius:999px!important;color:#fff!important;font-size:10px!important;font-weight:700!important;padding:3px 6px!important;box-shadow:0 1px 4px rgba(0,0,0,.25)}
  .toolbar-select{height:32px;border:1px solid #e7e5e4;border-radius:6px;background:#fff;padding:0 7px;font-size:11px;color:#57534e;outline:0}.toolbar-color{width:34px;height:32px;padding:4px;border:1px solid #e7e5e4;border-radius:6px;background:#fff;cursor:pointer}.toolbar-divider{width:1px;height:20px;margin:0 3px;background:#e7e5e4}
  .expense-list{display:grid;gap:8px}.expense-row{display:grid;grid-template-columns:minmax(140px,1.4fr) 112px 140px 130px minmax(110px,.8fr) auto;gap:8px;align-items:center;padding:9px;border:1px solid #e7e5e4;border-radius:8px;background:#fafaf9}.expense-row .admin-input,.expense-row .admin-select{height:40px}.expense-krw{font-size:11px;color:#78716c;text-align:right;white-space:nowrap}.expense-remove{height:40px;padding:0 10px;border:1px solid #e7e5e4;border-radius:7px;background:#fff;color:#a8a29e;font-size:11px;cursor:pointer}.expense-remove:hover{color:#991b1b;border-color:#fecaca;background:#fff5f5}.expense-summary{border:1px solid #e7e5e4;border-radius:9px;background:#faf9f6;padding:13px 14px}.expense-total-title{font-size:11px;font-weight:700;color:#57534e}.expense-total-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}.expense-total-grid span{display:inline-flex;gap:6px;padding:6px 9px;border:1px solid #e7e5e4;border-radius:999px;background:#fff;font-size:10px;color:#78716c}.expense-total-grid b{color:#292524}.expense-total-grid .expense-total-krw{font-weight:600;border-color:#d6d3d1}
  .image-path{margin-top:4px;font-size:9px;color:#a8a29e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.image-card.dragging{opacity:.45;border-color:#a8a29e}.image-card{cursor:grab}.image-card:active{cursor:grabbing}
  .action-bar{box-shadow:0 -8px 25px rgba(28,25,23,.045)}
  @media(max-width:700px){
    .expense-row{grid-template-columns:1fr 1fr}.expense-row .expense-item{grid-column:1 / -1}.expense-krw{text-align:left}.expense-remove{justify-self:start}
    .admin-shell{width:min(100% - 24px,1120px)}
    .image-card{grid-template-columns:84px minmax(0,1fr);}
    .image-card img{width:84px;height:70px}
    .image-card .remove-image{grid-column:2;justify-self:start;margin-top:-4px}
  }
  @media(max-width:480px){.image-card{grid-template-columns:1fr}.image-card img{width:100%;height:auto;aspect-ratio:16/9}.image-card .remove-image{grid-column:auto}}

/* ── TipTap 본문 편집기 ── */
.tt-wrap{overflow:hidden;border:1px solid #d6d3d1;border-radius:8px;background:#fff}
.tt-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:4px;padding:8px;border-bottom:1px solid #e7e5e4;background:#fafaf9;position:sticky;top:0;z-index:5}
.tt-toolbar .toolbar-btn.is-active{background:#292524;border-color:#292524;color:#fff}
.tt-toolbar .toolbar-btn:disabled{opacity:.35;cursor:default}
.tt-area .tt-content{min-height:250px;padding:16px 18px;background:#fff;color:#292524;outline:0}
.tt-area .tt-content:focus{box-shadow:inset 0 0 0 2px rgba(120,113,108,.12)}
.tt-area .tt-content p.is-editor-empty:first-child::before{content:attr(data-placeholder);float:left;height:0;color:#b5b0aa;pointer-events:none}
.tt-area .tt-content > :first-child{margin-top:0}
.tt-area .tt-content hr{margin:1.6em 0;border:0;border-top:1px solid #d6d3d1}
.story li > p{margin:0}
/* 대표 사진 / 이미지 카드 버튼 */
.image-card .image-actions{display:flex;gap:6px;grid-column:auto;justify-self:start;margin:0}
.image-card .set-cover,.image-card .remove-image{grid-column:auto;margin:0;height:28px;padding:0 10px}
.image-card.is-cover{border-color:#292524;box-shadow:0 0 0 2px rgba(41,37,36,.18)}
.image-card.is-cover .set-cover{background:#292524;border-color:#292524;color:#fff}
.image-card.is-cover .set-cover::after{content:" ✓"}

/* ═══ 섹션 도구(순서 이동·삭제·아래에 추가·접기) ═══ */
.section-editor{scroll-margin-top:84px}
.section-head{display:flex;align-items:center;gap:10px;padding:10px 14px 10px 8px;border-bottom:1px solid #e7e5e4;background:#faf9f6;border-radius:12px 12px 0 0;min-height:56px}
.section-handle{display:inline-flex;align-items:center;justify-content:center;flex:none;width:30px;height:36px;border-radius:8px;color:#a8a29e;cursor:grab;user-select:none;touch-action:none;transition:background-color .15s,color .15s}
.section-handle:hover,.section-handle:focus-visible{background:#ece9e4;color:#44403c;outline:0}
.section-handle:active{cursor:grabbing}
.section-head .section-number{flex:none;font-size:10px;font-weight:700;letter-spacing:.2em;color:#a8a29e;white-space:nowrap}
.section-title-preview{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13.5px;font-weight:600;color:#292524}
.section-title-preview.is-empty{font-weight:400;color:#b5b0aa}
.section-tools{display:flex;flex:none;align-items:center;gap:2px;margin-left:auto}
.section-icon-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:34px;height:34px;padding:0 8px;border:0;border-radius:8px;background:transparent;color:#78716c;font-size:12px;cursor:pointer;transition:background-color .15s,color .15s}
.section-icon-btn:hover{background:#ece9e4;color:#1c1917}
.section-icon-btn:focus-visible{outline:2px solid #57534e;outline-offset:1px}
.section-icon-btn.section-del{margin-left:6px;padding:0 11px 0 9px;color:#a8a29e}
.section-icon-btn.section-del:hover{background:#fef2f2;color:#b91c1c}
.section-icon-btn.section-toggle{margin-left:6px;padding-left:10px;padding-right:10px;border-left:1px solid #e7e5e4;border-radius:0 8px 8px 0}
.section-toggle svg{transition:transform .2s}
.is-collapsed .section-toggle svg{transform:rotate(-90deg)}
.section-add-bar{display:flex;width:100%;align-items:center;justify-content:center;gap:8px;padding:15px 16px;border:0;border-top:1px dashed #d6d3d1;border-radius:0 0 12px 12px;background:#fafaf9;color:#78716c;font-size:12.5px;font-weight:600;cursor:pointer;transition:background-color .15s,color .15s}
.section-add-bar:hover,.section-add-bar:focus-visible{background:#f1efea;color:#1c1917;outline:0}
.section-add-plus{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#e7e5e4;color:#44403c;font-size:15px;line-height:1;font-weight:500;transition:background-color .15s,color .15s}
.section-add-bar:hover .section-add-plus{background:#292524;color:#fff}
.section-editor.is-collapsed .section-body{display:none}
.section-editor.is-collapsed .section-head{border-bottom:0;border-radius:12px 12px 0 0}
.is-reordering .section-body,.is-reordering .section-add-bar{display:none}
.is-reordering .section-head{border-bottom:0;border-radius:12px}
.is-reordering .section-editor{margin-top:0}
.is-reordering{display:flex;flex-direction:column;gap:10px}
.is-reordering > .section-editor{margin:0!important}
.section-editor.is-dragging-section{opacity:.45;outline:2px dashed #78716c;outline-offset:2px}
.section-editor.section-flash{animation:sec-flash 1.4s ease-out}
@keyframes sec-flash{0%{box-shadow:0 0 0 4px rgba(87,83,78,.35)}100%{box-shadow:0 2px 10px rgba(28,25,23,.025)}}
#sections:empty::before{content:"소제목이 없습니다. 위의 '+ 소제목 추가'를 눌러 시작하세요.";display:block;padding:40px 20px;border:1px dashed #d6d3d1;border-radius:12px;text-align:center;font-size:13px;color:#a8a29e}
@media(max-width:560px){.section-icon-btn.section-del span{display:none}.section-icon-btn.section-del{padding:0 8px;margin-left:2px}.section-title-preview{font-size:13px}.section-head{gap:6px;padding:8px 8px 8px 10px}.section-word{display:none}.section-head .section-number{letter-spacing:.08em;font-size:11px;color:#78716c}.section-icon-btn{min-width:32px;padding:0 6px}.section-icon-btn.section-toggle{margin-left:2px;padding:0 8px}}
@media(hover:none){.section-handle{display:none}.section-head{padding-left:14px}}
@media(prefers-reduced-motion:reduce){.section-editor.section-flash{animation:none}}
/* 안내 메시지(토스트) */
.admin-toast{position:fixed;left:50%;bottom:24px;z-index:9999;display:flex;align-items:center;gap:14px;max-width:calc(100vw - 32px);padding:12px 14px 12px 18px;border-radius:12px;background:#292524;color:#fff;font-size:13px;box-shadow:0 14px 34px -10px rgba(0,0,0,.45);opacity:0;pointer-events:none;transform:translate(-50%,12px);transition:opacity .2s,transform .2s}
.admin-toast.is-on{opacity:1;pointer-events:auto;transform:translate(-50%,0)}
.admin-toast button{border:0;border-radius:8px;background:rgba(255,255,255,.14);color:#fff;font-size:12px;font-weight:600;padding:7px 12px;cursor:pointer}
.admin-toast button:hover{background:rgba(255,255,255,.26)}
/* 날씨 선택: 5칸 균등 격자 + 이모지 고정 상자(글꼴별 폭 차이로 어긋나던 문제 해결) */
.weather-picker{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;max-width:420px}
.weather-option{display:block;cursor:pointer}
.weather-option span{min-width:0;width:100%;gap:7px;padding:11px 4px 9px}
.weather-option b{display:grid;place-items:center;width:36px;height:30px;font-size:24px;line-height:1;font-weight:400;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif}
.weather-option small{font-size:11px;line-height:1.2;color:#57534e}
.transport-picker{gap:8px!important}
.expense-add{display:inline-flex;align-items:center;gap:6px;height:42px;padding:0 16px;border:1px solid #d6d3d1;border-radius:8px;background:#fff;color:#57534e;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;flex:none;transition:.15s}
.expense-add:hover{border-color:#a8a29e;background:#fafaf9;color:#1c1917}
.mini-badge{flex:none}

/* ═══ 관리자 레이아웃(사이드바) · 목록 ═══ */
.adm-wrap>*{min-width:0}
.adm-wrap{display:grid;grid-template-columns:230px minmax(0,1fr);gap:28px;align-items:start}
.adm-side{position:sticky;top:88px;display:grid;gap:18px}
.adm-nav-group h3{margin:0 0 6px;padding:0 12px;font-size:10px;font-weight:700;letter-spacing:.18em;color:#a8a29e}
.adm-nav{display:grid;gap:2px}
.adm-nav a,.adm-nav button{display:flex;align-items:center;gap:10px;width:100%;padding:9px 12px;border:0;border-radius:9px;background:transparent;color:#57534e;font:inherit;font-size:13px;text-align:left;text-decoration:none;cursor:pointer;transition:background-color .15s,color .15s}
.adm-nav a:hover,.adm-nav button:hover{background:#ece9e4;color:#1c1917}
.adm-nav a[aria-current="page"]{background:#fff;color:#1c1917;font-weight:600;box-shadow:0 1px 0 #e7e5e4,0 0 0 1px #e7e5e4}
.adm-nav svg{flex:none;width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;opacity:.8}
.adm-primary{display:inline-flex;align-items:center;gap:8px;height:46px;padding:0 20px;border-radius:12px;background:#1c1917;color:#fff;font-size:13.5px;font-weight:600;text-decoration:none;box-shadow:0 6px 18px rgba(28,25,23,.18);transition:transform .15s,box-shadow .15s,background-color .15s}
.adm-primary:hover{background:#44403c;transform:translateY(-1px);box-shadow:0 10px 24px rgba(28,25,23,.22)}
.adm-primary:disabled{opacity:.4;cursor:default;transform:none;box-shadow:none}
.adm-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:22px}
.adm-search{position:relative;flex:1 1 240px;max-width:380px}
.adm-search input{width:100%;height:42px;padding:0 14px 0 38px;border:1px solid #d6d3d1;border-radius:10px;background:#fff;font-size:13px;outline:0}
.adm-search input:focus{border-color:#78716c;box-shadow:0 0 0 3px rgba(120,113,108,.09)}
.adm-search svg{position:absolute;left:12px;top:12px;width:16px;height:16px;stroke:#a8a29e;fill:none;stroke-width:2;stroke-linecap:round}
.adm-tabs{display:inline-flex;gap:2px;padding:3px;border-radius:10px;background:#ece9e4}
.adm-tabs a{padding:7px 12px;border-radius:8px;color:#78716c;font-size:12px;text-decoration:none}
.adm-tabs a[aria-current="true"]{background:#fff;color:#1c1917;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,.08)}
.adm-tabs small{margin-left:4px;color:#a8a29e}
.adm-table{width:100%;margin-top:14px;border:1px solid #e7e5e4;border-radius:14px;background:#fff;border-collapse:separate;border-spacing:0;overflow:hidden}
.adm-table th{padding:11px 14px;background:#faf9f6;border-bottom:1px solid #e7e5e4;font-size:11px;font-weight:600;letter-spacing:.04em;color:#78716c;text-align:left;white-space:nowrap}
.adm-table th a{color:inherit;text-decoration:none}.adm-table th a:hover{color:#1c1917}
.adm-table td{padding:12px 14px;border-bottom:1px solid #f0efed;font-size:13px;color:#44403c;vertical-align:middle}
.adm-table tr:last-child td{border-bottom:0}
.adm-table tbody tr:hover td{background:#fcfbf9}
.adm-table tr.is-hidden td{opacity:.62}
.adm-title{display:block;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;color:#1c1917}
.adm-badge{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:600;vertical-align:1px}
.adm-actions{display:flex;gap:6px;justify-content:flex-end;white-space:nowrap}
.adm-actions form{display:inline;margin:0}
.adm-btn{display:inline-flex;align-items:center;justify-content:center;height:30px;padding:0 11px;border:1px solid #d6d3d1;border-radius:8px;background:#fff;color:#57534e;font:inherit;font-size:12px;text-decoration:none;cursor:pointer;transition:.15s}
.adm-btn:hover{border-color:#a8a29e;background:#fafaf9;color:#1c1917}
.adm-btn.danger{border-color:#fecaca;color:#dc2626}.adm-btn.danger:hover{background:#fef2f2;border-color:#fca5a5}
.adm-pager{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;font-size:12px;color:#78716c}
.adm-pager nav{display:flex;gap:4px}
.adm-pager a,.adm-pager span.cur{min-width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;padding:0 9px;border:1px solid #e7e5e4;border-radius:8px;background:#fff;color:#57534e;text-decoration:none}
.adm-pager span.cur{background:#1c1917;border-color:#1c1917;color:#fff}
.adm-pager a:hover{border-color:#a8a29e}
.adm-note{margin-top:16px;padding:11px 14px;border:1px solid #e7e5e4;border-radius:10px;background:#fff;font-size:12px;color:#57534e}
.image-card.is-uploading .image-path{color:#d97706}.image-card.is-uploading .set-cover{pointer-events:none;opacity:.4}.image-card.is-uploading img{opacity:.7}.image-card.is-failed .image-path{color:#dc2626}.image-card.is-failed .set-cover{pointer-events:none;opacity:.4}
.adm-bulk{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:14px;padding:9px 12px;border:1px solid #e7e5e4;border-radius:12px;background:#fff;font-size:12px;color:#57534e}
.adm-bulk[hidden]{display:none}.adm-bulk b{margin-right:4px;color:#1c1917}.adm-bulk-hint{margin-left:auto;font-size:11px;color:#a8a29e}
.adm-h2{margin:34px 0 0;font-size:15px;font-weight:700;color:#1c1917}.adm-h2 small{margin-left:6px;font-size:12px;font-weight:400;color:#a8a29e}
.adm-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:18px}
.adm-kpis>div{padding:14px 16px;border:1px solid #e7e5e4;border-radius:12px;background:#fff}
.adm-kpis small{display:block;font-size:11px;color:#78716c}.adm-kpis b{display:block;margin-top:4px;font-family:Georgia,"Noto Serif KR",serif;font-size:24px;font-weight:500;color:#1c1917}.adm-kpis span{font-size:11px;color:#a8a29e}
.adm-photos{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-top:12px}
.adm-ph{position:relative;display:block;border:1px solid #e7e5e4;border-radius:10px;background:#fff;overflow:hidden;cursor:pointer;font-size:11px;color:#57534e}
.adm-ph img{display:block;width:100%;aspect-ratio:1;object-fit:cover;background:#ece9e4}
.adm-ph span{display:block;padding:6px 8px;line-height:1.35;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.adm-ph small{color:#a8a29e}
.adm-ph input{position:absolute;top:8px;left:8px;width:18px;height:18px;accent-color:#1c1917}
.adm-ph:has(input:checked){border-color:#1c1917;box-shadow:0 0 0 2px #1c1917}
.adm-ok{color:#15803d}.adm-warn{color:#b45309}.adm-bad{color:#b91c1c}
.adm-dot{display:inline-block;width:9px;height:9px;margin-right:7px;border-radius:50%;background:#a8a29e;vertical-align:middle}.adm-dot.ok{background:#16a34a}.adm-dot.warn{background:#f59e0b}.adm-dot.bad{background:#dc2626}
.adm-chart{margin-top:14px;padding:16px;border:1px solid #e7e5e4;border-radius:14px;background:#fff}
.adm-bar{height:8px;border-radius:99px;background:#ece9e4;overflow:hidden}.adm-bar i{display:block;height:100%;background:#1c1917}
.hide-toggle{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 13px;border:1px solid #d6d3d1;border-radius:8px;background:#fff;font-size:12px;color:#57534e;cursor:pointer;user-select:none}
.hide-toggle input{accent-color:#92400e}
.hide-toggle:has(input:checked){border-color:#f59e0b;background:#fffbeb;color:#92400e}
.up-box{display:flex;gap:16px;align-items:center;margin-top:8px}
.up-prev{flex:none;width:132px;height:84px;border:1px dashed #d6d3d1;border-radius:10px;background:#fafaf9 center/cover no-repeat;display:flex;align-items:center;justify-content:center;font-size:11px;color:#a8a29e}
.up-prev.round{width:84px;border-radius:50%}
.up-btns{display:grid;gap:6px;justify-items:start}
.up-state{font-size:11px;color:#78716c}.up-state.err{color:#dc2626}
@media(max-width:900px){
  .adm-wrap{grid-template-columns:1fr;gap:14px}
  .adm-wrap>*{min-width:0}
  .adm-side{position:static;display:flex;gap:6px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none}
  .adm-nav-group,.adm-nav{display:contents}
  .adm-nav-group h3{display:none}
  .adm-nav form{display:contents}
  .adm-nav a,.adm-nav button{width:auto;white-space:nowrap;border:1px solid #e7e5e4;background:#fff;padding:8px 12px}
  .adm-table thead{display:none}
  .adm-table,.adm-table tbody,.adm-table tr,.adm-table td{display:block;width:100%}
  .adm-table tr{padding:12px 14px;border-bottom:1px solid #f0efed}
  .adm-table td{padding:2px 0;border:0}
  .adm-title{max-width:none;white-space:normal}
  .adm-actions{justify-content:flex-start;margin-top:8px}
}

/* ═══ 공개 상태 · 태그 · 자동저장 · 이력 ═══ */
.state-box{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.state-sel,.state-dt{height:38px;border:1px solid #d6d3d1;border-radius:8px;background:#fff;padding:0 10px;font-size:12px;color:#44403c;outline:0}
.state-sel:focus,.state-dt:focus{border-color:#78716c}
.pin-toggle{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 11px;border:1px solid #d6d3d1;border-radius:8px;background:#fff;font-size:12px;color:#57534e;cursor:pointer;user-select:none}
.pin-toggle:has(input:checked){border-color:#a8a29e;background:#f5f5f4;color:#1c1917;font-weight:600}
.pin-toggle input{accent-color:#44403c}
.autosave-state{font-size:11px;color:#a8a29e;min-width:92px}
.autosave-state[data-s="ok"]{color:#16a34a}.autosave-state[data-s="err"]{color:#dc2626}
.action-bar .admin-shell{flex-wrap:wrap}
.edit-note{margin-top:20px;padding:11px 14px;border:1px solid #e7e5e4;border-radius:10px;background:#fff;font-size:12.5px;color:#57534e;line-height:1.7}
.edit-note.is-info{border-color:#bfdbfe;background:#eff6ff;color:#1e40af}
.edit-note.is-warn{border-color:#fde68a;background:#fffbeb;color:#92400e}
.edit-note a{text-decoration:underline;font-weight:600}
.edit-note form.inline{display:inline}.edit-note button{background:none;border:0;padding:0;font:inherit;text-decoration:underline;cursor:pointer;color:inherit}
.rev-menu{position:relative}
.rev-menu summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border:1px solid #d6d3d1;border-radius:999px;background:#fff;font-size:12px;color:#57534e}
.rev-menu summary::-webkit-details-marker{display:none}
.rev-menu summary small{color:#a8a29e}
.rev-pop{position:absolute;right:0;top:calc(100% + 8px);z-index:50;width:260px;max-height:340px;overflow:auto;padding:8px;border:1px solid #e7e5e4;border-radius:12px;background:#fff;box-shadow:0 14px 34px rgba(28,25,23,.14)}
.rev-pop p{margin:2px 6px 8px;font-size:11px;line-height:1.6;color:#a8a29e}
.rev-pop a{display:flex;justify-content:space-between;padding:8px 10px;border-radius:8px;font-size:12.5px;color:#44403c;text-decoration:none}
.rev-pop a:hover{background:#f5f5f4}.rev-pop a small{color:#a8a29e}
.tag-box{display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:46px;padding:6px 10px;border:1px solid #d6d3d1;border-radius:7px;background:#fff;cursor:text}
.tag-box:focus-within{border-color:#78716c;box-shadow:0 0 0 3px rgba(120,113,108,.09)}
.tag-box input{flex:1 1 160px;min-width:120px;height:32px;border:0;outline:0;font-size:13px;background:transparent}
.tag-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 6px 4px 10px;border-radius:999px;background:#f0efed;font-size:12px;color:#44403c}
.tag-chip button{border:0;background:transparent;color:#a8a29e;font-size:15px;line-height:1;cursor:pointer;padding:0 3px}.tag-chip button:hover{color:#dc2626}
.adm-badge.draft{background:#e0e7ff;color:#3730a3}.adm-badge.scheduled{background:#dcfce7;color:#166534}
.adm-pin{margin-left:4px;font-size:12px}
</style>
</head>
<body class="min-h-screen bg-[#f7f6f2] text-stone-800 antialiased">
<header class="sticky top-0 z-50 border-b border-stone-200/90 bg-[#f7f6f2]/95 backdrop-blur-xl">
  <div class="admin-shell flex h-[68px] items-center justify-between gap-5">
    <a href="index.php" class="flex items-center gap-3 no-underline">
      <img src="../assets/logo_icon.png" alt="" width="32" height="32" style="display:block;width:32px;height:32px">
      <span class="text-[13px] font-semibold tracking-[.08em] text-stone-800" style="max-width:46vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(mb_strtoupper(defined('SITE_TITLE') ? SITE_TITLE : 'Naeggeolog'), ENT_QUOTES, 'UTF-8')?> ADMIN</span>
    </a>
    <span><a href="../hub/" class="rounded-full px-3 py-2 text-[11px] text-stone-500 transition hover:bg-white hover:text-stone-900">내 공간 ↗</a><a href="../index.php" class="rounded-full px-3 py-2 text-[11px] text-stone-500 transition hover:bg-white hover:text-stone-900">사이트 보기 ↗</a></span>
  </div>
</header>
<main class="admin-shell py-10 sm:py-12">
<?php
}
}

if (!function_exists('admin_nav')) {
function admin_nav(string $active = ''): void {
  $ic = fn($d) => '<svg viewBox="0 0 24 24" aria-hidden="true">' . $d . '</svg>';
  $groups = [
    '콘텐츠' => [
      ['list', 'index.php', '여행 목록', $ic('<path d="M4 6h16M4 12h16M4 18h10"/>')],
      ['photos', 'photos.php', '사진 관리', $ic('<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m21 16-4.5-4.5L8 19"/>')],
      ['stats', 'stats.php', '방문 통계', $ic('<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>')],
      ['site', 'site-settings.php', '홈페이지 설정', $ic('<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18"/>')],
      ['theme', 'theme.php', '테마 관리', $ic('<circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h9"/>')],
    ],
    '내 공간' => [
      ['hub-home', '../hub/', '내 공간 열기 ↗', $ic('<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/>')],
      ['hub-folders', 'hub-folders.php', '공유 폴더', $ic('<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>')],
      ['hub-users', 'hub-users.php', '사용자 관리', $ic('<circle cx="9" cy="8" r="3.2"/><path d="M3 20c.7-3.4 3.2-5.2 6-5.2s5.3 1.8 6 5.2"/><circle cx="17" cy="9" r="2.4"/><path d="M16.5 14.2c2.2.2 4 1.7 4.5 4.3"/>')],
      ['hub-modules', 'hub-modules.php', '기능', $ic('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/>')],
    ],
    '시스템' => [
      ['thumbs', 'thumbs.php', '이미지 최적화', $ic('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="1.5"/><path d="m21 15-5-5L5 21"/>')],
      ['health', 'health.php', '사이트 점검', $ic('<path d="M3 12h4l2-6 4 12 2-6h6"/>')],
      ['backup', 'backup.php', '백업 · 복원', $ic('<path d="M12 3v12M7 10l5 5 5-5M4 19h16"/>')],
      ['storage', 'storage.php', '저장소 (SQLite)', $ic('<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>')],
    ],
    '계정' => [
      ['sec', 'security.php', '보안 · 2단계 인증', $ic('<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6Z"/><path d="m9 12 2 2 4-4"/>')],
      ['pw', 'change-password.php', '비밀번호 변경', $ic('<rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>')],
      ['view', '../index.php', '사이트 보기 ↗', $ic('<path d="M14 4h6v6M20 4 10 14M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>')],
      ['out', 'logout.php', '로그아웃', $ic('<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4M16 8l4 4-4 4M20 12H9"/>')],
    ],
  ];
  echo '<aside class="adm-side" aria-label="관리 메뉴">';
  foreach ($groups as $g => $items) {
    echo '<div class="adm-nav-group"><h3>' . $g . '</h3><nav class="adm-nav">';
    foreach ($items as [$k, $href, $label, $svg]) echo '<a href="' . $href . '"' . ($k === $active ? ' aria-current="page"' : '') . '>' . $svg . $label . '</a>';
    if ($g === '시스템') echo '<form method="post" action="cache-clear.php" onsubmit="return confirm(\'썸네일 캐시를 비울까요? 원본 사진은 그대로이며, 이미지 최적화 메뉴에서 다시 만들 수 있습니다.\');"><input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '"><button type="submit">' . $ic('<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>') . '캐시 비우기</button></form>';
    echo '</nav></div>';
  }
  echo '</aside>';
}
}
if (!function_exists('admin_foot')) {
function admin_foot(){
?></main></body></html>
<?php
}
}

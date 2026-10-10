<?php
/** 내 공간(hub) 페이지 공통: 로그인 확인 · 상단 바 · 레이아웃. 직접 열면 아무것도 하지 않습니다. */
require_once __DIR__ . '/../includes/hub.php';
require_once __DIR__ . '/../theme.php';

require_once __DIR__ . '/../includes/data.php';   // e() 등 공통 함수

/** 현재 페이지에서 hub 폴더까지의 상대 경로 접두어 ('' · '../' · '../../') */
function hub_base(): string {
    static $b = null;
    if ($b !== null) return $b;
    $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $sd = str_replace('\\', '/', dirname($script)); $hd = str_replace('\\', '/', __DIR__);
    $rest = strpos($sd, $hd) === 0 ? trim(substr($sd, strlen($hd)), '/') : '';
    return $b = $rest === '' ? '' : str_repeat('../', substr_count($rest, '/') + 1);
}
function hub_url(string $p = ''): string { return hub_base() . ltrim($p, '/'); }
/** 모듈 url: hub 기준 상대 경로('modules/files/', '../index.php') 또는 절대 주소(https://…, /…) */
function hub_module_href(array $m): string { return preg_match('~^(https?:)?/~', (string)$m['url']) ? (string)$m['url'] : hub_url((string)$m['url']); }

/** 개인 페이지: 캐시·검색엔진 색인 금지 */
function hub_private_headers(): void {
    if (headers_sent()) return;
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
}

/** 로그인 필수. 회원이 임시 비밀번호 상태면 비밀번호 변경 화면으로 */
function hub_require_login(): array {
    hub_private_headers();
    if (!hub_available()) { http_response_code(500); exit('PHP SQLite(pdo_sqlite) 확장이 필요합니다. Web Station → PHP 프로필 → 확장 모듈에서 켜 주세요.'); }
    $u = hub_current_user();
    if (!$u) {
        $back = (string)($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . hub_url('login.php') . ($back !== '' ? '?next=' . rawurlencode($back) : ''));
        exit;
    }
    if (!empty($u['must_change']) && basename((string)$_SERVER['SCRIPT_NAME']) !== 'account.php' && !hub_is_owner($u)) {
        header('Location: ' . hub_url('account.php')); exit;
    }
    return $u;
}

$__HUB_ICO = 'width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

/** $opt: ['css' => ['desk.css'], 'body' => '본문 class'] (선택) */
function hub_head(string $title, ?array $u = null, string $active = '', array $opt = []): void {
    global $__HUB_ICO;
    $ico = $__HUB_ICO;
    $mods = $u ? hub_modules_for($u) : [];
    ?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="color-scheme" content="light dark">
<title><?=e($title)?> · 내 공간</title>
<script>try{var t=localStorage.getItem("naeggeolog-theme");if(t!=="dark"&&t!=="light")t=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";document.documentElement.dataset.theme=t}catch(e){}</script>
<?=travel_theme_style()?>
<link rel="icon" href="<?=e(hub_url('../favicon.ico'))?>" sizes="48x48">
<link rel="icon" type="image/svg+xml" href="<?=e(hub_url('../assets/favicon.svg'))?>">
<link rel="stylesheet" href="<?=e(hub_url('hub.css'))?>?v=<?=(int)@filemtime(__DIR__ . '/hub.css')?>">
<?php foreach ((array)($opt['css'] ?? []) as $c): ?><link rel="stylesheet" href="<?=e(hub_url($c))?>?v=<?=(int)@filemtime(__DIR__ . '/' . $c)?>">
<?php endforeach; ?>
<script>if(window.self!==window.top)document.documentElement.classList.add('hb-embedded')</script>
</head>
<body<?=!empty($opt['body']) ? ' class="' . e($opt['body']) . '"' : ''?>>
<?php if ($u): ?>
<header class="hb-top">
  <div class="hb-wrap hb-top-row">
    <a class="hb-brand" href="<?=e(hub_url(''))?>"><img src="<?=e(hub_url('../assets/logo_icon.png'))?>" alt="" width="30" height="30"><span>내 공간</span></a>
    <nav class="hb-tabs" aria-label="기능">
      <a href="<?=e(hub_url(''))?>" <?=$active === 'home' ? 'aria-current="page"' : ''?>>홈</a>
      <?php foreach ($mods as $id => $m): $href = hub_module_href($m); ?>
      <a href="<?=e($href)?>" <?=$active === $id ? 'aria-current="page"' : ''?>><?=e($m['title'])?></a>
      <?php endforeach; ?>
    </nav>
    <div class="hb-tools">
      <button type="button" class="hb-icon" data-theme-toggle aria-label="다크 모드 전환" title="다크 모드"><svg <?=$ico?>><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8Z"/></svg></button>
      <details class="hb-user">
        <summary aria-label="계정 메뉴"><span class="hb-avatar"><?=e(mb_strtoupper(mb_substr((string)$u['display_name'], 0, 1)))?></span><span class="hb-user-name"><?=e($u['display_name'])?></span></summary>
        <div class="hb-menu">
          <p><b><?=e($u['display_name'])?></b><small><?=hub_is_owner($u) ? '주인 · 모든 폴더' : '@' . e($u['username'])?></small></p>
          <?php if (hub_is_owner($u)): ?>
            <a href="<?=e(hub_url('../admin/index.php'))?>">관리자 화면</a>
            <a href="<?=e(hub_url('../admin/hub-users.php'))?>">사용자 · 권한 관리</a>
            <a href="<?=e(hub_url('../admin/hub-folders.php'))?>">공유 폴더 설정</a>
            <a href="<?=e(hub_url('../admin/change-password.php'))?>">비밀번호 변경</a>
          <?php else: ?>
            <a href="<?=e(hub_url('account.php'))?>">비밀번호 변경</a>
          <?php endif; ?>
          <a href="<?=e(hub_url('../index.php'))?>">여행 블로그 보기 ↗</a>
          <form method="post" action="<?=e(hub_url('logout.php'))?>"><input type="hidden" name="csrf" value="<?=e(hub_csrf())?>"><button type="submit">로그아웃</button></form>
        </div>
      </details>
    </div>
  </div>
</header>
<?php endif; ?>
<main class="hb-wrap hb-main">
<?php
}

function hub_foot(array $scripts = []): void {
    ?></main>
<script>
document.addEventListener('click',function(e){var b=e.target.closest('[data-theme-toggle]');if(!b)return;var d=document.documentElement,n=d.dataset.theme==='dark'?'light':'dark';d.dataset.theme=n;try{localStorage.setItem('naeggeolog-theme',n)}catch(x){}});
document.addEventListener('click',function(e){document.querySelectorAll('details.hb-user[open]').forEach(function(d){if(!d.contains(e.target))d.removeAttribute('open')})});
</script>
<?php foreach ($scripts as $s): ?><script src="<?=e(hub_url($s))?>?v=<?=(int)@filemtime(__DIR__ . '/' . $s)?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

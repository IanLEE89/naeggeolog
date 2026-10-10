# 내 공간 기능(모듈) 추가하기

`hub/modules/<이름>/` 폴더 하나가 기능 하나입니다. 이름은 영문 소문자·숫자·`-`·`_`만 씁니다.
폴더를 만들면 내 공간 홈에 카드, 상단에 메뉴가 자동으로 생기고, 관리자 → **기능** 에서 켜고 끌 수 있습니다.

## 1. `module.php` — 카드 정보 (필수)

```php
<?php
return [
    'title'   => '메모',                         // 카드·메뉴 이름
    'desc'    => '간단한 메모장',                 // 카드 설명
    'url'     => 'modules/memo/',               // 생략하면 이 값. 다른 곳으로 보내려면 '../index.php' 또는 'https://…'
    'order'   => 30,                            // 작을수록 앞 (여행 10, 파일 20)
    'icon'    => '<path d="M5 4h14v16H5z"/>',    // 24×24 SVG 내용(선 아이콘)
    // 선택: 이 사용자에게 보일지
    'visible' => function (array $u): bool { return hub_is_owner($u); },
    // 선택: 카드 아래 작은 글씨
    'badge'   => function (array $u): string { return '메모 3개'; },
];
```

`module.php` 는 홈을 열 때마다 읽히므로 무거운 작업은 넣지 마세요.

## 2. `index.php` — 화면

```php
<?php
require_once dirname(__DIR__, 2) . '/_boot.php';
$u = hub_require_login();          // 로그인 안 했으면 로그인 화면으로
hub_head('메모', $u, 'memo');       // 세 번째 값 = 폴더 이름(상단 메뉴 강조)
?>
<p class="hb-eyebrow">MEMO</p>
<h1 class="hb-h1">메모</h1>
<div class="hb-card hb-pad">내용</div>
<?php hub_foot();
```

## 쓸 수 있는 것

| 함수 | 설명 |
|---|---|
| `hub_require_login()` | 현재 사용자 배열. `id`(주인은 0), `username`, `display_name`, `role`(`owner`/`member`) |
| `hub_is_owner($u)` | 주인(관리자)인지 |
| `hub_db()` | `private/hub.sqlite` PDO. 새 기능용 표는 `CREATE TABLE IF NOT EXISTS memo_…` 처럼 이름 앞에 기능 이름을 붙이세요 |
| `hub_csrf()` / `hub_csrf_ok($t)` | POST 폼 보호: `<input type="hidden" name="csrf" value="<?=e(hub_csrf())?>">` |
| `hub_url('…')` | hub 폴더 기준 상대 주소 |
| `e($v)` | HTML 이스케이프 |

CSS 클래스: `hb-h1`, `hb-sub`, `hb-eyebrow`, `hb-card`, `hb-pad`, `hb-btn`, `hb-btn-primary`, `hb-form`, `hb-empty` 등 (`hub/hub.css`).

## 주의
- 로그인 없이 열려야 하는 파일은 만들지 마세요. 모든 화면 첫 줄에서 `hub_require_login()` 을 부르세요.
- 사용자 입력을 그대로 출력하지 말고 `e()` 로 감싸세요. 저장·삭제는 POST + `hub_csrf_ok()` 로만.
- 이름이 `_` 로 시작하는 PHP 파일과 `module.php` 는 웹에서 직접 열 수 없게 막혀 있습니다(Apache).

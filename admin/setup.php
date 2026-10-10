<?php
/**
 * 처음 설치 마법사 — 관리자 계정(private/admin.json)이 없을 때만 열립니다.
 *   1) 서버 환경 점검(PHP 확장 · 폴더 쓰기 권한)
 *   2) 사이트 이름 · 관리자 아이디/비밀번호 · (선택) Google Maps API 키 저장
 *   3) 바로 로그인된 상태로 관리자 화면으로 이동
 * 계정이 만들어진 뒤에는 이 페이지에 접근해도 로그인 화면으로 돌아갑니다.
 */
require_once __DIR__ . '/auth.php';

if (admin_record()) admin_redirect('login.php');

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$root = dirname(__DIR__);

/** 폴더가 없으면 만들고, 실제로 파일을 써 봐서 쓰기 가능 여부를 확인 */
function setup_dir_writable(string $dir): bool {
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    $t = rtrim($dir, '/\\') . '/.write-test-' . bin2hex(random_bytes(4));
    if (@file_put_contents($t, 'ok') === false) return false;
    @unlink($t);
    return true;
}

$checks = [];   // [이름, 통과 여부, 필수 여부, 안내]
$checks[] = ['PHP 8.0 이상', version_compare(PHP_VERSION, '8.0.0', '>='), true, '현재 ' . PHP_VERSION];
$checks[] = ['pdo_sqlite 확장', class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true), true, '여행 글·내 공간 데이터 저장에 필요'];
$checks[] = ['gd 확장', extension_loaded('gd'), true, '사진 축소·썸네일 생성에 필요'];
$checks[] = ['fileinfo 확장', extension_loaded('fileinfo'), true, '업로드 파일 형식 확인에 필요'];
$checks[] = ['mbstring 확장', extension_loaded('mbstring'), true, '한글 처리에 필요'];
$checks[] = ['zip 확장', class_exists('ZipArchive'), false, '백업 내려받기·복원에 사용(없어도 나머지 기능은 동작)'];
$checks[] = ['exif 확장', function_exists('exif_read_data'), false, '사진 회전·촬영 정보 읽기에 사용(권장)'];
foreach ([['private', PRIVATE_DIR], ['cache', CACHE_DIR], ['travel-photos', $root . '/travel-photos']] as [$label, $dir]) {
    $checks[] = [$label . ' 폴더 쓰기', setup_dir_writable($dir), true, '웹 서버 계정에 쓰기 권한 필요 — ' . $dir];
}
$blocking = array_filter($checks, fn($c) => $c[2] && !$c[1]);

$error = '';
$in = [
    'site_name' => trim((string)($_POST['site_name'] ?? 'Naeggeolog')),
    'username'  => trim((string)($_POST['username'] ?? '')),
    'maps_key'  => trim((string)($_POST['maps_key'] ?? '')),
    'maps_id'   => trim((string)($_POST['maps_id'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    if (!csrf_check($_POST['csrf'] ?? ''))                              $error = '잘못된 요청입니다. 새로고침 후 다시 시도해주세요.';
    elseif ($blocking)                                                   $error = '위의 필수 점검 항목을 먼저 해결해주세요.';
    elseif ($in['site_name'] === '' || mb_strlen($in['site_name']) > 60) $error = '사이트 이름을 1~60자로 입력해주세요.';
    elseif (!preg_match('/^[A-Za-z0-9._-]{4,32}$/', $in['username']))  $error = '아이디는 영문·숫자·._- 4~32자로 입력해주세요.';
    elseif (strtolower($in['username']) === 'admin')                     $error = 'admin 은 추측하기 쉬운 아이디입니다. 다른 아이디를 사용해주세요.';
    elseif (mb_strlen($p1) < 10)                                         $error = '비밀번호는 10자 이상으로 입력해주세요.';
    elseif ($p1 !== $p2)                                                 $error = '두 비밀번호가 일치하지 않습니다.';
    elseif ($in['maps_key'] !== '' && !preg_match('/^[A-Za-z0-9_-]{20,80}$/', $in['maps_key'])) $error = 'Google Maps API 키 형식이 올바르지 않습니다.';
    elseif ($in['maps_id'] !== '' && !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $in['maps_id']))    $error = '지도 ID 형식이 올바르지 않습니다.';
    else {
        // 두 사람이 동시에 설치를 진행해도 계정은 하나만 만들어지도록 잠금
        $lock = @fopen(rtrim(PRIVATE_DIR, '/\\') . '/.setup.lock', 'c');
        if ($lock) flock($lock, LOCK_EX);
        if (admin_record()) { if ($lock) { flock($lock, LOCK_UN); fclose($lock); } admin_redirect('login.php'); }

        $ok = admin_save_record([
            'username'      => $in['username'],
            'password_hash' => password_hash($p1, PASSWORD_DEFAULT),
            'must_change'   => false,
            'changed_at'    => date('c'),
        ]);
        if ($ok) {
            require_once $root . '/site.php';
            $site = $TRAVEL_SITE;
            $site['site_name'] = $in['site_name'];
            $site['footer_copyright'] = '© ' . $in['site_name'] . '. All rights reserved.';
            travel_write_json(rtrim(PRIVATE_DIR, '/\\') . '/site.json', $site);
            if ($in['maps_key'] !== '') {
                $php = "<?php\n// Google Maps Platform 설정 (설치 마법사에서 저장). 관리자 → 홈페이지 설정에서 바꿀 수 있습니다.\n"
                     . "return ['api_key' => " . var_export($in['maps_key'], true) . ", 'map_id' => " . var_export($in['maps_id'], true) . "];\n";
                @file_put_contents(rtrim(PRIVATE_DIR, '/\\') . '/google-maps.php', $php, LOCK_EX);
            }
        }
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); @unlink(rtrim(PRIVATE_DIR, '/\\') . '/.setup.lock'); }

        if ($ok && admin_record()) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $in['username'];
            login_log('ok', $in['username']);
            admin_redirect('index.php?welcome=1');
        }
        $error = '관리자 계정 파일을 저장하지 못했습니다. 웹 서버 계정에 다음 폴더의 쓰기 권한을 주세요: ' . PRIVATE_DIR;
    }
}

require __DIR__ . '/style.php';
admin_head('처음 설치');
$inp = 'w-full border border-stone-300 bg-white px-3 py-3 text-sm outline-none focus:border-stone-700';
?>
<div class="mx-auto max-w-lg pt-12 pb-16">
  <div class="mb-8 text-center">
    <p class="text-xs tracking-[.18em] text-stone-400">FIRST SETUP</p>
    <img src="../assets/logo_icon.png" alt="" width="56" height="56" style="display:block;margin:0 auto 18px;width:56px;height:56px">
    <h1 class="mt-3 text-2xl font-serif tracking-tight">Naeggeolog 설치</h1>
    <p class="mt-3 text-sm leading-7 text-stone-500">사이트 이름과 관리자 계정을 만들면 바로 사용할 수 있습니다.<br>이 화면은 계정이 만들어지기 전까지만 열립니다.</p>
  </div>

  <section class="border-y border-stone-200 py-6">
    <h2 class="text-sm font-semibold text-stone-800">1. 서버 환경 점검</h2>
    <ul class="mt-4 space-y-2 text-sm">
      <?php foreach ($checks as [$name, $pass, $req, $note]): ?>
        <li class="flex items-start gap-3">
          <span class="mt-0.5 inline-block w-12 shrink-0 text-xs font-semibold" style="color:<?= $pass ? '#047857' : ($req ? '#b91c1c' : '#b45309') ?>"><?= $pass ? '통과' : ($req ? '필수' : '권장') ?></span>
          <span><span class="text-stone-800"><?= $h($name) ?></span><br><span class="text-xs text-stone-400 break-all"><?= $h($note) ?></span></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($blocking): ?>
      <p class="mt-4 text-xs leading-6 text-red-700">필수 항목을 해결한 뒤 이 페이지를 새로고침하세요. 확장 모듈은 php.ini(또는 호스팅 관리 화면)에서 켜고, 폴더 권한은 웹 서버 계정(예: www-data, http, apache)에 쓰기 권한을 주면 됩니다.</p>
    <?php endif; ?>
  </section>

  <form method="post" class="mt-8 space-y-5" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= $h(csrf_token()) ?>">
    <h2 class="text-sm font-semibold text-stone-800">2. 사이트와 관리자 계정</h2>
    <label class="block"><span class="mb-2 block text-xs text-stone-500">사이트 이름</span>
      <input name="site_name" required maxlength="60" value="<?= $h($in['site_name']) ?>" placeholder="예: Naeggeolog" class="<?= $inp ?>"></label>
    <label class="block"><span class="mb-2 block text-xs text-stone-500">관리자 아이디 (영문·숫자·._- 4~32자)</span>
      <input name="username" required autocomplete="username" value="<?= $h($in['username']) ?>" class="<?= $inp ?>"></label>
    <label class="block"><span class="mb-2 block text-xs text-stone-500">비밀번호 (10자 이상)</span>
      <input type="password" name="password" required autocomplete="new-password" class="<?= $inp ?>"></label>
    <label class="block"><span class="mb-2 block text-xs text-stone-500">비밀번호 확인</span>
      <input type="password" name="password2" required autocomplete="new-password" class="<?= $inp ?>"></label>

    <details class="border border-stone-200 bg-white px-4 py-3" <?= ($in['maps_key'] !== '') ? 'open' : '' ?>>
      <summary class="cursor-pointer text-sm text-stone-700">3. Google Maps API 키 (선택 — 나중에 넣어도 됩니다)</summary>
      <p class="mt-3 text-xs leading-6 text-stone-500">홈의 여행 지도, 여행 상세의 이동 경로, 관리자의 장소 검색에 쓰입니다. 키가 없어도 나머지 기능은 모두 동작합니다.
        Google Cloud Console에서 <b>Maps JavaScript API</b>와 <b>Places API (New)</b>를 켠 키를 만들고, 웹사이트 주소(HTTP 리퍼러)로 제한하세요.</p>
      <label class="mt-3 block"><span class="mb-2 block text-xs text-stone-500">API 키</span>
        <input name="maps_key" value="<?= $h($in['maps_key']) ?>" class="<?= $inp ?>" placeholder="AIza…"></label>
      <label class="mt-3 block"><span class="mb-2 block text-xs text-stone-500">지도 ID (선택, 고급 마커용)</span>
        <input name="maps_id" value="<?= $h($in['maps_id']) ?>" class="<?= $inp ?>"></label>
    </details>

    <?php if ($error): ?><p class="text-xs leading-6 text-red-700"><?= $h($error) ?></p><?php endif; ?>
    <button class="w-full bg-stone-900 px-4 py-3 text-sm text-white hover:bg-stone-700 disabled:opacity-40" <?= $blocking ? 'disabled' : '' ?>>설치 완료하고 관리자 화면으로</button>
  </form>
</div>
<?php admin_foot(); ?>

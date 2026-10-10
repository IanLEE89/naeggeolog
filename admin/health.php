<?php
// 사이트 점검: PHP 환경 · 확장 · 쓰기 권한 · 디스크 · 보안(민감 파일이 인터넷에서 열리는지) · 썸네일 준비율 · 백업 상태를 한 화면에서.
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/../includes/data.php';
require_once __DIR__.'/../includes/images.php';
require_once __DIR__.'/../includes/backup.php';
require_once __DIR__.'/style.php';

function hc_bytes(string $v): int { $v = trim($v); if ($v === '' || $v === '-1') return PHP_INT_MAX; $n = (int)$v; return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n }; }
function hc_fmt(int $b): string { return $b >= 1073741824 ? round($b / 1073741824, 1) . ' GB' : ($b >= 1048576 ? round($b / 1048576) . ' MB' : round($b / 1024) . ' KB'); }
function hc_web(string $abs): ?string {
    $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? ''); $real = realpath($abs);
    if (!$doc || !$real || strpos($real, $doc . DIRECTORY_SEPARATOR) !== 0) return null;
    return str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($doc)));
}

// ── AJAX: 썸네일 준비율(무작위 80장 표본) ──
if (($_GET['thumbs'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    $all = []; foreach (store()->all() as $t) foreach (trip_photo_paths($t) as $p) $all[$p] = true;
    $all = array_keys($all); $total = count($all); shuffle($all); $ok = 0; $chk = 0; $dir = rtrim(CACHE_DIR, '/\\') . '/thumbs';
    foreach (array_slice($all, 0, 80) as $rel) {
        $info = photo_info($rel); if (!$info) continue; $chk++;
        $ext = photo_thumb_ext($info, photo_webp_ok());
        if (is_file(photo_thumb_file($info, $rel, min(960, $info['w']), $ext))) $ok++;
    }
    echo json_encode(['total' => $total, 'checked' => $chk, 'ok' => $ok]); exit;
}

$rows = [];   // [group, label, level(ok|warn|bad|info), detail]
$add = function (string $g, string $label, string $lv, string $detail = '') use (&$rows) { $rows[] = [$g, $label, $lv, $detail]; };

// 서버
$add('서버', 'PHP 버전', version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'bad', PHP_VERSION . ' (8.0 이상 필요)');
$add('서버', '서버 프로그램', 'info', (string)($_SERVER['SERVER_SOFTWARE'] ?? '?'));
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$add('서버', 'HTTPS', $https ? 'ok' : 'warn', $https ? '보안 연결' : 'HTTP 로 접속 중 — 앱 설치(PWA)·로그인 쿠키 보호를 위해 HTTPS 권장');
$add('서버', 'SITE_URL', SITE_URL !== '' ? 'ok' : 'info', SITE_URL !== '' ? SITE_URL : '비어 있음(주소를 자동 감지) — 검색엔진 등록 시 config.local.php 에 지정하면 더 안정적');
$add('서버', '시간대', 'info', date_default_timezone_get() . ' · 지금 ' . date('Y-m-d H:i'));
$mem = hc_bytes((string)ini_get('memory_limit'));
$add('서버', 'memory_limit', $mem >= (256 << 20) ? 'ok' : 'warn', ini_get('memory_limit') . ($mem < (256 << 20) ? ' — 큰 사진 변환 시 부족할 수 있음(256M 권장)' : ''));
$up = hc_bytes((string)ini_get('upload_max_filesize')); $po = hc_bytes((string)ini_get('post_max_size'));
$add('서버', '업로드 한도', $up >= (20 << 20) && $po >= (20 << 20) ? 'ok' : 'warn', 'upload_max_filesize ' . ini_get('upload_max_filesize') . ' · post_max_size ' . ini_get('post_max_size'));
$add('서버', '실행 시간', 'info', 'max_execution_time ' . ini_get('max_execution_time') . '초');

// 확장
foreach ([['gd', '사진 변환(필수)', true], ['mbstring', '한글 문자열 처리(필수)', true], ['fileinfo', '업로드 파일 형식 검사', true], ['zip', '백업 zip', false], ['exif', '사진 회전/촬영정보', false], ['pdo_sqlite', 'SQLite 저장소(필수)', true]] as [$ext, $why, $req]) {
    $has = extension_loaded($ext); $add('PHP 확장', $ext, $has ? 'ok' : ($req ? 'bad' : 'warn'), $why . ($has ? '' : ' — Web Station → PHP 프로필 → 확장 모듈에서 켜세요'));
}
$add('PHP 확장', 'WebP 출력', photo_webp_ok() ? 'ok' : 'warn', photo_webp_ok() ? '썸네일을 WebP 로 만듭니다' : 'GD 에 WebP 지원이 없어 JPEG 로 대체됩니다');

// 쓰기 권한
foreach ([['cache', CACHE_DIR], ['private', PRIVATE_DIR], ['travel-photos', photo_root()]] as [$n, $d]) {
    $ok = is_dir($d) && is_writable($d); $add('쓰기 권한', $n, $ok ? 'ok' : (is_dir($d) ? 'bad' : 'warn'), $ok ? '쓰기 가능' : (is_dir($d) ? '쓰기 불가 — 폴더 권한(http 그룹 쓰기)을 확인하세요' : '폴더가 아직 없음'));
}
$free = @disk_free_space(dirname(__DIR__)); $tot = @disk_total_space(dirname(__DIR__));
if ($free !== false && $tot) { $pct = $free / $tot * 100; $add('쓰기 권한', '디스크 여유', $pct > 10 ? 'ok' : ($pct > 3 ? 'warn' : 'bad'), hc_fmt((int)$free) . ' / ' . hc_fmt((int)$tot) . ' (' . round($pct) . '%)'); }

// 저장소 · 백업
$st = store(); $add('데이터', '저장소', 'ok', 'SQLite ' . round((int)@filesize(SqliteStore::dbPath()) / 1024) . ' KB · 여행 ' . $st->count() . '개');
$__leg = array_filter([dirname(__DIR__) . '/data', ADMIN_DIR], fn($d) => is_dir($d) && (glob($d . '/*') ?: []));
$add('데이터', '예전 data 폴더', $__leg ? 'info' : 'ok', $__leg ? '설정은 private 폴더로 옮겨졌습니다. data/ · admin/data/ 폴더는 지워도 됩니다(먼저 백업을 받아 두세요).' : '없음 — 정리 완료');
$bd = backup_days_since_download(); $add('데이터', '마지막 백업 내려받기', $bd === null ? 'warn' : ($bd < 30 ? 'ok' : 'warn'), $bd === null ? '아직 없음 — 백업 메뉴에서 내려받으세요' : $bd . '일 전');
$snaps = backup_list(); $add('데이터', '서버 자동 스냅샷', $snaps ? 'ok' : 'info', $snaps ? '최근 ' . count($snaps) . '개 보관' : '아직 없음(관리자 목록을 열면 7일마다 생성)');
$adm = admin_record(); $add('보안', '2단계 인증', !empty($adm['totp_secret']) ? 'ok' : 'warn', !empty($adm['totp_secret']) ? '사용 중' : '꺼져 있음 — 보안 메뉴에서 켜는 것을 권장');
$add('보안', '초기 비밀번호', empty($adm['must_change']) ? 'ok' : 'bad', empty($adm['must_change']) ? '변경됨' : '아직 초기 비밀번호입니다');

// 인터넷에서 열리면 안 되는 파일 목록(브라우저에서 실제로 열어 확인)
$probe = [];
foreach (array_merge([admin_file()], [SqliteStore::dbPath()], glob(PRIVATE_DIR . '/*.json') ?: [], glob(PRIVATE_DIR . '/backups/*.zip') ?: [], glob(PRIVATE_DIR . '/revisions/*/*.json') ?: []) as $f) {
    if (count($probe) >= 5) break; $w = is_file($f) ? hc_web($f) : null; if ($w) $probe[$w] = true;
}
$probe = array_keys($probe);

admin_head('사이트 점검'); ?>
<div class="adm-wrap">
  <?php admin_nav('health'); ?>
  <section>
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="text-xs tracking-[.18em] text-stone-400">HEALTH CHECK</p>
        <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">사이트 점검</h1>
        <p class="mt-2 text-sm text-stone-500">서버 설정이 바뀌었거나 이상할 때 먼저 열어 보세요.</p>
      </div>
      <a class="adm-btn" style="height:38px" href="health.php">다시 점검</a>
    </div>

    <?php $bad = count(array_filter($rows, fn($r) => $r[2] === 'bad')); $warn = count(array_filter($rows, fn($r) => $r[2] === 'warn')); ?>
    <div class="adm-note" id="hcSummary" style="<?=$bad ? 'border-color:#fecaca;background:#fef2f2;color:#991b1b' : ($warn ? 'border-color:#fde68a;background:#fffbeb;color:#92400e' : 'border-color:#bbf7d0;background:#f0fdf4;color:#166534')?>">
      <?=$bad ? "해결이 필요한 항목 {$bad}개" : ($warn ? "확인하면 좋은 항목 {$warn}개" : '모두 정상입니다 ✓')?><?=$bad && $warn ? " · 권장 {$warn}개" : ''?>
    </div>

    <table class="adm-table" style="margin-top:14px"><tbody>
      <?php $g = ''; foreach ($rows as [$grp, $label, $lv, $detail]): if ($grp !== $g): $g = $grp; ?>
        <tr><td colspan="3" style="background:#faf9f6;font-size:11px;font-weight:700;letter-spacing:.12em;color:#78716c"><?=e($grp)?></td></tr>
      <?php endif; ?>
        <tr><td style="width:34px;padding-right:0"><span class="adm-dot <?=$lv?>"></span></td><td style="white-space:nowrap;font-weight:600;color:#292524"><?=e($label)?></td><td style="color:#78716c"><?=e($detail)?></td></tr>
      <?php endforeach; ?>
      <tr><td colspan="3" style="background:#faf9f6;font-size:11px;font-weight:700;letter-spacing:.12em;color:#78716c">보안 · 성능 (브라우저에서 실제로 확인)</td></tr>
      <tr><td><span class="adm-dot" id="dotProbe"></span></td><td style="white-space:nowrap;font-weight:600;color:#292524">데이터 파일 비공개</td><td style="color:#78716c" id="txtProbe">확인 중…</td></tr>
      <tr><td><span class="adm-dot" id="dotThumb"></span></td><td style="white-space:nowrap;font-weight:600;color:#292524">썸네일 준비율</td><td style="color:#78716c" id="txtThumb">확인 중…</td></tr>
    </tbody></table>
  </section>
</div>
<script>
(function(){
  function set(id, lv, txt){ document.getElementById('dot'+id).className='adm-dot '+lv; document.getElementById('txt'+id).textContent=txt; }
  var urls=<?=json_encode($probe, JSON_UNESCAPED_SLASHES)?>;
  if(!urls.length) set('Probe','info','확인할 데이터 파일이 아직 없습니다.');
  else Promise.all(urls.map(function(u){ return fetch(u,{cache:'no-store',credentials:'omit'}).then(function(r){return r.ok?u:null}).catch(function(){return null}); })).then(function(res){
    var open=res.filter(Boolean);
    if(open.length) set('Probe','bad','인터넷에서 직접 열립니다: '+open.join(', ')+' — README 의 "데이터 폴더 보호"를 적용하세요(.htaccess 가 적용되지 않는 환경).');
    else set('Probe','ok','여행 DB·계정·백업 파일 '+urls.length+'개가 외부에서 열리지 않습니다.');
  });
  fetch('health.php?thumbs=1',{cache:'no-store'}).then(function(r){return r.json()}).then(function(d){
    if(!d.total){ set('Thumb','info','사진이 없습니다.'); return; }
    var p=d.checked?Math.round(d.ok/d.checked*100):0;
    set('Thumb', p>=90?'ok':(p>=50?'warn':'bad'), '사진 '+d.total+'장 중 무작위 '+d.checked+'장 표본에서 '+p+'% 준비됨'+(p<90?' — "이미지 최적화" 메뉴에서 미리 만들어 두면 첫 방문이 빨라집니다.':''));
  }).catch(function(){ set('Thumb','info','확인하지 못했습니다.'); });
})();
</script>
<?php admin_foot();

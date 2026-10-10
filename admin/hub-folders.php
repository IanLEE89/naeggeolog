<?php
// 내 공간 · 공유 폴더 — NAS 폴더를 골라 내 공간에 연결하고, 누가 볼 수 있는지 정합니다. (NAS 파일은 절대 바꾸지 않음: 읽기 전용)
require_once __DIR__ . '/auth.php'; require_admin();
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/hub.php';
require_once __DIR__ . '/style.php';

if (!hub_available()) { admin_head('공유 폴더'); echo '<p>PHP SQLite(pdo_sqlite) 확장이 필요합니다.</p>'; admin_foot(); exit; }

$msg = ''; $err = '';
$users = hub_users();
$userIds = array_map('intval', array_column($users, 'id'));
$pickUsers = fn($raw) => array_values(array_intersect($userIds, array_map('intval', (array)$raw)));
function hub_set_share_users(int $sid, array $ids): void {
    $db = hub_db(); $db->beginTransaction();
    $db->prepare('DELETE FROM share_access WHERE share_id = ?')->execute([$sid]);
    $ins = $db->prepare('INSERT OR IGNORE INTO share_access(share_id,user_id) VALUES(?,?)');
    foreach ($ids as $uid) $ins->execute([$sid, (int)$uid]);
    $db->commit();
}
/** 폴더 상태 점검: [ok(bool), 설명] */
function hub_check_dir(string $p): array {
    if ($p === '' || ($p[0] !== '/' && !preg_match('~^[A-Za-z]:/~', $p))) return [false, '절대 경로(/volume1/…, /home/… 또는 D:/… 처럼)로 입력하세요.'];
    $ob = (string)ini_get('open_basedir');
    if ($ob !== '') {
        $okOb = false;
        foreach (explode(PATH_SEPARATOR, $ob) as $b) { $b = rtrim($b, '/'); if ($b !== '' && ($p === $b || strpos($p . '/', $b . '/') === 0)) { $okOb = true; break; } }
        if (!$okOb) return [false, 'PHP open_basedir 에 이 경로가 없습니다. Web Station → 스크립트 언어 설정 → PHP 프로필 편집 → open_basedir 에 추가하세요.'];
    }
    if (!@is_dir($p)) return [false, '폴더가 없거나, 웹 서버(http 사용자)가 볼 수 없습니다.'];
    if (!@is_readable($p) || @opendir($p) === false) return [false, '웹 서버(http 사용자)에게 읽기 권한이 없습니다. File Station → 폴더 속성 → 권한에서 http 에 "읽기"를 주세요.'];
    return [true, '읽을 수 있음'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청입니다. 새로고침 후 다시 시도하세요.'); }
    $act = (string)($_POST['action'] ?? ''); $id = (int)($_POST['id'] ?? 0);
    try {
        if ($act === 'create') {
            $path = hub_norm_abs((string)($_POST['path'] ?? '')); $name = trim((string)($_POST['name'] ?? '')) ?: basename($path);
            [$ok, $why] = hub_check_dir($path);
            $priv = realpath(PRIVATE_DIR); $site = realpath(dirname(__DIR__));
            $real = realpath($path) ?: $path;
            if (!$ok) $err = $why;
            elseif (!hub_browse_allowed($real)) $err = 'NAS 볼륨(' . implode(', ', hub_browse_roots()) . ') 안의 폴더만 공유할 수 있습니다.';
            elseif ($path === '/' || ($priv && (strpos($priv . '/', rtrim($real, '/') . '/') === 0 || strpos(rtrim($real, '/') . '/', $priv . '/') === 0))) $err = '비밀 파일 폴더(private)와 겹치는 경로는 공유할 수 없습니다.';
            elseif ($site && strpos($site . '/', rtrim($real, '/') . '/') === 0) $err = '홈페이지 폴더 전체(또는 그 상위)를 공유하면 계정·설정 파일이 노출됩니다. 하위 폴더를 고르세요.';
            else {
                $s = hub_db()->prepare('SELECT id FROM shares WHERE path = ?'); $s->execute([$path]);
                if ($s->fetchColumn()) $err = '이미 추가된 폴더입니다.';
                else {
                    hub_db()->prepare('INSERT INTO shares(name,path,note,sort,created_at,allow_upload) VALUES(?,?,?,?,?,?)')
                        ->execute([mb_substr($name, 0, 40), $path, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120), (int)hub_db()->query('SELECT COALESCE(MAX(sort),0)+1 FROM shares')->fetchColumn(), date('c'), empty($_POST['allow_upload']) ? 0 : 1]);
                    hub_set_share_users((int)hub_db()->lastInsertId(), $pickUsers($_POST['users'] ?? []));
                    $msg = '"' . $name . '" 폴더를 내 공간에 연결했습니다.';
                }
            }
        } elseif ($act === 'save' && ($sh = hub_share($id))) {
            hub_db()->prepare('UPDATE shares SET name = ?, note = ?, active = ?, sort = ?, allow_upload = ? WHERE id = ?')
                ->execute([mb_substr(trim((string)($_POST['name'] ?? '')) ?: $sh['name'], 0, 40), mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120), empty($_POST['active']) ? 0 : 1, (int)($_POST['sort'] ?? 0), empty($_POST['allow_upload']) ? 0 : 1, $id]);
            hub_set_share_users($id, $pickUsers($_POST['users'] ?? []));
            $msg = '저장했습니다.';
        } elseif ($act === 'delete' && ($sh = hub_share($id))) {
            hub_db()->prepare('DELETE FROM shares WHERE id = ?')->execute([$id]);
            $msg = '"' . $sh['name'] . '" 연결을 해제했습니다. NAS 의 실제 폴더와 파일은 그대로입니다.';
        }
    } catch (Throwable $e) {
        $err = '저장하지 못했습니다: ' . $e->getMessage() . ' — private 폴더에 http 사용자 쓰기 권한이 있는지 확인하세요.';
    }
}

$shares = hub_shares_all();
$editId = (int)($_GET['edit'] ?? 0); $edit = $editId ? hub_share($editId) : null;
$byShare = [];
foreach (hub_db()->query('SELECT a.share_id, u.username, u.display_name FROM share_access a JOIN users u ON u.id = a.user_id')->fetchAll() as $row) $byShare[(int)$row['share_id']][] = $row['display_name'] ?: $row['username'];

// 폴더 선택기
$roots = hub_browse_roots();
$browse = isset($_GET['browse']) ? hub_norm_abs((string)$_GET['browse']) : '';
if ($browse !== '' && !hub_browse_allowed($browse)) $browse = '';
$pick = isset($_GET['pick']) ? hub_norm_abs((string)$_GET['pick']) : (string)($_POST['path'] ?? '');
$subdirs = null;
if ($browse !== '') {
    $subdirs = [];
    $h = @opendir(preg_match('~^[A-Za-z]:$~', $browse) ? $browse . '/' : $browse);
    if ($h) { while (($n = readdir($h)) !== false) { if ($n === '.' || $n === '..' || hub_is_hidden_name($n)) continue; if (@is_dir("$browse/$n")) $subdirs[] = $n; } closedir($h); natcasesort($subdirs); }
    else $subdirs = null;
}
$userBoxes = function (array $checked) use ($users) {
    if (!$users) { echo '<p class="field-help">아직 사용자가 없습니다. 주인(나)은 항상 모든 폴더를 볼 수 있어요. 가족·지인은 <a class="underline" href="hub-users.php">사용자 관리</a>에서 추가하세요.</p>'; return; }
    echo '<div class="hub-checks">';
    foreach ($users as $x) {
        $on = in_array((int)$x['id'], $checked, true);
        echo '<label class="hub-check"><input type="checkbox" name="users[]" value="' . (int)$x['id'] . '"' . ($on ? ' checked' : '') . '><span>' . e($x['display_name'] ?: $x['username']) . (!$x['active'] ? ' <small>(중지)</small>' : '') . '</span></label>';
    }
    echo '</div>';
};
$who = function_exists('posix_geteuid') && function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : '';
$ob = (string)ini_get('open_basedir');

admin_head('공유 폴더'); ?>
<style>
.hub-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.hub-checks{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.hub-check{display:inline-flex;cursor:pointer}.hub-check input{position:absolute;opacity:0}
.hub-check span{padding:7px 12px;border:1px solid #d6d3d1;border-radius:999px;background:#fff;font-size:12px;color:#57534e}
.hub-check input:checked+span{border-color:#1c1917;background:#1c1917;color:#fff}
.hub-check input:focus-visible+span{outline:2px solid #78716c;outline-offset:2px}
.hub-chip{display:inline-block;margin:2px 4px 2px 0;padding:1px 8px;border-radius:999px;background:#f0efed;font-size:11px;color:#57534e}
.hub-form{margin-top:14px;padding:20px;border:1px solid #e7e5e4;border-radius:14px;background:#fff;display:grid;gap:14px}
.hub-picker{margin-top:14px;border:1px solid #e7e5e4;border-radius:14px;background:#fff;overflow:hidden}
.hub-picker-head{display:flex;flex-wrap:wrap;align-items:center;gap:6px;padding:12px 14px;border-bottom:1px solid #f0efed;background:#faf9f6;font-size:12px}
.hub-picker-head a{color:#57534e;text-decoration:none;padding:2px 6px;border-radius:6px}.hub-picker-head a:hover{background:#ece9e4}
.hub-picker ul{list-style:none;margin:0;padding:0;max-height:340px;overflow:auto}
.hub-picker li{display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid #f5f5f4;font-size:13px}
.hub-picker li:last-child{border-bottom:0}
.hub-picker li a.dir{flex:1;min-width:0;display:flex;align-items:center;gap:8px;color:#1c1917;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hub-picker li a.dir:hover{text-decoration:underline}
.hub-picker svg{flex:none;width:18px;height:18px;color:#c9962f}
.hub-steps{margin:14px 0 0;padding:16px 18px 16px 34px;border:1px solid #e7e5e4;border-radius:12px;background:#fff;font-size:12.5px;line-height:1.9;color:#57534e}
.hub-steps b{color:#1c1917}
</style>
<div class="adm-wrap">
  <?php admin_nav('hub-folders'); ?>
  <section>
    <p class="text-xs tracking-[.18em] text-stone-400">MY SPACE</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">공유 폴더</h1>
    <p class="mt-2 text-sm text-stone-500">NAS 폴더를 내 공간의 <a class="underline" href="../hub/modules/files/" target="_blank">파일</a> 메뉴에 연결합니다. 가족·지인은 <b>보기·내려받기만</b> 할 수 있고, 주인은 "업로드 허용"을 켠 폴더에 파일을 올리거나 새 폴더를 만들 수 있습니다. 웹에서 파일을 지우거나 바꾸는 기능은 없습니다.</p>
    <?php if ($msg): ?><div class="adm-note" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?=e($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="adm-note" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?=e($err)?></div><?php endif; ?>

    <?php if (!$shares): ?>
    <ol class="hub-steps">
      <li><b>DSM → 제어판 → 공유 폴더</b>에서 공개할 폴더(예: <code>photo</code>, <code>video</code>)를 고르고 <b>편집 → 권한</b>에서 <b>http</b> 사용자(시스템 내부 사용자)에게 <b>읽기 전용</b>을 줍니다. 쓰기는 주지 마세요.</li>
      <li><b>Web Station → 스크립트 언어 설정 → PHP 프로필 편집 → open_basedir</b> 에 그 폴더 경로(예: <code>/volume1/photo</code>)를 <code>:</code> 로 이어 붙입니다. (비워져 있으면 생략)</li>
      <li>아래 <b>폴더 고르기</b>에서 폴더를 찾아 "선택" → 이름과 볼 사람을 정해 추가합니다.</li>
    </ol>
    <?php endif; ?>

    <?php if ($edit): $checked = hub_share_user_ids((int)$edit['id']); ?>
      <h2 class="adm-h2">"<?=e($edit['name'])?>" 설정</h2>
      <form method="post" class="hub-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$edit['id']?>">
        <p style="font-size:12px;color:#78716c">경로 <code><?=e($edit['path'])?></code> (경로를 바꾸려면 연결 해제 후 다시 추가)</p>
        <div class="hub-grid">
          <label><span class="field-label">표시 이름</span><input class="admin-input" name="name" value="<?=e($edit['name'])?>" maxlength="40" required></label>
          <label><span class="field-label">설명</span><input class="admin-input" name="note" value="<?=e($edit['note'])?>" maxlength="120"></label>
          <label><span class="field-label">순서</span><input class="admin-input" type="number" name="sort" value="<?=(int)$edit['sort']?>"></label>
        </div>
        <label class="hide-toggle" style="justify-self:start"><input type="checkbox" name="active" value="1" <?=$edit['active'] ? 'checked' : ''?>> 내 공간에 표시 (끄면 모두에게 숨김)</label>
        <div><label class="hide-toggle" style="justify-self:start"><input type="checkbox" name="allow_upload" value="1" <?=!empty($edit['allow_upload']) ? 'checked' : ''?>> 주인 업로드 허용 (파일 올리기 · 새 폴더)</label>
          <?php $w = @is_writable((string)$edit['path']); ?><p class="field-help">가족·지인은 계속 보기·내려받기만 합니다. 켜려면 DSM → 공유 폴더 → 권한에서 <b>http</b> 에 <b>읽기/쓰기</b>가 필요합니다. 현재: <b class="<?=$w ? 'adm-ok' : 'adm-bad'?>"><?=$w ? '쓰기 가능' : '쓰기 권한 없음'?></b></p></div>
        <div><span class="field-label">볼 수 있는 사람 <small style="font-weight:400;color:#a8a29e">(주인은 항상 볼 수 있음)</small></span><?php $userBoxes($checked); ?></div>
        <div style="display:flex;gap:8px"><button class="adm-primary" style="height:40px">저장</button><a class="adm-btn" style="height:40px" href="hub-folders.php">취소</a></div>
      </form>
    <?php else: ?>
      <h2 class="adm-h2">폴더 고르기</h2>
      <div class="hub-picker">
        <div class="hub-picker-head">
          <span style="color:#a8a29e">NAS</span>
          <?php if ($browse === ''): ?>
            <?php foreach ($roots as $rt): ?><a href="?browse=<?=rawurlencode($rt)?>"><?=e($rt)?></a><?php endforeach; ?>
          <?php else: $acc = ''; foreach (explode('/', trim($browse, '/')) as $sg): $acc .= '/' . $sg; ?>
            › <a href="?browse=<?=rawurlencode($acc)?>"><?=e($sg)?></a>
          <?php endforeach; endif; ?>
        </div>
        <?php if ($browse === ''): ?>
          <p style="padding:14px;font-size:12.5px;color:#78716c">위의 볼륨을 눌러 폴더를 찾아 들어가세요.</p>
        <?php elseif ($subdirs === null): [$okB, $whyB] = hub_check_dir($browse); ?>
          <p style="padding:14px;font-size:12.5px;color:#b91c1c">이 위치를 읽을 수 없습니다. <?=e($whyB)?></p>
        <?php else: ?>
          <ul>
            <?php if (hub_norm_abs(dirname($browse)) !== $browse && hub_browse_allowed(dirname($browse))): ?><li><a class="dir" href="?browse=<?=rawurlencode(dirname($browse))?>">↑ 상위 폴더</a></li><?php endif; ?>
            <li style="background:#faf9f6"><span class="dir" style="flex:1;font-size:12px;color:#78716c">현재 폴더 <code><?=e($browse)?></code></span><a class="adm-btn" href="?browse=<?=rawurlencode($browse)?>&pick=<?=rawurlencode($browse)?>#add">이 폴더 선택</a></li>
            <?php foreach ($subdirs as $d): $full = $browse . '/' . $d; ?>
              <li><a class="dir" href="?browse=<?=rawurlencode($full)?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></svg><?=e($d)?></a>
              <?php if (!@is_readable($full)): ?><span style="font-size:11px;color:#b45309">읽기 권한 없음</span><?php endif; ?>
              <a class="adm-btn" href="?browse=<?=rawurlencode($browse)?>&pick=<?=rawurlencode($full)?>#add">선택</a></li>
            <?php endforeach; ?>
            <?php if (!$subdirs): ?><li style="color:#a8a29e">하위 폴더가 없습니다.</li><?php endif; ?>
          </ul>
        <?php endif; ?>
      </div>

      <h2 class="adm-h2" id="add">폴더 추가</h2>
      <form method="post" class="hub-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create">
        <div class="hub-grid">
          <label style="grid-column:1/-1"><span class="field-label">NAS 경로</span><input class="admin-input" name="path" required placeholder="/volume1/photo" value="<?=e($pick)?>"><span class="field-help">위에서 "선택"을 누르면 자동으로 채워집니다. 직접 입력해도 됩니다.</span></label>
          <label><span class="field-label">표시 이름</span><input class="admin-input" name="name" maxlength="40" placeholder="<?=e($pick !== '' ? basename($pick) : '예: 가족 사진')?>" value="<?=e($err ? ($_POST['name'] ?? '') : '')?>"></label>
          <label><span class="field-label">설명</span><input class="admin-input" name="note" maxlength="120" placeholder="선택" value="<?=e($err ? ($_POST['note'] ?? '') : '')?>"></label>
        </div>
        <?php if ($pick !== ''): [$okP, $whyP] = hub_check_dir($pick); ?><p style="font-size:12px" class="<?=$okP ? 'adm-ok' : 'adm-bad'?>"><span class="adm-dot <?=$okP ? 'ok' : 'bad'?>"></span><?=e($whyP)?></p><?php endif; ?>
        <div><span class="field-label">볼 수 있는 사람 <small style="font-weight:400;color:#a8a29e">(주인은 항상 볼 수 있음)</small></span><?php $userBoxes([]); ?></div>
        <label class="hide-toggle" style="justify-self:start"><input type="checkbox" name="allow_upload" value="1" <?=!empty($_POST['allow_upload']) ? 'checked' : ''?>> 주인 업로드 허용 <small style="color:#a8a29e">(http 에 쓰기 권한 필요)</small></label>
        <div><button class="adm-primary" style="height:40px">내 공간에 연결</button></div>
      </form>
    <?php endif; ?>

    <h2 class="adm-h2">연결된 폴더 <small><?=count($shares)?>개</small></h2>
    <?php if (!$shares): ?><div class="adm-note">아직 연결된 폴더가 없습니다.</div><?php else: ?>
    <table class="adm-table">
      <thead><tr><th>폴더</th><th>상태</th><th>볼 수 있는 사람</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($shares as $s): [$ok, $why] = hub_check_dir((string)$s['path']); $sid = (int)$s['id']; ?>
        <tr class="<?=$s['active'] ? '' : 'is-hidden'?>">
          <td><span class="adm-title"><?=e($s['name'])?><?php if (!$s['active']): ?><span class="adm-badge">숨김</span><?php endif; ?></span><code style="font-size:11px;color:#78716c"><?=e($s['path'])?></code></td>
          <td style="font-size:12px;max-width:260px;min-width:72px"><span class="adm-dot <?=$ok ? 'ok' : 'bad'?>"></span><?=e($ok ? '정상' : $why)?><?php if ($ok && !empty($s['allow_upload'])): $wr = @is_writable((string)$s['path']); ?><br><span class="<?=$wr ? 'adm-ok' : 'adm-warn'?>">⬆ 업로드 <?=$wr ? '가능' : '켜짐 · 쓰기 권한 없음'?></span><?php endif; ?></td>
          <td><span class="hub-chip" style="background:#1c1917;color:#fff">주인</span><?php foreach ($byShare[$sid] ?? [] as $n): ?><span class="hub-chip"><?=e($n)?></span><?php endforeach; ?></td>
          <td><div class="adm-actions">
            <a class="adm-btn" href="../hub/modules/files/index.php?s=<?=$sid?>" target="_blank">열기</a>
            <a class="adm-btn" href="?edit=<?=$sid?>">설정</a>
            <form method="post" onsubmit="return confirm('연결을 해제할까요? NAS 의 실제 파일은 지워지지 않습니다.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$sid?>"><button class="adm-btn danger" name="action" value="delete">연결 해제</button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <h2 class="adm-h2">서버 정보</h2>
    <div class="adm-note" style="line-height:1.9">
      웹 서버 실행 계정: <code><?=e($who ?: '알 수 없음')?></code> — 공유 폴더 권한은 이 계정(보통 <b>http</b>)에 주세요.<br>
      PHP open_basedir: <?=$ob === '' ? '<b>제한 없음</b>' : '<code style="word-break:break-all">' . e($ob) . '</code>'?><br>
      폴더 고르기 시작 위치: <code><?=e(implode(', ', $roots))?></code> (바꾸려면 <code>config.local.php</code> 에 <code>define('HUB_BROWSE_ROOTS', ['/volume1']);</code>)
    </div>
  </section>
</div>
<?php admin_foot();

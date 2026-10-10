<?php
// 내 공간 · 사용자 관리 — 가족·지인 계정 만들기, 임시 비밀번호, 사용 중지, 폴더별 권한, 다운로드·로그인 기록
require_once __DIR__ . '/auth.php'; require_admin();
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/hub.php';
require_once __DIR__ . '/style.php';

if (!hub_available()) { admin_head('사용자 관리'); echo '<p>PHP SQLite(pdo_sqlite) 확장이 필요합니다.</p>'; admin_foot(); exit; }

$msg = ''; $err = ''; $reveal = null;   // $reveal: 한 번만 보여 줄 [아이디, 임시 비밀번호]
$shares = hub_shares_all();
$shareIds = array_map('intval', array_column($shares, 'id'));
$pickShares = fn($raw) => array_values(array_intersect($shareIds, array_map('intval', (array)$raw)));
function hub_set_user_shares(int $uid, array $ids): void {
    $db = hub_db(); $db->beginTransaction();
    $db->prepare('DELETE FROM share_access WHERE user_id = ?')->execute([$uid]);
    $ins = $db->prepare('INSERT OR IGNORE INTO share_access(share_id,user_id) VALUES(?,?)');
    foreach ($ids as $sid) $ins->execute([(int)$sid, $uid]);
    $db->commit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청입니다. 새로고침 후 다시 시도하세요.'); }
    $act = (string)($_POST['action'] ?? ''); $id = (int)($_POST['id'] ?? 0);
    try {
        if ($act === 'create') {
            $un = trim((string)($_POST['username'] ?? '')); $dn = trim((string)($_POST['display_name'] ?? ''));
            $admin = admin_record();
            if (!hub_valid_username($un)) $err = '아이디는 영문·숫자·._- 3~32자로 입력하세요.';
            elseif (hub_user_by_name($un)) $err = '이미 있는 아이디입니다.';
            elseif ($admin && strcasecmp((string)$admin['username'], $un) === 0) $err = '관리자 아이디와 같은 아이디는 쓸 수 없습니다.';
            else {
                $pw = hub_temp_password();
                hub_db()->prepare('INSERT INTO users(username,display_name,password_hash,note,created_at) VALUES(?,?,?,?,?)')
                    ->execute([$un, mb_substr($dn, 0, 40), password_hash($pw, PASSWORD_DEFAULT), mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120), date('c')]);
                $nid = (int)hub_db()->lastInsertId();
                hub_set_user_shares($nid, $pickShares($_POST['shares'] ?? []));
                $reveal = [$un, $pw]; $msg = '계정을 만들었습니다.';
            }
        } elseif ($act === 'save' && ($usr = hub_user($id))) {
            hub_db()->prepare('UPDATE users SET display_name = ?, note = ? WHERE id = ?')
                ->execute([mb_substr(trim((string)($_POST['display_name'] ?? '')), 0, 40), mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120), $id]);
            hub_set_user_shares($id, $pickShares($_POST['shares'] ?? []));
            $msg = $usr['username'] . ' 님의 정보와 폴더 권한을 저장했습니다.';
        } elseif ($act === 'reset' && ($usr = hub_user($id))) {
            $pw = hub_temp_password();
            hub_db()->prepare('UPDATE users SET password_hash = ?, must_change = 1, pw_ver = pw_ver + 1 WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
            $reveal = [$usr['username'], $pw]; $msg = '임시 비밀번호를 새로 만들었습니다. 이 사람의 기존 로그인은 모두 끊어졌습니다.';
        } elseif ($act === 'toggle' && ($usr = hub_user($id))) {
            hub_db()->prepare('UPDATE users SET active = 1 - active, pw_ver = pw_ver + 1 WHERE id = ?')->execute([$id]);
            $msg = $usr['username'] . ' 님을 ' . ($usr['active'] ? '사용 중지했습니다(즉시 로그아웃).' : '다시 사용하도록 했습니다.');
        } elseif ($act === 'delete' && ($usr = hub_user($id))) {
            hub_db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            $msg = $usr['username'] . ' 계정을 삭제했습니다.';
        }
    } catch (Throwable $e) {
        $err = '저장하지 못했습니다: ' . $e->getMessage() . ' — private 폴더에 http 사용자 쓰기 권한이 있는지 확인하세요.';
    }
}

$users = hub_users();
$editId = (int)($_GET['edit'] ?? 0); $edit = $editId ? hub_user($editId) : null;
$byUser = [];
foreach (hub_db()->query('SELECT a.user_id, s.name FROM share_access a JOIN shares s ON s.id = a.share_id ORDER BY s.sort, s.name')->fetchAll() as $row) $byUser[(int)$row['user_id']][] = $row['name'];
$downloads = hub_db()->query('SELECT d.*, s.name AS share FROM downloads d LEFT JOIN shares s ON s.id = d.share_id ORDER BY d.id DESC LIMIT 40')->fetchAll();
$logins = hub_db()->query('SELECT * FROM logins ORDER BY id DESC LIMIT 25')->fetchAll();
$resLabel = ['ok' => ['성공', 'ok'], 'fail' => ['실패', 'bad'], 'locked' => ['잠금 중', 'warn'], 'disabled' => ['중지된 계정', 'warn']];

$shareBoxes = function (array $checked) use ($shares) {
    if (!$shares) { echo '<p class="field-help">아직 공유 폴더가 없습니다. <a class="underline" href="hub-folders.php">공유 폴더 설정</a>에서 먼저 추가하세요.</p>'; return; }
    echo '<div class="hub-checks">';
    foreach ($shares as $s) {
        $on = in_array((int)$s['id'], $checked, true);
        echo '<label class="hub-check"><input type="checkbox" name="shares[]" value="' . (int)$s['id'] . '"' . ($on ? ' checked' : '') . '><span>' . e($s['name']) . (!$s['active'] ? ' <small>(꺼짐)</small>' : '') . '</span></label>';
    }
    echo '</div>';
};

admin_head('사용자 관리'); ?>
<style>
.hub-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.hub-checks{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.hub-check{display:inline-flex;cursor:pointer}.hub-check input{position:absolute;opacity:0}
.hub-check span{padding:7px 12px;border:1px solid #d6d3d1;border-radius:999px;background:#fff;font-size:12px;color:#57534e}
.hub-check input:checked+span{border-color:#1c1917;background:#1c1917;color:#fff}
.hub-check input:focus-visible+span{outline:2px solid #78716c;outline-offset:2px}
.hub-chip{display:inline-block;margin:2px 4px 2px 0;padding:1px 8px;border-radius:999px;background:#f0efed;font-size:11px;color:#57534e}
.hub-secret{margin-top:16px;padding:16px 18px;border:1px solid #fde68a;border-radius:12px;background:#fffbeb;color:#78350f;font-size:13px;line-height:1.8}
.hub-secret code{display:inline-block;margin:0 4px;padding:3px 10px;border-radius:6px;background:#fff;border:1px solid #fde68a;font-size:16px;letter-spacing:.06em;color:#1c1917}
.hub-form{margin-top:14px;padding:20px;border:1px solid #e7e5e4;border-radius:14px;background:#fff;display:grid;gap:14px}
</style>
<div class="adm-wrap">
  <?php admin_nav('hub-users'); ?>
  <section>
    <p class="text-xs tracking-[.18em] text-stone-400">MY SPACE</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">사용자 관리</h1>
    <p class="mt-2 text-sm text-stone-500">가족·지인 계정을 만들고 어떤 폴더를 볼 수 있는지 정합니다. 이 사람들은 <a class="underline" href="../hub/login.php" target="_blank">/hub/login.php</a> 에서 로그인하며, 관리자 화면에는 들어올 수 없습니다.</p>
    <?php if ($msg): ?><div class="adm-note" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?=e($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="adm-note" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?=e($err)?></div><?php endif; ?>
    <?php if ($reveal): ?>
      <div class="hub-secret">🔑 <b><?=e($reveal[0])?></b> 님의 임시 비밀번호 <code><?=e($reveal[1])?></code><br>
      <b>지금 한 번만 보입니다.</b> 메신저 등으로 전달하세요. 처음 로그인할 때 본인 비밀번호로 바꾸도록 안내됩니다.<br>
      로그인 주소: <code style="font-size:12px;letter-spacing:0"><?=e(abs_hub_login())?></code></div>
    <?php endif; ?>

    <?php if ($edit): $checked = hub_user_share_ids((int)$edit['id']); ?>
      <h2 class="adm-h2"><?=e($edit['username'])?> 님 정보 · 폴더 권한</h2>
      <form method="post" class="hub-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$edit['id']?>">
        <div class="hub-grid">
          <label><span class="field-label">이름(표시용)</span><input class="admin-input" name="display_name" value="<?=e($edit['display_name'])?>" maxlength="40" placeholder="예: 엄마"></label>
          <label><span class="field-label">메모</span><input class="admin-input" name="note" value="<?=e($edit['note'])?>" maxlength="120" placeholder="예: 가족"></label>
        </div>
        <div><span class="field-label">볼 수 있는 폴더</span><?php $shareBoxes($checked); ?></div>
        <div style="display:flex;gap:8px"><button class="adm-primary" style="height:40px">저장</button><a class="adm-btn" style="height:40px" href="hub-users.php">취소</a></div>
      </form>
    <?php else: ?>
      <h2 class="adm-h2">새 사용자 만들기</h2>
      <form method="post" class="hub-form" autocomplete="off">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create">
        <div class="hub-grid">
          <label><span class="field-label">아이디</span><input class="admin-input" name="username" required pattern="[A-Za-z0-9._\-]{3,32}" maxlength="32" placeholder="영문·숫자 3~32자" value="<?=e($err ? ($_POST['username'] ?? '') : '')?>"></label>
          <label><span class="field-label">이름(표시용)</span><input class="admin-input" name="display_name" maxlength="40" placeholder="예: 엄마" value="<?=e($err ? ($_POST['display_name'] ?? '') : '')?>"></label>
          <label><span class="field-label">메모</span><input class="admin-input" name="note" maxlength="120" placeholder="선택" value="<?=e($err ? ($_POST['note'] ?? '') : '')?>"></label>
        </div>
        <div><span class="field-label">볼 수 있는 폴더</span><?php $shareBoxes([]); ?></div>
        <div><button class="adm-primary" style="height:40px">계정 만들기 · 임시 비밀번호 발급</button></div>
      </form>
    <?php endif; ?>

    <h2 class="adm-h2">사용자 <small><?=count($users)?>명</small></h2>
    <?php if (!$users): ?><div class="adm-note">아직 만든 사용자가 없습니다.</div><?php else: ?>
    <table class="adm-table">
      <thead><tr><th>사용자</th><th>볼 수 있는 폴더</th><th>마지막 로그인</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $x): $xid = (int)$x['id']; ?>
        <tr class="<?=$x['active'] ? '' : 'is-hidden'?>">
          <td><span class="adm-title"><?=e($x['display_name'] ?: $x['username'])?><?php if (!$x['active']): ?><span class="adm-badge">중지</span><?php elseif ($x['must_change']): ?><span class="adm-badge draft">첫 로그인 전</span><?php endif; ?></span><small class="text-stone-400">@<?=e($x['username'])?><?=$x['note'] !== '' ? ' · ' . e($x['note']) : ''?></small></td>
          <td><?php foreach ($byUser[$xid] ?? [] as $n): ?><span class="hub-chip"><?=e($n)?></span><?php endforeach; ?><?=empty($byUser[$xid]) ? '<span class="text-stone-400" style="font-size:12px">없음</span>' : ''?></td>
          <td style="font-size:12px;color:#78716c;white-space:nowrap"><?=$x['last_login'] ? e(date('Y-m-d H:i', strtotime($x['last_login']))) : '—'?></td>
          <td><div class="adm-actions">
            <a class="adm-btn" href="?edit=<?=$xid?>">권한·정보</a>
            <form method="post" onsubmit="return confirm('임시 비밀번호를 새로 만들까요? 이 사람의 기존 로그인은 끊어집니다.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$xid?>"><button class="adm-btn" name="action" value="reset">비밀번호 재발급</button></form>
            <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$xid?>"><button class="adm-btn" name="action" value="toggle"><?=$x['active'] ? '사용 중지' : '다시 사용'?></button></form>
            <form method="post" onsubmit="return confirm('<?=e($x['username'])?> 계정을 삭제할까요? (NAS 파일은 그대로입니다)')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$xid?>"><button class="adm-btn danger" name="action" value="delete">삭제</button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <h2 class="adm-h2">최근 내려받기 · 올리기 <small>최근 40건 · 내려받기는 처음부터 받은 것만 기록</small></h2>
    <?php if (!$downloads): ?><div class="adm-note">아직 기록이 없습니다.</div><?php else: ?>
    <table class="adm-table"><thead><tr><th>시각</th><th>사용자</th><th>파일</th><th>크기</th></tr></thead><tbody>
      <?php foreach ($downloads as $d): ?><tr><td style="font-size:12px;white-space:nowrap"><?=e(date('m-d H:i', (int)$d['t']))?></td><td style="font-size:12px"><?=e($d['user'])?></td><td style="font-size:12px;word-break:break-all"><?=($d['kind'] ?? '') === 'up' ? '<span class="adm-badge scheduled" style="margin:0 6px 0 0">올림</span>' : ''?><?=e($d['path'])?></td><td style="font-size:12px;white-space:nowrap"><?=e(hub_fmt_size((float)$d['bytes']))?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>

    <h2 class="adm-h2">사용자 로그인 기록 <small>최근 25건</small></h2>
    <?php if (!$logins): ?><div class="adm-note">아직 기록이 없습니다.</div><?php else: ?>
    <table class="adm-table"><thead><tr><th>시각</th><th>아이디</th><th>결과</th><th>기기 · IP</th></tr></thead><tbody>
      <?php foreach ($logins as $l): [$lb, $cl] = $resLabel[$l['result']] ?? [$l['result'], '']; ?><tr><td style="font-size:12px;white-space:nowrap"><?=e(date('m-d H:i', (int)$l['t']))?></td><td style="font-size:12px"><?=e($l['user'])?></td><td style="font-size:12px"><span class="adm-dot <?=$cl?>"></span><?=e($lb)?></td><td style="font-size:12px;color:#78716c"><?=e(login_ua_label((string)$l['ua']))?> · <?=e($l['ip'])?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </section>
</div>
<?php admin_foot();

function abs_hub_login(): string {
    if (SITE_URL !== '') return rtrim(SITE_URL, '/') . '/hub/login.php';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x'))), '/');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/hub/login.php';
}

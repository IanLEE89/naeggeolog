<?php
// 보안: 2단계 인증(TOTP) 켜기/끄기 · 복구 코드 · 최근 로그인 기록
require_once __DIR__.'/auth.php'; require_admin();
require_once __DIR__.'/totp.php';
require_once __DIR__.'/../includes/data.php';
require_once __DIR__.'/style.php';

$r = admin_record(); if (!$r) { http_response_code(500); exit('관리자 계정 파일을 찾을 수 없습니다.'); }
$on = !empty($r['totp_secret']); $msg = ''; $err = ''; $showCodes = null; $setup = null;
$issuer = SITE_TITLE; $account = (string)($r['username'] ?? 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['act'] ?? '');
    if (!csrf_check($_POST['csrf'] ?? '')) { $err = '잘못된 요청입니다. 새로고침 후 다시 시도해주세요.'; }
    elseif ($act === 'begin' && !$on) { $_SESSION['totp_setup'] = totp_new_secret(); }
    elseif ($act === 'cancel') { unset($_SESSION['totp_setup']); }
    elseif ($act === 'confirm' && !$on && !empty($_SESSION['totp_setup'])) {
        $c = totp_verify((string)$_SESSION['totp_setup'], (string)($_POST['code'] ?? ''));
        if ($c === null) $err = '코드가 맞지 않습니다. 앱에 표시된 현재 6자리 숫자를 입력해주세요.';
        else {
            [$plain, $hash] = totp_new_recovery();
            $r['totp_secret'] = $_SESSION['totp_setup']; $r['totp_recovery'] = $hash; $r['totp_last'] = $c; $r['totp_at'] = date('c');
            if (admin_save_record($r)) { unset($_SESSION['totp_setup']); $on = true; $showCodes = $plain; $msg = '2단계 인증을 켰습니다.'; }
            else $err = '설정을 저장하지 못했습니다. 계정 파일(admin.json)의 쓰기 권한을 확인하세요.';
        }
    }
    elseif (in_array($act, ['disable', 'regen'], true) && $on) {
        if (!password_verify((string)($_POST['password'] ?? ''), (string)$r['password_hash'])) $err = '비밀번호가 올바르지 않습니다.';
        elseif ($act === 'disable') {
            foreach (['totp_secret', 'totp_recovery', 'totp_last', 'totp_at'] as $k) unset($r[$k]);
            if (admin_save_record($r)) { $on = false; $msg = '2단계 인증을 껐습니다.'; } else $err = '저장하지 못했습니다.';
        } else {
            [$plain, $hash] = totp_new_recovery(); $r['totp_recovery'] = $hash;
            if (admin_save_record($r)) { $showCodes = $plain; $msg = '복구 코드를 새로 만들었습니다. 이전 코드는 더 이상 쓸 수 없습니다.'; } else $err = '저장하지 못했습니다.';
        }
    }
}
if (!$on && !empty($_SESSION['totp_setup'])) $setup = (string)$_SESSION['totp_setup'];
$log = array_slice(login_log_load(), 0, 25);
$left = count((array)($r['totp_recovery'] ?? []));
$lbl = ['ok' => ['성공', 'adm-ok'], 'fail_pw' => ['비밀번호 실패', 'adm-bad'], 'fail_2fa' => ['인증 코드 실패', 'adm-bad'], 'locked' => ['잠금 중 시도', 'adm-warn']];

admin_head('보안 · 2단계 인증'); ?>
<div class="adm-wrap">
  <?php admin_nav('sec'); ?>
  <section>
    <p class="text-xs tracking-[.18em] text-stone-400">SECURITY</p>
    <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">보안 · 2단계 인증</h1>
    <p class="mt-2 text-sm text-stone-500">비밀번호가 유출되어도 휴대폰의 인증 앱 코드가 없으면 로그인할 수 없게 만듭니다.</p>
    <?php if ($msg): ?><div class="adm-note" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?=e($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="adm-note" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?=e($err)?></div><?php endif; ?>

    <?php if ($showCodes): ?>
    <div class="adm-note" style="border-color:#fde68a;background:#fffbeb;color:#78350f;line-height:1.9">
      <b>복구 코드 — 지금 한 번만 보여드립니다.</b> 휴대폰을 잃어버렸을 때 인증 코드 대신 한 번씩 쓸 수 있습니다. 안전한 곳(비밀번호 관리자·인쇄)에 보관하세요.
      <div style="margin-top:10px;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:6px;font-family:ui-monospace,Consolas,monospace;font-size:14px;color:#1c1917"><?php foreach ($showCodes as $c): ?><span style="background:#fff;border:1px solid #fde68a;border-radius:6px;padding:6px 10px"><?=e($c)?></span><?php endforeach; ?></div>
      <button type="button" class="adm-btn" style="margin-top:10px" onclick="navigator.clipboard.writeText(<?=e(json_encode(implode("\n", $showCodes)))?>).then(function(){this.textContent='복사됨'}.bind(this))">모두 복사</button>
    </div>
    <?php endif; ?>

    <div class="adm-chart" style="margin-top:18px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span class="adm-dot <?=$on ? 'ok' : 'warn'?>"></span><b style="font-size:15px">2단계 인증 <?=$on ? '사용 중' : '꺼져 있음'?></b>
        <?php if ($on): ?><span style="font-size:12px;color:#78716c">남은 복구 코드 <?=$left?>개<?=$left <= 2 ? ' — 새로 만들어 두세요' : ''?></span><?php endif; ?>
      </div>

      <?php if (!$on && !$setup): ?>
        <p style="margin:12px 0;font-size:13px;color:#57534e;line-height:1.8">Google Authenticator · Microsoft Authenticator · Authy 같은 앱이 필요합니다. 설정에는 1분이면 충분해요.</p>
        <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="begin"><button class="adm-primary" style="height:42px">2단계 인증 설정하기</button></form>
      <?php elseif ($setup): ?>
        <ol style="margin:14px 0 0;padding-left:20px;font-size:13px;line-height:1.9;color:#44403c">
          <li>휴대폰 인증 앱에서 <b>+</b> → <b>QR 코드 스캔</b>으로 아래 QR 을 읽습니다.</li>
          <li>앱에 표시된 <b>6자리 코드</b>를 아래에 입력하고 확인을 누릅니다.</li>
        </ol>
        <div style="display:flex;gap:22px;flex-wrap:wrap;align-items:center;margin:16px 0">
          <div id="qr" style="width:176px;height:176px;background:#fff;border:1px solid #e7e5e4;border-radius:10px;padding:8px"></div>
          <div style="font-size:12px;color:#78716c;max-width:340px;line-height:1.8">QR 이 안 읽히면 앱에서 <b>키 직접 입력</b>을 고르고 아래 키를 넣으세요(시간 기반).<br>
            <code id="key" style="display:block;margin-top:6px;padding:8px 10px;background:#f5f5f4;border-radius:6px;font-size:13px;letter-spacing:.08em;word-break:break-all;color:#1c1917"><?=e(trim(chunk_split($setup, 4, ' ')))?></code></div>
        </div>
        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="confirm">
          <input class="admin-input" style="width:170px;text-align:center;letter-spacing:.25em;font-size:16px" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" required>
          <button class="adm-primary" style="height:46px">확인하고 켜기</button>
          <button class="adm-btn" style="height:46px" name="act" value="cancel" formnovalidate>취소</button>
        </form>
        <script src="vendor/qrcode.js"></script>
        <script>
          (function(){ var uri=<?=json_encode(totp_uri($setup, $account, $issuer), JSON_UNESCAPED_SLASHES)?>;
            try{ var q=qrcode(0,'M'); q.addData(uri); q.make(); document.getElementById('qr').innerHTML=q.createSvgTag({cellSize:4,margin:0,scalable:true}); var s=document.querySelector('#qr svg'); if(s){s.style.width='100%';s.style.height='100%';} }
            catch(e){ document.getElementById('qr').textContent='QR 을 만들 수 없습니다 — 아래 키를 직접 입력하세요.'; } })();
        </script>
      <?php else: ?>
        <form method="post" style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center" onsubmit="return !(event.submitter && event.submitter.value==='disable') || confirm('2단계 인증을 끌까요? 보안 수준이 낮아집니다.')">
          <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
          <input class="admin-input" style="width:220px" type="password" name="password" placeholder="현재 비밀번호" autocomplete="current-password" required>
          <button class="adm-btn" style="height:46px" name="act" value="regen">복구 코드 새로 만들기</button>
          <button class="adm-btn danger" style="height:46px" name="act" value="disable">2단계 인증 끄기</button>
        </form>
      <?php endif; ?>
    </div>

    <h2 class="adm-h2">최근 로그인 기록 <small>최근 <?=count($log)?>건 · 이 기기가 아닌 접속이나 실패가 많다면 비밀번호를 바꾸세요</small></h2>
    <table class="adm-table"><thead><tr><th>시각</th><th>결과</th><th>접속 위치(IP)</th><th>기기</th></tr></thead><tbody>
      <?php foreach ($log as $e_): [$t_, $c_] = $lbl[$e_['r']] ?? [$e_['r'], '']; ?>
      <tr><td style="white-space:nowrap;color:#78716c"><?=e(date('Y-m-d H:i', (int)$e_['t']))?></td><td class="<?=$c_?>" style="white-space:nowrap;font-weight:600"><?=e($t_)?></td><td><?=e($e_['ip'])?></td><td style="color:#78716c"><?=e(login_ua_label((string)$e_['ua']))?></td></tr>
      <?php endforeach; ?>
      <?php if (!$log): ?><tr><td colspan="4" style="padding:30px;text-align:center;color:#a8a29e">아직 기록이 없습니다. 다음 로그인부터 쌓입니다.</td></tr><?php endif; ?>
    </tbody></table>
  </section>
</div>
<?php admin_foot();

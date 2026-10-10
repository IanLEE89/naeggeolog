<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/totp.php';
// 아직 관리자 계정이 없으면(처음 설치) 설치 마법사로
if(!admin_record()) admin_redirect('setup.php');
// 내 공간 로그인 화면에서 넘어왔으면(next=hub) 로그인 후 내 공간으로. 그냥 열면 기억을 지움
if($_SERVER['REQUEST_METHOD']==='GET' && !isset($_GET['cancel'])){ if(($_GET['next']??'')==='hub') $_SESSION['after_login']='hub'; else unset($_SESSION['after_login']); }
if(admin_logged_in()){ $__to=($_SESSION['after_login']??'')==='hub'?'../hub/':'index.php'; unset($_SESSION['after_login']); admin_redirect($__to); }
if(isset($_GET['cancel'])){ unset($_SESSION['pending_2fa'],$_SESSION['pending_2fa_t']); admin_redirect('login.php'); }
$error='';
$pending = !empty($_SESSION['pending_2fa']) && (time()-(int)($_SESSION['pending_2fa_t']??0)) < 300;
if(!$pending) unset($_SESSION['pending_2fa'],$_SESSION['pending_2fa_t']);

function finish_login(string $u, array $r): void {
  $sum = login_prev_summary();                    // 이번 기록을 남기기 전에 이전 로그인/실패 횟수를 읽는다
  login_register_success(); login_log('ok', $u);
  session_regenerate_id(true);
  unset($_SESSION['pending_2fa'],$_SESSION['pending_2fa_t']);
  $_SESSION['admin_logged_in']=true; $_SESSION['admin_user']=$u; $_SESSION['login_notice']=$sum;
  $toHub = ($_SESSION['after_login']??'')==='hub'; unset($_SESSION['after_login']);
  admin_redirect(!empty($r['must_change'])?'change-password.php':($toHub?'../hub/':'index.php'));
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $wait = login_locked_seconds();
  $r=admin_record();
  if($wait>0){
    $error='로그인 시도가 너무 많습니다. '.ceil($wait/60).'분 후 다시 시도해주세요.';
    login_log('locked', trim((string)($_POST['username']??'')));
  } elseif($pending && ($_POST['step']??'')==='2fa' && $r){
    $u=(string)$_SESSION['pending_2fa']; $in=trim((string)($_POST['code']??''));
    $rec = preg_match('/^[0-9a-f]{5}-[0-9a-f]{5}$/i',$in);
    $good=false;
    if($rec){
      $left=totp_use_recovery((array)($r['totp_recovery']??[]),$in);
      if($left!==null){ $r['totp_recovery']=$left; admin_save_record($r); $good=true; }
    } else {
      $c=totp_verify((string)($r['totp_secret']??''),$in,(int)($r['totp_last']??0));
      if($c!==null){ $r['totp_last']=$c; admin_save_record($r); $good=true; }
    }
    if($good) finish_login($u,$r);
    login_register_fail(); login_log('fail_2fa',$u);
    $error='인증 코드가 올바르지 않습니다.';
    if(login_locked_seconds()>0){ unset($_SESSION['pending_2fa'],$_SESSION['pending_2fa_t']); $pending=false; }
  } else {
    $u=trim((string)($_POST['username']??'')); $p=(string)($_POST['password']??'');
    $okUser = $r && hash_equals((string)($r['username']??''),$u);
    // 아이디가 틀려도 비밀번호 검증을 수행해 응답 시간 차이로 아이디를 추측하지 못하게 함
    $okPass = password_verify($p,(string)($r['password_hash']??'$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin'));
    if($okUser && $okPass){
      if(!empty($r['totp_secret'])){ session_regenerate_id(true); $_SESSION['pending_2fa']=$u; $_SESSION['pending_2fa_t']=time(); $pending=true; }
      else finish_login($u,$r);
    } else {
      login_register_fail(); login_log('fail_pw',$u);
      $error='아이디 또는 비밀번호가 올바르지 않습니다.';
    }
  }
}
require __DIR__.'/style.php'; admin_head('로그인'); $inp='w-full border border-stone-300 bg-white px-3 py-3 text-sm outline-none focus:border-stone-700'; ?>
<div class="mx-auto max-w-sm pt-16"><div class="mb-8 text-center"><p class="text-xs tracking-[.18em] text-stone-400"><?=htmlspecialchars(mb_strtoupper(SITE_TITLE),ENT_QUOTES,'UTF-8')?></p><h1 class="mt-3 text-2xl font-serif tracking-tight">관리자 로그인</h1></div>
<?php if($pending): ?>
<form method="post" class="space-y-4 border-y border-stone-200 py-7"><input type="hidden" name="step" value="2fa">
  <p class="text-sm leading-7 text-stone-600">인증 앱에 표시된 <b>6자리 코드</b>를 입력하세요.<br><span class="text-xs text-stone-400">휴대폰을 잃어버렸다면 복구 코드(예: 1a2b3-c4d5e)를 입력할 수 있습니다.</span></p>
  <div><input name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required maxlength="11" placeholder="123456" class="<?=$inp?> text-center text-lg tracking-[.3em]"></div>
  <?php if($error):?><p class="text-xs text-red-700"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif;?>
  <button class="w-full bg-stone-900 px-4 py-3 text-sm text-white hover:bg-stone-700">확인</button>
  <p class="text-center text-xs"><a class="text-stone-400 underline" href="login.php?cancel=1">처음으로</a></p>
</form>
<?php else: ?>
<form method="post" class="space-y-4 border-y border-stone-200 py-7"><input type="hidden" name="step" value="pw"><div><label class="mb-2 block text-xs text-stone-500">아이디</label><input name="username" autocomplete="username" required class="<?=$inp?>"></div><div><label class="mb-2 block text-xs text-stone-500">비밀번호</label><input type="password" name="password" autocomplete="current-password" required class="<?=$inp?>"></div><?php if($error):?><p class="text-xs text-red-700"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif;?><button class="w-full bg-stone-900 px-4 py-3 text-sm text-white hover:bg-stone-700">로그인</button></form>
<?php endif; ?></div><?php admin_foot(); ?>

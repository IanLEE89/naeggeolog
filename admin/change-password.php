<?php
require_once __DIR__.'/auth.php'; require_admin();
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$r=admin_record();
if(!$r){ http_response_code(500); exit('관리자 계정 파일을 찾을 수 없습니다.'); }
$first=!empty($r['must_change']);   // 초기 비밀번호 상태
$error=''; $ok=false; $file=admin_file();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $cur=(string)($_POST['current']??''); $a=(string)($_POST['password']??''); $b=(string)($_POST['password2']??'');
  $newUser=trim((string)($_POST['username']??$r['username']));
  if(!csrf_check($_POST['csrf']??'')) $error='잘못된 요청입니다. 새로고침 후 다시 시도해주세요.';
  elseif(!password_verify($cur,(string)$r['password_hash'])) $error='현재 비밀번호가 올바르지 않습니다.';
  elseif(!preg_match('/^[A-Za-z0-9._-]{4,32}$/',$newUser)) $error='아이디는 영문·숫자·._- 4~32자로 입력해주세요.';
  elseif(mb_strlen($a)<10) $error='새 비밀번호는 10자 이상으로 입력해주세요.';
  elseif($a!==$b) $error='두 비밀번호가 일치하지 않습니다.';
  elseif(password_verify($a,(string)$r['password_hash'])) $error='현재와 다른 비밀번호를 사용해주세요.';
  elseif($first && $newUser==='admin') $error='기본 아이디(admin)는 추측하기 쉽습니다. 다른 아이디로 바꿔주세요.';
  else{
    $r['username']=$newUser;
    $r['password_hash']=password_hash($a,PASSWORD_DEFAULT);
    $r['must_change']=false;
    $r['changed_at']=date('c');
    if(admin_save_record($r)){
      // 저장 결과를 실제로 다시 읽어 확인
      $chk=admin_record();
      if($chk && password_verify($a,(string)$chk['password_hash'])){
        $ok=true; $_SESSION['admin_user']=$newUser; session_regenerate_id(true);
      } else $error='저장 후 확인에 실패했습니다. 파일 권한을 확인해주세요.';
    } else {
      $error='비밀번호 파일을 저장하지 못했습니다. Synology에서 아래 파일/폴더에 웹 서버 계정(http)의 쓰기 권한을 주세요: '.$file;
    }
  }
}
require __DIR__.'/style.php'; admin_head('계정 설정'); ?>
<div class="mx-auto max-w-sm pt-12"><h1 class="text-2xl font-serif"><?= $first?'관리자 계정 설정':'아이디·비밀번호 변경' ?></h1>
<?php if($first):?><p class="mt-3 text-sm leading-7 text-stone-500">첫 로그인입니다. 계속 사용하기 전에 아이디와 비밀번호를 새로 설정해주세요.</p><?php endif;?>
<?php if($ok):?><div class="mt-7 border-y border-stone-200 py-5 text-sm">변경되었습니다.</div><a class="mt-5 inline-block bg-stone-900 px-5 py-3 text-sm text-white" href="index.php">관리자 화면으로</a>
<?php else:?>
<form method="post" class="mt-8 space-y-4" autocomplete="off"><input type="hidden" name="csrf" value="<?=$h(csrf_token())?>">
<label class="block"><span class="mb-2 block text-xs text-stone-500">현재 비밀번호</span><input type="password" name="current" autocomplete="current-password" required class="w-full border border-stone-300 bg-white px-3 py-3 text-sm"></label>
<label class="block"><span class="mb-2 block text-xs text-stone-500">아이디</span><input name="username" value="<?=$h($_POST['username']??$r['username'])?>" autocomplete="username" required class="w-full border border-stone-300 bg-white px-3 py-3 text-sm"></label>
<label class="block"><span class="mb-2 block text-xs text-stone-500">새 비밀번호 (10자 이상)</span><input type="password" name="password" autocomplete="new-password" required class="w-full border border-stone-300 bg-white px-3 py-3 text-sm"></label>
<label class="block"><span class="mb-2 block text-xs text-stone-500">새 비밀번호 확인</span><input type="password" name="password2" autocomplete="new-password" required class="w-full border border-stone-300 bg-white px-3 py-3 text-sm"></label>
<?php if($error):?><p class="text-xs leading-6 text-red-700"><?=$h($error)?></p><?php endif;?>
<button class="w-full bg-stone-900 px-4 py-3 text-sm text-white">저장</button></form>
<?php if(!$first):?><p class="mt-6"><a class="text-xs text-stone-400 hover:text-stone-900" href="index.php">← 관리자 홈</a></p><?php endif;?>
<?php endif;?></div><?php admin_foot(); ?>

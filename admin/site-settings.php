<?php
require_once __DIR__.'/auth.php';
require_admin();
require_once __DIR__.'/../site.php';
require_once __DIR__.'/style.php';

$site = $TRAVEL_SITE;
function site_admin_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function site_prev($v): string { $v = trim((string)$v); return $v === '' ? '' : (preg_match('~^(https?:)?//|^/~', $v) ? $v : '../' . implode('/', array_map('rawurlencode', explode('/', $v)))); }
admin_head('홈페이지 설정');
?>
<div class="adm-wrap">
  <?php admin_nav('site'); ?>
  <section>
    <div>
      <p class="text-xs tracking-[.18em] text-stone-400">SITE SETTINGS</p>
      <h1 class="admin-serif mt-2 text-3xl tracking-tight text-stone-900">홈페이지 설정</h1>
      <p class="mt-2 text-sm text-stone-500">첫 화면의 대표 이미지·문구와 하단 프로필을 관리합니다.</p>
    </div>
    <?php if (isset($_GET['saved'])): ?><div class="adm-note">사이트 설정을 저장했습니다.</div><?php endif; ?>
    <?php if (($_GET['error'] ?? '') === 'email'): ?><div class="adm-note" style="border-color:#fecaca;color:#b91c1c">이메일 형식이 올바르지 않습니다.</div><?php endif; ?>
    <?php if (($_GET['error'] ?? '') === 'maps'): ?><div class="adm-note" style="border-color:#fecaca;color:#b91c1c">Google Maps API 키 또는 지도 ID 형식이 올바르지 않습니다.</div><?php endif; ?>
    <?php if (($_GET['error'] ?? '') === 'mapsave'): ?><div class="adm-note" style="border-color:#fecaca;color:#b91c1c">Google Maps 설정 파일을 저장하지 못했습니다. private 폴더 쓰기 권한을 확인하세요.</div><?php endif; ?>

    <form class="mt-7 grid gap-6 xl:grid-cols-2" method="post" action="site-settings-save.php" id="siteForm">
      <input type="hidden" name="csrf" id="csrf" value="<?=site_admin_e(csrf_token())?>">
      <section class="section-card p-5 sm:p-6">
        <h2 class="section-heading">사이트 대표 이미지 설정</h2>
        <p class="section-description">홈페이지 맨 위에 크게 보이는 사진과 문구입니다. 사진을 올리지 않으면 가장 최근 여행의 대표 사진이 사용됩니다.</p>
        <div class="mt-6 grid gap-5">
          <label><span class="field-label">사이트 이름</span><input class="admin-input mt-2" name="site_name" maxlength="60" required value="<?=site_admin_e($site['site_name'])?>"><span class="field-help" style="display:block">브라우저 탭·검색 결과·로고 자리(로고 이미지가 없을 때)에 표시됩니다.</span></label>
          <div>
            <span class="field-label">대표 이미지</span>
            <div class="up-box" data-kind="hero">
              <div class="up-prev" data-prev style="<?=$site['hero_image']!==''?"background-image:url('".site_admin_e(site_prev($site['hero_image']))."')":''?>"><?=$site['hero_image']===''?'자동 선택':''?></div>
              <div class="up-btns">
                <input type="hidden" name="hero_image" value="<?=site_admin_e($site['hero_image'])?>" data-val>
                <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-file>
                <button type="button" class="adm-btn" data-pick>사진 올리기</button>
                <button type="button" class="adm-btn" data-clear <?=$site['hero_image']===''?'hidden':''?>>삭제(자동 선택으로)</button>
                <span class="up-state" data-state>JPG·PNG·WEBP, 30MB 이하 (가로 사진 권장)</span>
              </div>
            </div>
          </div>
          <label><span class="field-label">제목</span><input class="admin-input mt-2" name="hero_title" value="<?=site_admin_e($site['hero_title'])?>"></label>
          <label><span class="field-label">설명</span><textarea class="admin-textarea mt-2" name="hero_subtitle" rows="3"><?=site_admin_e($site['hero_subtitle'])?></textarea></label>
        </div>
      </section>

      <section class="section-card p-5 sm:p-6">
        <h2 class="section-heading">프로필 / Footer</h2>
        <p class="section-description">페이지 맨 아래에 표시되는 소개와 프로필입니다.</p>
        <div class="mt-6 grid gap-5">
          <div>
            <span class="field-label">프로필 사진</span>
            <div class="up-box" data-kind="profile">
              <div class="up-prev round" data-prev style="background-image:url('<?=site_admin_e(site_prev($site['profile_photo']))?>')"></div>
              <div class="up-btns">
                <input type="hidden" name="profile_photo" value="<?=site_admin_e($site['profile_photo'])?>" data-val data-default="assets/logo_icon.png">
                <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-file>
                <button type="button" class="adm-btn" data-pick>사진 올리기</button>
                <button type="button" class="adm-btn" data-clear <?=$site['profile_photo']==='assets/logo_icon.png'?'hidden':''?>>기본 이미지로</button>
                <span class="up-state" data-state>가운데를 기준으로 정사각형으로 잘립니다.</span>
              </div>
            </div>
          </div>
          <label><span class="field-label">닉네임</span><input class="admin-input mt-2" name="profile_nickname" value="<?=site_admin_e($site['profile_nickname'])?>"></label>
          <label><span class="field-label">이메일</span><input class="admin-input mt-2" type="email" name="profile_email" value="<?=site_admin_e($site['profile_email'])?>"></label>
          <label><span class="field-label">소개</span><textarea class="admin-textarea mt-2" name="profile_intro" rows="4"><?=site_admin_e($site['profile_intro'])?></textarea></label>
          <label><span class="field-label">Copyright</span><input class="admin-input mt-2" name="footer_copyright" value="<?=site_admin_e($site['footer_copyright'])?>"></label>
        </div>
      </section>

      <?php $gm = travel_google_maps_config(); ?>
      <section class="section-card p-5 sm:p-6 xl:col-span-2">
        <h2 class="section-heading">Google Maps (선택)</h2>
        <p class="section-description">홈의 여행 지도, 여행 상세의 이동 경로, 관리자의 장소 검색에 쓰입니다. 비워 두면 지도 기능만 꺼지고 나머지는 모두 동작합니다.
          Google Cloud Console에서 <b>Maps JavaScript API</b>와 <b>Places API (New)</b>를 켠 키를 만들고, 내 사이트 주소(HTTP 리퍼러)로 제한하세요.</p>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
          <label><span class="field-label">API 키</span><input class="admin-input mt-2" name="maps_key" autocomplete="off" spellcheck="false" placeholder="AIza…" value="<?=site_admin_e($gm['key'])?>"></label>
          <label><span class="field-label">지도 ID (선택, 고급 마커용)</span><input class="admin-input mt-2" name="maps_id" autocomplete="off" spellcheck="false" value="<?=site_admin_e($gm['map_id'])?>"></label>
        </div>
      </section>

      <div class="xl:col-span-2"><button class="adm-primary" type="submit">설정 저장</button></div>
    </form>
  </section>
</div>
<script>
(function(){
  const csrf = document.getElementById('csrf').value;
  document.querySelectorAll('.up-box').forEach(box => {
    const kind = box.dataset.kind, prev = box.querySelector('[data-prev]'), val = box.querySelector('[data-val]'),
          file = box.querySelector('[data-file]'), state = box.querySelector('[data-state]'), clear = box.querySelector('[data-clear]');
    const def = val.dataset.default || '';
    const show = (src) => { prev.style.backgroundImage = src ? "url('" + src.replace(/'/g, '%27') + "')" : ''; prev.textContent = src ? '' : (kind === 'hero' ? '자동 선택' : ''); };
    box.querySelector('[data-pick]').addEventListener('click', () => file.click());
    file.addEventListener('change', async () => {
      const f = file.files[0]; if (!f) return;
      state.className = 'up-state'; state.textContent = '올리는 중…';
      const fd = new FormData(); fd.append('csrf', csrf); fd.append('kind', kind); fd.append('image', f);
      try {
        const r = await fetch('site-upload.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json().catch(() => ({ ok: false, message: '서버 응답을 읽을 수 없습니다.' }));
        if (!j.ok) throw new Error(j.message || '업로드 실패');
        val.value = j.path; show(j.preview); clear.hidden = false;
        state.textContent = '업로드 완료 — 아래 "설정 저장"을 눌러야 적용됩니다.';
      } catch (e) { state.className = 'up-state err'; state.textContent = e.message; }
      file.value = '';
    });
    clear.addEventListener('click', () => {
      val.value = def; show(def ? '../' + def : ''); clear.hidden = true;
      state.textContent = '저장하면 ' + (kind === 'hero' ? '최신 여행 대표 사진으로 자동 선택됩니다.' : '기본 이미지로 바뀝니다.');
    });
  });
})();
</script>
<?php admin_foot(); ?>

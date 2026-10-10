<?php
/** 자동저장(초안)과 수정 이력(리비전). 비밀 폴더(PRIVATE_DIR) 아래에 JSON 으로 보관 — 웹에서 직접 열 수 없는 .json 규칙의 보호를 받습니다. */
require_once __DIR__ . '/../config.php';

function drafts_dir(): string { return rtrim(PRIVATE_DIR, '/\\') . '/drafts'; }
function revs_root(): string { return rtrim(PRIVATE_DIR, '/\\') . '/revisions'; }
function draft_key_for_id(string $id): string { return 'edit-' . substr(sha1($id), 0, 24); }
function draft_valid_key(string $k): bool { return (bool)preg_match('/^(edit-[a-f0-9]{24}|new-draft_[a-f0-9]{24})$/', $k); }
function draft_file(string $k): string { return drafts_dir() . '/' . $k . '.json'; }

function draft_load(string $k): ?array {
    if (!draft_valid_key($k) || !is_file($f = draft_file($k))) return null;
    if (filemtime($f) < time() - 30 * 86400) { @unlink($f); return null; }
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) && is_array($d['trip'] ?? null) ? $d + ['key' => $k] : null;
}
function draft_save(string $k, array $trip, string $uploadKey = ''): bool {
    if (!draft_valid_key($k)) return false;
    return travel_write_json(draft_file($k), ['saved_at' => date('Y-m-d H:i:s'), 'upload_key' => $uploadKey, 'trip' => $trip]);
}
function draft_delete(string $k): void { if (draft_valid_key($k) && is_file($f = draft_file($k))) @unlink($f); }
/** 가장 최근의 '새 여행' 자동저장본(14일 이내, 내용이 있는 것) */
function draft_latest_new(): ?array {
    $best = null;
    foreach (glob(drafts_dir() . '/new-draft_*.json') ?: [] as $f) {
        if (filemtime($f) < time() - 14 * 86400) continue;
        $k = basename($f, '.json'); $d = draft_load($k);
        if (!$d || ($d['trip']['title'] ?? '') === '' && count($d['trip']['sections'] ?? []) <= 1) continue;
        if (!$best || filemtime($f) > $best['_t']) $best = $d + ['_t' => filemtime($f)];
    }
    return $best;
}

// ── 수정 이력 ──
function rev_dir(string $id): string { return revs_root() . '/' . sha1($id); }
const REV_KEEP = 15;
function rev_snapshot(string $id, array $old): void {
    if ($id === '') return;
    $dir = rev_dir($id); if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return;
    $files = glob($dir . '/*.json') ?: []; rsort($files);
    if ($files) { $last = json_decode((string)@file_get_contents($files[0]), true); if (is_array($last) && json_encode($last) === json_encode($old)) return; }
    travel_write_json($dir . '/' . date('Ymd-His') . '.json', $old);
    $files = glob($dir . '/*.json') ?: []; rsort($files);
    foreach (array_slice($files, REV_KEEP) as $f) @unlink($f);
}
function rev_list(string $id): array {
    $out = []; if ($id === '') return $out;
    $files = glob(rev_dir($id) . '/*.json') ?: []; rsort($files);
    foreach ($files as $f) {
        $n = basename($f, '.json'); if (!preg_match('/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$/', $n, $m)) continue;
        $kb = filesize($f) / 1024;
        $out[] = ['name' => $n, 'label' => "$m[1]-$m[2]-$m[3] $m[4]:$m[5]", 'size' => $kb < 1000 ? round($kb) . ' KB' : round($kb / 1024, 1) . ' MB'];
    }
    return $out;
}
function rev_load(string $id, string $name): ?array {
    if ($id === '' || !preg_match('/^\d{8}-\d{6}$/', $name) || !is_file($f = rev_dir($id) . '/' . $name . '.json')) return null;
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : null;
}

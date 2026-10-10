<?php
/**
 * 백업: 여행 데이터 + 사이트 설정 + 테마를 zip 으로 묶는다(사진 제외 — 사진은 Hyper Backup/폴더 복사로).
 *  - 관리자 화면에서 내려받기(오프사이트 백업) · 7일마다 자동 스냅샷(실수로 지운 경우 복구용, 최근 8개 보관)
 *  - zip 안: backup.json(전체) + README.txt
 */
require_once __DIR__ . '/data.php';

function backup_dir(): string { return rtrim(PRIVATE_DIR, '/\\') . '/backups'; }
function backup_meta_file(): string { return rtrim(PRIVATE_DIR, '/\\') . '/backup-meta.json'; }
function backup_meta(): array { $d = is_file($f = backup_meta_file()) ? json_decode((string)file_get_contents($f), true) : []; return is_array($d) ? $d : []; }
function backup_meta_set(string $k, $v): void { $m = backup_meta(); $m[$k] = $v; travel_write_json(backup_meta_file(), $m); }
function backup_zip_ok(): bool { return class_exists('ZipArchive'); }
function backup_read_json(string $f) { return is_file($f) ? json_decode((string)file_get_contents($f), true) : null; }

/** zip 파일을 $dest 에 만든다. [성공, 메시지, 여행 수] */
function backup_build(string $dest): array {
    if (!backup_zip_ok()) return [false, 'PHP 에 zip 확장이 없습니다. Web Station → PHP 프로필 → 확장 모듈에서 zip 을 켜 주세요.', 0];
    $trips = store()->all();
    $payload = ['app' => 'naeggeolog', 'version' => 2, 'exported_at' => date('c'), 'trips' => $trips,
                'site' => backup_read_json(travel_private_file('site.json')), 'theme' => backup_read_json(travel_private_file('theme.json'))];
    $z = new ZipArchive();
    if ($z->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return [false, '백업 파일을 만들 수 없습니다(쓰기 권한 확인).', 0];
    $z->addFromString('backup.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $z->addFromString('README.txt', "Naeggeolog 백업 (" . date('Y-m-d H:i:s') . ")\n여행 " . count($trips) . "편 · 사이트 설정 · 테마 포함. 사진(travel-photos)은 포함되지 않습니다.\n복원: 관리자 → 백업 · 복원 → 'zip 으로 복원'.\n");
    $z->close();
    return [true, '', count($trips)];
}

/** 마지막 자동 스냅샷이 7일 이상 지났으면 새로 만든다(관리자 화면 접속 시 조용히 실행). 오래된 것은 8개만 남김 */
function backup_snapshot_if_due(bool $force = false, string $prefix = 'auto'): ?string {
    if (!backup_zip_ok()) return null;
    $m = backup_meta();
    if (!$force && !empty($m['last_snapshot']) && strtotime((string)$m['last_snapshot']) > time() - 7 * 86400) return null;
    if (!store()->list()) return null;
    $dir = backup_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    $name = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
    [$ok] = backup_build($dir . '/' . $name);
    if (!$ok) { @unlink($dir . '/' . $name); return null; }
    backup_meta_set('last_snapshot', date('c'));
    $files = glob($dir . '/auto-*.zip') ?: []; rsort($files);
    foreach (array_slice($files, 8) as $f) @unlink($f);
    return $name;
}
function backup_list(): array {
    $out = [];
    foreach (glob(backup_dir() . '/*.zip') ?: [] as $f) $out[] = ['name' => basename($f), 'time' => filemtime($f), 'size' => filesize($f)];
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}
function backup_fmt_size(int $b): string { return $b < 1048576 ? round($b / 1024) . ' KB' : round($b / 1048576, 1) . ' MB'; }
/** 오프사이트(내려받기) 백업 경과 일수. 한 번도 없으면 null */
function backup_days_since_download(): ?int {
    $m = backup_meta(); if (empty($m['last_download'])) return null;
    return (int)floor((time() - strtotime((string)$m['last_download'])) / 86400);
}

/** zip 에서 복원. 반환 [성공, 메시지]. 복원 전에 현재 상태를 'pre-restore' 스냅샷으로 남긴다. */
function backup_restore(string $zipPath, bool $withSettings): array {
    if (!backup_zip_ok()) return [false, 'zip 확장이 없습니다.'];
    $z = new ZipArchive();
    if ($z->open($zipPath) !== true) return [false, 'zip 파일을 열 수 없습니다.'];
    $raw = $z->getFromName('backup.json');
    $data = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($data) || !is_array($data['trips'] ?? null) || !in_array(($data['app'] ?? ''), ['naeggeolog', 'taegap-travel'], true)) { $z->close(); return [false, 'Naeggeolog 백업 파일이 아닙니다(backup.json 없음).']; }
    $z->close();
    backup_snapshot_if_due(true, 'pre-restore');
    $n = 0; $skip = 0; $st = store();
    foreach ($data['trips'] as $t) {
        $id = is_array($t) ? (string)($t['id'] ?? '') : '';
        if ($id === '' || trim((string)($t['title'] ?? '')) === '' || preg_match('/[\x00-\x1F\x7F\/\\\\]/', $id) || $id === '.' || $id === '..') { $skip++; continue; }
        if ($st->save($t)) $n++; else $skip++;
    }
    $extra = '';
    if ($withSettings) {
        if (is_array($data['site'] ?? null)) { travel_write_json(rtrim(PRIVATE_DIR, '/\\') . '/site.json', $data['site']); $extra .= ' 사이트 설정'; }
        if (is_array($data['theme'] ?? null)) { travel_write_json(rtrim(PRIVATE_DIR, '/\\') . '/theme.json', $data['theme']); $extra .= ' 테마'; }
    }
    return [true, "여행 {$n}편을 복원했습니다." . ($skip ? " (건너뜀 {$skip}건)" : '') . ($extra ? " 복원된 설정:{$extra}." : '')];
}

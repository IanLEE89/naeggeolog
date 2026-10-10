<?php
/**
 * 내 공간 홈(데스크톱) 공통 — 위젯 데이터와 배경화면을 사용자별로 저장합니다.
 *   데이터: private/hub.sqlite 의 desk_data 표 (사용자 id × 키 → JSON)
 *   배경 이미지: private/hub-desk/wall-u<id>.<확장자> (웹에서 직접 열 수 없음, desk-wall.php 로만 전달)
 * 이름이 _ 로 시작하므로 웹에서 직접 열 수 없습니다.
 */
require_once __DIR__ . '/_boot.php';

/** 저장할 수 있는 키와 최대 크기(바이트) */
const DESK_KEYS = [
    'layout'  => 8000,     // 위젯 순서·크기·숨김
    'wall'    => 2000,     // 배경화면 설정
    'todo'    => 120000,   // 할 일
    'notes'   => 200000,   // 메모
    'links'   => 60000,    // 바로가기
    'dday'    => 40000,    // D-day
    'weather' => 2000,     // 날씨 지역
    'pomo'    => 4000,     // 집중 타이머 설정·기록
    'cal'     => 120000,   // 캘린더 일정
];

function desk_db(): PDO {
    static $ready = false;
    $db = hub_db();
    if (!$ready) {
        $db->exec("CREATE TABLE IF NOT EXISTS desk_data (
            user_id INTEGER NOT NULL, key TEXT NOT NULL, value TEXT NOT NULL, updated_at TEXT NOT NULL,
            PRIMARY KEY (user_id, key))");
        $ready = true;
    }
    return $db;
}
/** 사용자의 위젯 데이터 전체: ['todo' => [...], ...] */
function desk_load(array $u): array {
    $s = desk_db()->prepare('SELECT key, value FROM desk_data WHERE user_id = ?');
    $s->execute([(int)$u['id']]);
    $out = [];
    foreach ($s->fetchAll() as $r) {
        if (!isset(DESK_KEYS[$r['key']])) continue;
        $v = json_decode((string)$r['value'], true);
        if ($v !== null) $out[$r['key']] = $v;
    }
    return $out;
}
function desk_save(array $u, string $key, string $json): void {
    desk_db()->prepare('INSERT OR REPLACE INTO desk_data(user_id, key, value, updated_at) VALUES(?,?,?,?)')
        ->execute([(int)$u['id'], $key, $json, date('c')]);
}

/* ───────── 배경 이미지 ───────── */
function desk_wall_dir(): string { return rtrim(PRIVATE_DIR, '/\\') . '/hub-desk'; }
/** 현재 사용자의 배경 이미지 경로(없으면 null) */
function desk_wall_file(array $u): ?string {
    foreach (['jpg', 'png', 'webp'] as $ext) {
        $f = desk_wall_dir() . '/wall-u' . (int)$u['id'] . '.' . $ext;
        if (is_file($f)) return $f;
    }
    return null;
}
function desk_json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

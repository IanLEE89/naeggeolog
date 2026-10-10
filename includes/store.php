<?php
/**
 * 여행 데이터 저장소 — SQLite (private/travel.sqlite).
 *   store()->list()   : 가벼운 목록(제목·날짜·국가·좌표·요약·커버·사진수). 홈/내비/지도/관리자 목록용
 *   store()->all()    : 본문 포함 전체(지출 대시보드·삭제 시 사진 참조 계산용)
 *   store()->get($id) : 본문 포함 1건
 *   store()->search($q): 검색어(공백 AND)에 맞는 id 목록(날짜 내림차순)
 *   store()->save($trip) / delete($id) / exists($id)
 * 홈·메뉴·지도는 목록용 열만 읽고 본문(JSON 칼럼)은 상세 페이지에서만 읽으므로 여행이 많아져도 가볍습니다.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/data.php';   // trip_search_text

/** 목록용 가벼운 행 (본문 제외).  */
function trip_light(array $t): array {
    $out = [];
    foreach (['id', 'date', 'title', 'country', 'country_en', 'location', 'summary', 'cover'] as $k) $out[$k] = (string)($t[$k] ?? '');
    foreach (['lat', 'lng'] as $k) $out[$k] = (isset($t[$k]) && $t[$k] !== '' && is_numeric($t[$k])) ? (float)$t[$k] : '';
    $out['image_count'] = (int)($t['image_count'] ?? 0);
    $out['hidden'] = !empty($t['hidden']);
    $out['draft'] = !empty($t['draft']);
    $out['pinned'] = !empty($t['pinned']);
    $out['publish_at'] = (string)($t['publish_at'] ?? '');
    $out['tags'] = array_values(array_filter(array_map('strval', (array)($t['tags'] ?? [])), fn($x) => $x !== ''));
    return $out;
}
function trip_sort(array &$rows): void { usort($rows, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')) ?: strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''))); }

/* ───────────────────────── SQLite ───────────────────────── */
final class SqliteStore {
    private PDO $db;
    public function __construct(private string $file) {
        $this->db = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->db->exec('PRAGMA busy_timeout = 5000');
        $this->db->exec('PRAGMA synchronous = NORMAL');
        $this->migrate();
    }
    public static function available(): bool { return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true); }
    public static function dbPath(): string { return rtrim(PRIVATE_DIR, '/\\') . '/travel.sqlite'; }
        public function pdo(): PDO { return $this->db; }
    public function path(): string { return $this->file; }

    private const SCHEMA = 3;
    /** 스키마가 최신이면 한 번의 PRAGMA 로 끝낸다(요청마다 CREATE/ALTER 를 점검하지 않음) */
    private function migrate(): void {
        if ((int)$this->db->query('PRAGMA user_version')->fetchColumn() >= self::SCHEMA) return;
        if (defined('CACHE_DIR')) @unlink(CACHE_DIR . '/trips-light.json');   // 예전 JSON 저장소의 목록 캐시 정리
        try { $this->db->exec('PRAGMA journal_mode = WAL'); } catch (Throwable $e) { /* 일부 파일시스템은 WAL 불가 → 기본 모드 */ }
        $this->db->exec("CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS trips (
            id TEXT PRIMARY KEY, date TEXT, title TEXT NOT NULL, country TEXT, country_en TEXT, location TEXT,
            lat REAL, lng REAL, summary TEXT, cover TEXT, image_count INTEGER NOT NULL DEFAULT 0,
            search_text TEXT NOT NULL DEFAULT '', data TEXT NOT NULL, updated_at TEXT)");
        $cols = array_column($this->db->query('PRAGMA table_info(trips)')->fetchAll(), 'name');
        foreach (['hidden' => 'INTEGER NOT NULL DEFAULT 0', 'draft' => 'INTEGER NOT NULL DEFAULT 0', 'pinned' => 'INTEGER NOT NULL DEFAULT 0', 'publish_at' => "TEXT NOT NULL DEFAULT ''", 'tags' => "TEXT NOT NULL DEFAULT '[]'"] as $__c => $__d)
            if (!in_array($__c, $cols, true)) $this->db->exec("ALTER TABLE trips ADD COLUMN $__c $__d");
        $this->db->exec("CREATE INDEX IF NOT EXISTS trips_date ON trips(date DESC)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS trips_country ON trips(country)");
        $this->db->exec('PRAGMA user_version = ' . self::SCHEMA);
    }
    public function meta(string $k): ?string { $s = $this->db->prepare('SELECT value FROM meta WHERE key = ?'); $s->execute([$k]); $v = $s->fetchColumn(); return $v === false ? null : (string)$v; }
    public function setMeta(string $k, string $v): void { $this->db->prepare('INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)')->execute([$k, $v]); }
    public function signature(): string { return $this->count() . ':' . (string)$this->db->query('SELECT MAX(updated_at) FROM trips')->fetchColumn(); }
    public function count(): int { return (int)$this->db->query('SELECT COUNT(*) FROM trips')->fetchColumn(); }

    public function list(): array {
        $rows = $this->db->query('SELECT id,date,title,country,country_en,location,lat,lng,summary,cover,image_count,hidden,draft,pinned,publish_at,tags FROM trips ORDER BY date DESC, id ASC')->fetchAll();
        foreach ($rows as &$r) { $r['lat'] = $r['lat'] === null ? '' : (float)$r['lat']; $r['lng'] = $r['lng'] === null ? '' : (float)$r['lng']; $r['image_count'] = (int)$r['image_count']; $r['hidden'] = !empty($r['hidden']); $r['draft'] = !empty($r['draft']); $r['pinned'] = !empty($r['pinned']); $r['publish_at'] = (string)($r['publish_at'] ?? ''); $tg = json_decode((string)($r['tags'] ?? '[]'), true); $r['tags'] = is_array($tg) ? array_values($tg) : []; foreach (['date','country','country_en','location','summary','cover'] as $k) $r[$k] = (string)($r[$k] ?? ''); }
        return $rows;
    }
    public function all(): array {
        $out = [];
        foreach ($this->db->query('SELECT data FROM trips ORDER BY date DESC, id ASC') as $r) { $d = json_decode($r['data'], true); if (is_array($d)) $out[] = $d; }
        return $out;
    }
    public function get(string $id): ?array { $s = $this->db->prepare('SELECT data FROM trips WHERE id = ?'); $s->execute([$id]); $v = $s->fetchColumn(); $d = $v === false ? null : json_decode($v, true); return is_array($d) ? $d : null; }
    public function exists(string $id): bool { $s = $this->db->prepare('SELECT 1 FROM trips WHERE id = ?'); $s->execute([$id]); return (bool)$s->fetchColumn(); }
    public function search(string $q): array {
        $terms = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($q))), fn($x) => $x !== ''));
        if (!$terms) return array_column($this->db->query('SELECT id FROM trips ORDER BY date DESC, id ASC')->fetchAll(), 'id');
        $where = implode(' AND ', array_fill(0, count($terms), 'instr(search_text, ?) > 0'));
        $s = $this->db->prepare("SELECT id FROM trips WHERE $where ORDER BY date DESC, id ASC"); $s->execute($terms);
        return array_column($s->fetchAll(), 'id');
    }
    public function save(array $trip): bool {
        $id = (string)($trip['id'] ?? ''); if ($id === '' || trim((string)($trip['title'] ?? '')) === '') return false;
        $l = trip_light($trip);
        $json = json_encode($trip, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); if ($json === false) return false;
        try {
            $this->db->prepare('INSERT OR REPLACE INTO trips(id,date,title,country,country_en,location,lat,lng,summary,cover,image_count,search_text,data,updated_at,hidden,draft,pinned,publish_at,tags) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$id, $l['date'], $l['title'], $l['country'], $l['country_en'], $l['location'], $l['lat'] === '' ? null : $l['lat'], $l['lng'] === '' ? null : $l['lng'],
                           $l['summary'], $l['cover'], $l['image_count'], mb_strtolower(trip_search_text($trip)), $json, date('c'), $l['hidden'] ? 1 : 0, $l['draft'] ? 1 : 0, $l['pinned'] ? 1 : 0, $l['publish_at'], json_encode($l['tags'], JSON_UNESCAPED_UNICODE)]);
            return true;
        } catch (Throwable $e) { error_log('SqliteStore::save ' . $e->getMessage()); return false; }
    }
    public function delete(string $id): bool { try { return $this->db->prepare('DELETE FROM trips WHERE id = ?')->execute([$id]); } catch (Throwable $e) { return false; } }
}

/* ───────────────────────── 선택 ───────────────────────── */
/** 저장소(한 요청 안에서 한 번만 연다). pdo_sqlite 가 없으면 이유를 알려 주고 멈춘다 */
function store(): SqliteStore {
    static $s = null;
    if ($s !== null) return $s;
    if (!SqliteStore::available()) { http_response_code(500); exit('PHP 의 pdo_sqlite 확장이 필요합니다. Web Station → PHP 프로필 → 확장 모듈에서 pdo_sqlite 를 켜 주세요.'); }
    $dir = dirname(SqliteStore::dbPath());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    try { return $s = new SqliteStore(SqliteStore::dbPath()); }
    catch (Throwable $e) { error_log('SQLite 열기 실패: ' . $e->getMessage()); http_response_code(500); exit('데이터베이스를 열 수 없습니다. private 폴더에 웹 서버 계정(http)의 쓰기 권한이 있는지 확인하세요.'); }
}

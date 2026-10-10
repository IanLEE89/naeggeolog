<?php
/**
 * 가벼운 자체 방문 집계(외부 서비스·쿠키 없음).
 *  - 월별 JSON 한 개(private/stats/YYYY-MM.json): 일별 조회수·순 방문자·여행별 조회수·유입 사이트
 *  - 순 방문자: IP+브라우저+날짜를 비밀 솔트로 해시한 8자리 값만 이틀간 보관(개인 식별 불가). IP·UA 원문은 저장하지 않음
 *  - 봇·관리자·미리 읽기·Do Not Track 요청은 집계하지 않음
 */
require_once __DIR__ . '/data.php';

if (!defined('STATS_ENABLED')) define('STATS_ENABLED', true);
function stats_dir(): string { return rtrim(PRIVATE_DIR, '/\\') . '/stats'; }
function stats_file(string $ym): string { return stats_dir() . '/' . $ym . '.json'; }
function stats_salt(): string {
    $f = stats_dir() . '/.salt';
    if (is_file($f)) return (string)file_get_contents($f);
    if (!is_dir(stats_dir())) @mkdir(stats_dir(), 0775, true);
    $s = bin2hex(random_bytes(16)); @file_put_contents($f, $s, LOCK_EX); return $s;
}
function stats_is_bot(string $ua): bool {
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|monitor|uptime|curl|wget|python|java\/|go-http|headless|lighthouse|pagespeed|gtmetrix|feedfetcher|feedly|rss/i', $ua);
}
function stats_host(string $url): string {
    $h = strtolower((string)parse_url($url, PHP_URL_HOST)); return preg_replace('/^(www|m|l)\./', '', $h);
}

/** 여행 상세 조회 1회 기록. $tripId 가 빈 문자열이면 사이트 전체 방문만 센다 */
function stats_record(string $tripId = ''): void {
    if (!STATS_ENABLED || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (stats_is_bot($ua) || ($_SERVER['HTTP_DNT'] ?? '') === '1') return;
    if (stripos((string)($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . (string)($_SERVER['HTTP_PURPOSE'] ?? ''), 'prefetch') !== false) return;
    if (function_exists('is_admin_session') && is_admin_session()) return;
    try {
        $day = date('Y-m-d'); $ym = date('Y-m');
        $dir = stats_dir(); if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return;
        $h = @fopen(stats_file($ym), 'c+'); if (!$h) return;
        if (!flock($h, LOCK_EX)) { fclose($h); return; }
        $d = json_decode((string)stream_get_contents($h), true); if (!is_array($d)) $d = ['days' => [], 'seen' => []];
        $row = $d['days'][$day] ?? ['v' => 0, 'u' => 0, 't' => [], 'r' => []];
        $row['v']++;
        $vid = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua . '|' . $day . '|' . stats_salt()), 0, 8);
        $seen = $d['seen'][$day] ?? [];
        if (!in_array($vid, $seen, true)) { $seen[] = $vid; $row['u']++; }
        if ($tripId !== '') $row['t'][$tripId] = ($row['t'][$tripId] ?? 0) + 1;
        $ref = stats_host((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($ref !== '' && $ref !== stats_host('http://' . ($_SERVER['HTTP_HOST'] ?? ''))) { $ref = mb_substr($ref, 0, 60); $row['r'][$ref] = ($row['r'][$ref] ?? 0) + 1; }
        $d['days'][$day] = $row;
        $d['seen'] = [$day => $seen] + array_slice($d['seen'] ?? [], 0, 1, true);       // 오늘 + 하루 전만 보관
        foreach (array_keys($d['seen']) as $k) if ($k < date('Y-m-d', time() - 86400)) unset($d['seen'][$k]);
        ftruncate($h, 0); rewind($h); fwrite($h, json_encode($d, JSON_UNESCAPED_UNICODE)); fflush($h);
        flock($h, LOCK_UN); fclose($h);
    } catch (Throwable $e) { /* 집계 실패는 사이트에 영향을 주지 않음 */ }
}

/** 최근 $days 일 요약: ['daily'=>[날짜=>[v,u]], 'trips'=>[id=>조회수], 'refs'=>[호스트=>수], 'v','u'] */
function stats_summary(int $days = 30): array {
    $out = ['daily' => [], 'trips' => [], 'refs' => [], 'v' => 0, 'u' => 0];
    for ($i = $days - 1; $i >= 0; $i--) $out['daily'][date('Y-m-d', time() - $i * 86400)] = [0, 0];
    $months = []; for ($i = 0; $i < $days; $i += 28) $months[date('Y-m', time() - $i * 86400)] = true; $months[date('Y-m', time() - ($days - 1) * 86400)] = true;
    foreach (array_keys($months) as $ym) {
        $d = is_file($f = stats_file($ym)) ? json_decode((string)file_get_contents($f), true) : null;
        foreach ((array)($d['days'] ?? []) as $day => $r) {
            if (!isset($out['daily'][$day])) continue;
            $out['daily'][$day] = [(int)$r['v'], (int)$r['u']]; $out['v'] += (int)$r['v']; $out['u'] += (int)$r['u'];
            foreach ((array)($r['t'] ?? []) as $id => $n) $out['trips'][$id] = ($out['trips'][$id] ?? 0) + (int)$n;
            foreach ((array)($r['r'] ?? []) as $h => $n) $out['refs'][$h] = ($out['refs'][$h] ?? 0) + (int)$n;
        }
    }
    arsort($out['trips']); arsort($out['refs']);
    return $out;
}
/** 홈의 "많이 본 여행"용: 최근 90일 조회수 상위 id 목록(데이터가 충분할 때만) */
function stats_popular_ids(int $n = 4): array {
    static $memo = null;
    if ($memo === null) { $s = stats_summary(90); $memo = array_filter($s['trips'], fn($c) => $c >= 2); }
    return count($memo) >= $n && array_sum($memo) >= 20 ? array_slice(array_keys($memo), 0, $n) : [];
}

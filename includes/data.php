<?php
require_once __DIR__ . '/../config.php';
function photo_url($rel) { return rtrim(TRAVEL_PHOTO_URL, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', ltrim((string)$rel, '/')))); }
/** 가벼운 여행 목록(본문 제외, 날짜 내림차순) — 홈·내비게이션·지도·관리자 목록용 */
function load_trips() { static $trips = null; return $trips ??= array_values(array_filter(store()->list(), 'trip_is_public')); }
/** 숨김 여행까지 모두 포함한 목록 — 관리자 전용 */
function load_trips_admin() { static $t = null; return $t ??= store()->list(); }
/** 공개 여행의 본문 포함 전체 (지출 대시보드) */
function load_trips_full() { return array_values(array_filter(store()->all(), 'trip_is_public')); }
function load_trips_fresh() { return store()->all(); }
/** 본문 포함 여행 1건 */
function get_trip($id) { return store()->get((string)$id); }
/** 검색어에 맞는 여행 id 목록(순서 유지) */
function trip_search_ids($q) { return store()->search((string)$q); }
function countries_from_trips($trips){$countries=[];foreach($trips as $t){$c=trim((string)($t['country']??''));if($c!=='')$countries[$c]=true;} $list=array_keys($countries);sort($list,SORT_NATURAL|SORT_FLAG_CASE);return $list;}
function trips_by_country($trips,$country){return array_values(array_filter($trips,function($t)use($country){return ($t['country']??'')===$country;}));}
/** 시간 순서상 이전(더 오래된)/다음(더 최근) 여행. 목록은 날짜 내림차순이므로 뒤집어서 계산 */
function trip_neighbors(array $light, string $id): array {
    $asc = array_values(array_reverse($light)); $n = count($asc);
    foreach ($asc as $i => $t) if ((string)$t['id'] === $id) return ['prev' => $i > 0 ? $asc[$i - 1] : null, 'next' => $i < $n - 1 ? $asc[$i + 1] : null, 'pos' => $i + 1, 'total' => $n];
    return ['prev' => null, 'next' => null, 'pos' => 0, 'total' => $n];
}
/** 공개 상태: public | scheduled(예약 공개 대기) | draft(초안) | hidden(숨김) */
function trip_state(array $t): string {
    if (!empty($t['hidden'])) return 'hidden';
    if (!empty($t['draft'])) return 'draft';
    $p = trim((string)($t['publish_at'] ?? ''));
    if ($p !== '') { $ts = strtotime($p); if ($ts !== false && $ts > time()) return 'scheduled'; }
    return 'public';
}
function trip_is_public(array $t): bool { return trip_state($t) === 'public'; }
function trip_state_label(string $s): string { return ['public' => '공개', 'scheduled' => '예약', 'draft' => '초안', 'hidden' => '숨김'][$s] ?? $s; }
/** 태그 정리: 문자열(쉼표·줄바꿈 구분) 또는 배열 → 중복 없는 최대 12개, 각 24자 이하 */
function trip_clean_tags($raw): array {
    if (is_string($raw)) $raw = preg_split('/[,\n]+/u', $raw);
    $out = [];
    foreach ((array)$raw as $x) {
        $x = trim(preg_replace('/\s+/u', ' ', ltrim(trim((string)$x), '#')));
        if ($x === '' || preg_match('/[<>"\x00-\x1F]/u', $x)) continue;
        $x = mb_substr($x, 0, 24);
        $k = mb_strtolower($x); if (!isset($out[$k])) $out[$k] = $x;
        if (count($out) >= 12) break;
    }
    return array_values($out);
}
/** 공개 여행에서 쓰인 태그 → [태그 => 개수] (많이 쓴 순) */
function trip_tag_counts(array $light): array {
    $c = [];
    foreach ($light as $t) foreach ((array)($t['tags'] ?? []) as $g) { $k = mb_strtolower((string)$g); $c[$k] = ['name' => (string)$g, 'n' => ($c[$k]['n'] ?? 0) + 1]; }
    uasort($c, fn($a, $b) => $b['n'] <=> $a['n'] ?: strcmp($a['name'], $b['name']));
    return array_column($c, 'n', 'name');
}
/** 비슷한 여행: 공통 태그(2점/개) + 같은 나라(1점). 점수가 같으면 날짜가 가까운 순. 점수 0 은 제외 */
function trip_related(array $light, array $trip, int $n = 3): array {
    $tags = array_map('mb_strtolower', array_map('strval', (array)($trip['tags'] ?? [])));
    $scored = [];
    foreach ($light as $t) {
        if ((string)$t['id'] === (string)$trip['id']) continue;
        $score = 2 * count(array_intersect($tags, array_map('mb_strtolower', array_map('strval', (array)($t['tags'] ?? [])))));
        if (($t['country'] ?? '') !== '' && ($t['country'] ?? '') === ($trip['country'] ?? '')) $score += 1;
        if ($score > 0) $scored[] = [$score, abs(strtotime((string)$t['date']) - strtotime((string)($trip['date'] ?? 'now'))), $t];
    }
    usort($scored, fn($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
    return array_map(fn($x) => $x[2], array_slice($scored, 0, $n));
}
function trips_by_tag(array $trips, string $tag): array {
    $k = mb_strtolower($tag);
    return array_values(array_filter($trips, fn($t) => in_array($k, array_map('mb_strtolower', array_map('strval', (array)($t['tags'] ?? []))), true)));
}
function find_trip($trips,$id){foreach($trips as $t)if((string)$t['id']===(string)$id)return $t;return null;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

/** 여행에서 참조하는 사진 상대경로 목록(커버 + 섹션 사진). */
function trip_photo_paths($t){
    $out=[]; if(!empty($t['cover']))$out[]=(string)$t['cover'];
    foreach((array)($t['sections']??[]) as $s){ foreach((array)($s['images']??[]) as $i){ if($i!=='')$out[]=(string)$i; } }
    return array_values(array_unique($out));
}
/** 검색어(공백 구분 AND)와 여행 본문 전체를 대소문자 무시하고 비교. */
function trip_matches_query($t,$q){
    $q=trim((string)$q); if($q==='')return true;
    $hay=trip_search_text($t);
    foreach(preg_split('/\s+/u',$q) as $term){ if($term!==''&&mb_stripos($hay,$term)===false)return false; }
    return true;
}

function trip_search_text($t){
    $parts=[];
    foreach(['title','date','country','country_en','location','summary'] as $k){
        if(isset($t[$k])) $parts[]=(string)$t[$k];
    }
    if(!empty($t['tags'])&&is_array($t['tags'])) $parts[]=implode(' ',array_map('strval',$t['tags']));
    $basic=$t['basic_info']??[];
    if(is_array($basic)){
        foreach(['date','weather','country','country_en','location','major_places'] as $k){ if(isset($basic[$k])) $parts[]=(string)$basic[$k]; }
        if(isset($basic['lodging'])){
            if(is_array($basic['lodging'])) $parts[]=(string)($basic['lodging']['name']??'');
            else $parts[]=(string)$basic['lodging'];
        }
        if(isset($basic['transport']) && is_array($basic['transport'])) $parts=array_merge($parts,array_map('strval',$basic['transport']));
    }
    foreach(($t['sections']??[]) as $section){
        if(!is_array($section)) continue;
        foreach(['title','anchor','content_html'] as $k){ if(isset($section[$k])) $parts[] = strip_tags((string)$section[$k]); }
        $info=$section['subsection_info']??[];
        if(is_array($info)){
            foreach(['location','location_url','purpose'] as $k){ if(isset($info[$k])) $parts[]=(string)$info[$k]; }
            if(isset($info['features']) && is_array($info['features'])) $parts=array_merge($parts,array_map('strval',$info['features']));
        }
        if(isset($section['expenses']) && is_array($section['expenses'])){
            foreach($section['expenses'] as $ex){
                if(is_array($ex)) $parts[]=(string)($ex['item']??'');
            }
        }
    }
    if(isset($t['expenses']) && is_array($t['expenses'])){
        foreach($t['expenses'] as $ex){ if(is_array($ex)) $parts[]=(string)($ex['item']??''); }
    }
    return trim(preg_replace('/\s+/u',' ',implode(' ',array_filter($parts,fn($v)=>$v!==''))));
}

require_once __DIR__ . '/store.php';

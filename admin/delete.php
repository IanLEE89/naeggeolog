<?php
require_once __DIR__.'/auth.php';
require_admin();
require_once __DIR__.'/../includes/data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('잘못된 요청입니다.');
}
$id = trim((string)($_POST['id'] ?? ''));
if ($id === '' || $id === '_template') { http_response_code(400); exit('삭제할 여행을 확인할 수 없습니다.'); }

$st = store();
$trip = $st->get($id);
if ($trip === null) { http_response_code(404); exit('삭제할 여행을 찾을 수 없습니다. <a href="index.php">여행 관리로 돌아가기</a>'); }
if (!$st->delete($id)) {
    http_response_code(500);
    exit('여행을 삭제할 수 없습니다. 파일/데이터베이스 쓰기 권한을 확인하세요.');
}

// 사진 삭제: 이 여행이 "참조하는 파일"만 지웁니다.
//  - 같은 날짜·국가 폴더를 쓰는 다른 여행의 사진은 건드리지 않습니다.
//  - 다른 여행이 같은 파일을 참조하고 있어도 보존합니다.
$photosDeleted = false;
if (!empty($_POST['delete_photos'])) {
    $photoRoot = realpath(dirname(__DIR__) . '/travel-photos');
    if ($photoRoot !== false) {
        $inUse = [];
        foreach ($st->all() as $other) foreach (trip_photo_paths($other) as $p) $inUse[$p] = true;
        $prefix = rtrim($photoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $dirs = [];
        foreach (trip_photo_paths($trip) as $rel) {
            if (isset($inUse[$rel]) || str_contains($rel, '..')) continue;
            $abs = realpath($photoRoot . '/' . $rel);
            if ($abs === false || !is_file($abs) || !str_starts_with($abs, $prefix)) continue;
            if (@unlink($abs)) { $photosDeleted = true; $dirs[dirname($abs)] = true; }
        }
        foreach (array_keys($dirs) as $d) { if ($d !== $photoRoot && !glob($d . '/*')) @rmdir($d); } // 비었을 때만 폴더 삭제
    }
}
header('Location: index.php?deleted=1&photos=' . ($photosDeleted ? '1' : '0'));
exit;

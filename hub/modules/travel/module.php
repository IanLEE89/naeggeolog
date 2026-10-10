<?php
// 여행 기록 — 기존 여행 블로그로 연결하는 카드. (주인에게는 관리 화면 바로가기도 보임)
return [
    'title' => '여행 기록',
    'desc'  => '다녀온 여행의 글·사진·지도·지출을 봅니다.',
    'url'   => '../index.php',
    'order' => 10,
    'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3Z"/>',
    'badge' => function (array $u): string {
        try {
            require_once dirname(__DIR__, 3) . '/includes/store.php';
            $n = count(load_trips());
            return '공개된 여행 ' . $n . '편' . (hub_is_owner($u) ? ' · 관리는 관리자 화면에서' : '');
        } catch (Throwable $e) { return ''; }
    },
];

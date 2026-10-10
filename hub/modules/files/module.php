<?php
// 파일 — NAS 공유 폴더를 웹하드처럼 탐색·다운로드. 공유받은 폴더가 하나라도 있는 사람에게만 보입니다.
return [
    'title'   => '파일',
    'desc'    => 'NAS 공유 폴더를 탐색하고 바로 내려받습니다.',
    'url'     => 'modules/files/',
    'order'   => 20,
    'icon'    => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
    'visible' => function (array $u): bool { return hub_is_owner($u) || count(hub_shares_for($u)) > 0; },
    'badge'   => function (array $u): string { $n = count(hub_shares_for($u)); return $n ? '공유 폴더 ' . $n . '개' : '공유 폴더를 설정하세요'; },
];

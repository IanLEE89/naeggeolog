<?php
// Google Maps 설정을 페이지에 전달하고 assets/maps.js 를 불러옵니다.
// 실제 Google 스크립트는 지도가 화면 근처에 왔을 때 maps.js 가 지연 로드합니다.
require_once __DIR__ . '/config.php';
$gm = travel_google_maps_config();
$mapsJs = 'assets/maps.js'; $clusterJs = 'assets/vendor/markerclusterer.js';
if (function_exists('asset_url')) { $mapsJs = asset_url($mapsJs); $clusterJs = asset_url($clusterJs); }   // 수정시각 기반 캐시 갱신
?>
<script>window.TRAVEL_MAPS=<?=json_encode(['key'=>$gm['key'],'mapId'=>$gm['map_id']!==''?$gm['map_id']:'DEMO_MAP_ID','clusterer'=>$clusterJs],JSON_UNESCAPED_SLASHES)?>;</script>
<script src="<?=htmlspecialchars($mapsJs,ENT_QUOTES,'UTF-8')?>" defer></script>

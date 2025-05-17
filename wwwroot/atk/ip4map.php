<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_ipcombine.php';
$db = createDbLink();

$img = imagecreate(4096, 4096);

$colors = [];

$colors[0] = imagecolorallocate($img, 0, 0, 0);
for ($i = 1; $i <= 255; $i++) {
    $colors[$i] = imagecolorallocate($img, $i, 255 - $i, 0);
}

$atk_list4 = pg_query($db, 'SELECT
    host(CASE WHEN COUNT(*) >= 2 THEN network(set_masklen(ip, 24)) ELSE MIN(ip) END) AS ip,
    COUNT(*) AS cnt
FROM atkIps
WHERE family(ip) = 4
GROUP BY network(set_masklen(ip, 24))');

$data = pg_fetch_all($atk_list4);

foreach ($data as $row) {
    $ip = ip2long($row['ip']);
    if ($ip === false) {
        continue;
    }
    $ip = $ip >> 8;
    $cnt = $row['cnt'];

    if ($cnt > 255) {
        $cnt = 255;
    }

    $x_idx = $ip % 4096;
    $y_idx = intval($ip / 4096);

    imagesetpixel($img, $x_idx, $y_idx, $colors[$cnt]);
}

header('Content-Type: image/png');
imagepng($img);
imagedestroy($img);
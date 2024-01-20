<?php
require_once __DIR__ . '/../_init.php';
$db = createDbLink();

$range = $_GET['range'] ?? 'host';
if ($range !== 'net') $range = 'host';

$atk_list4 = null;
$atk_list6 = null;

if ($range === 'net') {
    $atk_list4 = pg_query($db, 'SELECT DISTINCT network(set_masklen(ip, 24)) AS ip FROM atkIps WHERE family(ip) = 4 ORDER BY ip ASC');
    $atk_list6 = pg_query($db, 'SELECT DISTINCT network(set_masklen(ip, 64)) AS ip FROM atkIps WHERE family(ip) = 6 ORDER BY ip ASC');
} else {
    $atk_list4 = pg_query($db, 'SELECT ip FROM atkIps WHERE family(ip) = 4 ORDER BY ip ASC');
    $atk_list6 = pg_query($db, 'SELECT ip FROM atkIps WHERE family(ip) = 6 ORDER BY ip ASC');
}


header('Content-Type: text/plain; charset=utf-8');

print("# Attack detected IPs ($range, infinite)");

for ($i = 0; $i < pg_num_rows($atk_list4); $i++) {
    $rows = pg_fetch_array($atk_list4, NULL, PGSQL_ASSOC);
    echo "\n" . $rows['ip'];
}

for ($i = 0; $i < pg_num_rows($atk_list6); $i++) {
    $rows = pg_fetch_array($atk_list6, NULL, PGSQL_ASSOC);
    echo "\n" . $rows['ip'];
}

closeDbLink($db);

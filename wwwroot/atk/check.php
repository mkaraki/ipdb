<?php
require_once __DIR__ . '/../_init.php';

if (!isset($_GET['ip'])) {
    http_response_code(400);
    die('Bad request');
}

$ip = $_GET['ip'];

if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    die('Non valid IP address');
}

$link = createDbLink();

$atkDb = pg_query_params($link, 'SELECT ip, extract(epoch from addedat) as addedat, extract(epoch from lastseen) as lastseen FROM atkIps WHERE ip = $1', [$ip]);
$inAtkDb = pg_num_rows($atkDb) > 0;

closeDbLink($link);

header('Content-Type: application/json');
echo json_encode(['result' => $inAtkDb]);
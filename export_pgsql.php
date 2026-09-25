<?php
# ============================================================
#  ipdb Migration tool (from Postgres (ver.1) to MariaDB (ver.2))
#
#  This is exporter. Place on old (ipdb v1) code root.
#  You cannot run this on v2 codebase.
# ============================================================

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/wwwroot/_init.php';

$db = createDbLink();

$query = pg_query($db, 'SELECT 
    ip,
    ccode,
    asn,
    extract(epoch from addedat) as addedat,
    extract(epoch from lastseen) as lastseen
FROM atkIps ORDER BY ip ASC');
$atkIps = pg_fetch_all($query, PGSQL_ASSOC);

$query = pg_query($db, 'SELECT 
    ip,
    rdns,
    extract(epoch from last_checked) as last_checked
FROM meta_rdns');
$meta_rdns = pg_fetch_all($query, PGSQL_ASSOC);

$query = pg_query($db, 'SELECT * FROM atkDbIgnoreList');
$atkDbIgnoreList = pg_fetch_all($query, PGSQL_ASSOC);

closeDbLink($db);

$exportData = [
    'atkIps' => $atkIps,
    'meta_rdns' => $meta_rdns,
    'atkDbIgnoreList' => $atkDbIgnoreList
];

header('Content-Type: application/json');
echo json_encode($exportData);
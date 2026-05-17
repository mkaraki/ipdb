<?php
# ============================================================
#  ipdb Migration tool (from Postgres to MariaDB)
#
# NOTE: You must confirm latest DB schema (202408310001.sql) has been applied
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
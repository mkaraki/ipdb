<?php
# ============================================================
#  ipdb Migration tool (from Postgres to MariaDB)
#
# NOTE: You must place `export.json` that exported with `export_pgsql.py` in the same directory as this script.
# ============================================================

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/src/DbProxy.php';
require_once __DIR__ . '/src/Atk/PostToAtk.php';

$exportFile = __DIR__ . '/export.json';

if (!file_exists($exportFile)) {
    die('export.json not found. Please export data from Postgres with export_pgsql.py and place the file in the same directory as this script.');
}

$file = file_get_contents($exportFile);

if ($file === false) {
    die('Failed to read export.json. Please check the file permissions and try again.');
}

$fileSize = filesize($exportFile);

print('Loaded file content from export.json...' . PHP_EOL);
print($fileSize . ' bytes loaded.' . PHP_EOL);
print('Preview: ' . substr($file, 0, 15) . '...' . substr($file, $fileSize - 16) . PHP_EOL);

if (str_starts_with($file, 'Content-Type: application/json')) {
    unset($file);
    $f = fopen($exportFile, 'r');
    if ($f === false) {
        die('Failed to open export.json for reading. Please check the file permissions and try again.');
    }
    $skipLen = strlen('Content-Type: application/json') + 4; // Header + 2 newline (CRLF)
    fseek($f, $skipLen);
    $file = fread($f, $fileSize - $skipLen);
    fclose($f);

    $fileSize = strlen($file);

    print('Removed Content-Type header from file content.' . PHP_EOL);
    print($fileSize . ' bytes re-loaded.' . PHP_EOL);
    print('Preview: ' . substr($file, 0, 15) . '...' . substr($file, $fileSize - 16) . PHP_EOL);
}

// Set memory limit to $fileSize * 3
ini_set('memory_limit', strval($fileSize * 10));

$exportData = json_decode($file, true, 4, JSON_THROW_ON_ERROR);
unset($file);
unset($fileSize);

if ($exportData === null) {
    die('Failed to decode JSON from export.json. Please check the file content and try again.' . PHP_EOL);
}

echo('Exported data summary:' . PHP_EOL);

$atkIpsCount = count($exportData['atkIps']);
$metaRdnsCount = count($exportData['meta_rdns']);
$atkDbIgnoreListCount = count($exportData['atkDbIgnoreList']);

print('atkIps: ' . $atkIpsCount . ' entries' . PHP_EOL);
print('meta_rdns: ' . $metaRdnsCount . ' entries' . PHP_EOL);
print('atkDbIgnoreList: ' . $atkDbIgnoreListCount . ' entries' . PHP_EOL);

$db = db_init();
if (!check_schema_version($db)) {
    die('Database schema version mismatch. Please run the migration on a fresh database or update the schema version in the code.');
}

$import_start = time();

query_params($db, 'DELETE FROM atkIps');
foreach ($exportData['atkIps'] as $i => $atkIp) {
    try {
        query_params($db, 'INSERT INTO atkIps (ip, ccode, asn, addedat, lastseen) VALUES (?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))', 'sssii', [
            formatIpForDb($atkIp['ip']),
            $atkIp['ccode'],
            $atkIp['asn'],
            intval($atkIp['addedat']),
            intval($atkIp['lastseen'])
        ]);
    } catch (\Throwable $t) {
        print('ERROR: ' . $t->getMessage() . PHP_EOL);
    }
    if ($i % 200 === 0)
        print('atkIps...' . $i . '/' . $atkIpsCount . "\r");
}
unset($exportData['atkIps']);

print(PHP_EOL . 'Done.' . PHP_EOL);

query_params($db, 'DELETE FROM meta_rdns');
foreach ($exportData['meta_rdns'] as $i => $rdns) {
    try {
        query_params($db, 'INSERT INTO meta_rdns (ip, rdns, last_checked) VALUES (?, ?, FROM_UNIXTIME(?))', 'ssi', [
            formatIpForDb($rdns['ip']),
            $rdns['rdns'],
            intval($rdns['last_checked'])
        ]);
    } catch (\Throwable $t) {
        print('ERROR: ' . $t->getMessage() . PHP_EOL);
    }
    if ($i % 200 === 0)
        print('meta_rdns...' . $i . '/' . $metaRdnsCount . "\r");
}
unset($exportData['meta_rdns']);

print(PHP_EOL . 'Done.' . PHP_EOL);

query_params($db, 'DELETE FROM atkDbIgnoreList');
foreach ($exportData['atkDbIgnoreList'] as $i => $ignore) {
    $ipSubnet = $ignore['net'];
    if (str_contains($ipSubnet, '/')) {
        $ipSubnetParts = explode('/', $ipSubnet);
        $ipNetwork = $ipSubnetParts[0];
        $ipCidr = $ipSubnetParts[1];
    } else {
        $ipNetwork = $ipSubnet;
        $ipCidr = isIP4($ipSubnet) ? 32 : 128;
    }

    try {
        query_params($db, 'INSERT INTO atkDbIgnoreList (network, cidr, description) VALUES (?, ?, ?)', 'sis', [
            formatIpForDb($ipNetwork),
            $ipCidr,
            $ignore['description']
        ]);
    }
    catch (\Throwable $t) {
        print('ERROR: ' . $t->getMessage() . PHP_EOL);
    }

    if ($i % 200 === 0)
        print('atkDbIgnoreList...' . $i . '/' . $atkDbIgnoreListCount . "\r");
}
unset($exportData['atkDbIgnoreList']);

print(PHP_EOL . 'Done.' . PHP_EOL);

$import_end = time();

$import_took = $import_end - $import_start;

print('Import took ' . $import_took . ' seconds.' . PHP_EOL);

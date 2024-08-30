<?php
require_once __DIR__ . '/../_init.php';

function updateAtkIpGeoCountryCode($db, $reader, $ip): void
{
    try {
        $cityData = $reader['cityDb']->city($ip);
    }
    catch (Exception) {
        pg_query_params($db, 'UPDATE atkIps SET ccode = NULL WHERE ip = $1', [$ip]);
        return;
    }

    $countryCode = $cityData->country->isoCode ?? null;

    pg_query_params($db, 'UPDATE atkIps SET ccode = $1 WHERE ip = $2', [$countryCode, $ip]);
}

function updateAtkIpGeoAsn($db, $reader, $ip): void
{
    try {
        $asnData = $reader['asnDb']->asn($ip);
    }
    catch (Exception) {
        pg_query_params($db, 'UPDATE atkIps SET asn = NULL WHERE ip = $1', [$ip]);
        return;
    }

    $asn = $asnData->autonomousSystemNumber ?? null;

    pg_query_params($db, 'UPDATE atkIps SET asn = $1 WHERE ip = $2', [$asn,  $ip]);
}

function updateAtkIpGeoMetadata($db, $reader, $ip): void
{
    updateAtkIpGeoCountryCode($db, $reader, $ip);
    updateAtkIpGeoAsn($db, $reader, $ip);
}

$rpt_time = time();

if (!isset($_POST['role_mgr'])) {
    authBasic(USER_ATK_REPORTER);
} else {
    authBasic(USER_ATK_MANAGER);
    if (isset($_POST['loggedat']) && is_numeric($_POST['loggedat'])) {
        // Has Logged at data

        if ($_POST['loggedat'] < 946684800 /* 2000-01-01 */ || $_POST['loggedat'] > time()) {
            // Invalid timestamp

            http_response_code(400);
            die('Invalid timestamp');
        }

        $rpt_time = intval($_POST['loggedat']);
    }
}

$noredirect = isset($_POST['noredirect']) && $_POST['noredirect'] === '1';

$db = createDbLink();

if (!isset($_POST['ip']) || !filter_var($_POST['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
    http_response_code(400);
    die('Bad request');
}

$ip = $_POST['ip'];

$atk_list = pg_query($db, "SELECT ip, extract(epoch from lastseen) as lastseen FROM atkIps WHERE ip = '$ip'");

if (!$noredirect)
    http_response_code(303);

if (pg_num_rows($atk_list) > 0) {
    $already_row = pg_fetch_row($atk_list, NULL, PGSQL_ASSOC);
    if ($already_row['lastseen'] >= $rpt_time) {
        if (!$noredirect)
            header('Location: /atk/list.php?pj_status=0&pj_msg=No+update');

        die('No update');
    } else {
        pg_query($db, "UPDATE atkIps SET lastseen = to_timestamp($rpt_time) WHERE ip = '$ip'");

        updateReverseDnsInfo($db, $ip);
        $geoReader = prepareIpGeoReader();
        updateAtkIpGeoMetadata($db, $geoReader, $ip);

        if (!$noredirect)
            header('Location: /atk/list.php?pj_status=0&pj_msg=Updated+database');

        die('Updated database');
    }
}

updateReverseDnsInfo($db, $ip);

pg_query($db, "INSERT INTO atkIps (ip, addedat, lastseen) VALUES ('$ip', to_timestamp($rpt_time), to_timestamp($rpt_time))");

$geoReader = prepareIpGeoReader();
updateAtkIpGeoMetadata($db, $geoReader, $ip);

closeDbLink($db);

if (!$noredirect)
    header('Location: /atk/list.php?pj_status=0&pj_msg=Added+to+list');

die('Added to list');

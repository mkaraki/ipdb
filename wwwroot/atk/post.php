<?php
require_once __DIR__ . '/../_init.php';

$transactionContext = \Sentry\Tracing\TransactionContext::make()
    ->setName('atk/post.php')
    ->setOp('http.server');
$transaction = \Sentry\startTransaction($transactionContext);
\Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

$rpt_time = time();

$spanContext = \Sentry\Tracing\SpanContext::make()
    ->setOp('login');
$loginSpan = $transaction->startChild($spanContext);
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
$loginSpan->finish();

if (empty($_POST['ip'])) {
    http_response_code(400);
    die("Bad request. Empty IP request.");
}

$_POST['ip'] = trim($_POST['ip']);

if (!filter_var($_POST['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
    http_response_code(400);
    die('Bad request. Invalid IP: ' . $_POST['ip']);
}

function updateAtkIpGeoCountryCode($db, $reader, $ip): void
{
    global $transaction;

    $spanContext = \Sentry\Tracing\SpanContext::make()
        ->setOp('update.geo.country');
    $span = $transaction->startChild($spanContext);

    if (!isset($reader['cityDb']))
    {
        $span->finish();
        return;
    }

    try {
        $cityData = $reader['cityDb']->city($ip);
    }
    catch (\GeoIp2\Exception\AddressNotFoundException $ex) {
        return;
    }
    catch (Exception $ex) {
        if (IS_SENTRY_USABLE) {
            \Sentry\captureException($ex);
        }
        pg_query_params($db, 'UPDATE atkIps SET ccode = NULL WHERE ip = $1', [$ip]);
        $span->finish();
        return;
    }

    $countryCode = $cityData->country->isoCode ?? null;

    pg_query_params($db, 'UPDATE atkIps SET ccode = $1 WHERE ip = $2', [$countryCode, $ip]);

    $span->finish();
}

function updateAtkIpGeoAsn($db, $reader, $ip): void
{
    global $transaction;

    $spanContext = \Sentry\Tracing\SpanContext::make()
        ->setOp('update.geo.asn');
    $span = $transaction->startChild($spanContext);

    if (!isset($reader['asnDb'])) {
        $span->finish();
        return;
    }

    try {
        $asnData = $reader['asnDb']->asn($ip);
    }
    catch (\GeoIp2\Exception\AddressNotFoundException $ex) {
        return;
    }
    catch (Exception $ex) {
        if (IS_SENTRY_USABLE)
        {
            \Sentry\captureException($ex);
        }
        pg_query_params($db, 'UPDATE atkIps SET asn = NULL WHERE ip = $1', [$ip]);
        $span->finish();
        return;
    }

    $asn = $asnData->autonomousSystemNumber ?? null;

    pg_query_params($db, 'UPDATE atkIps SET asn = $1 WHERE ip = $2', [$asn,  $ip]);

    $span->finish();
}

function updateAtkIpGeoMetadata($db, $reader, $ip): void
{
    updateAtkIpGeoCountryCode($db, $reader, $ip);
    updateAtkIpGeoAsn($db, $reader, $ip);
}

$noredirect = isset($_POST['noredirect']) && $_POST['noredirect'] === '1';

$ip = $_POST['ip'];
$ip = strtolower(trim($ip));
// Normalize IP address
$ip = inet_ntop(inet_pton($ip));

if (apcu_exists("atk_posted_{$ip}")) {
    if (ATK_SKIP_UPDATE_ON_CACHE_HIT) {
        // Skip update
        if (!$noredirect)
            header('Location: /atk/list.php?pj_status=0&pj_msg=Skip+update+(cached)');

        $transaction->finish();
        die ("Skip update (cached)");
    } else {
        $last_posted = apcu_fetch("atk_posted_{$ip}");
        if ($rpt_time <= $last_posted) {
            if (!$noredirect)
                header('Location: /atk/list.php?pj_status=0&pj_msg=No+update+(cached)');

            $transaction->finish();
            die('No update (cached)');
        } else {
            // ToDo: remove duplicated code.
            // This code is same as non cache hit updated code.

            // Create DB connection if update is needed.
            // Because global db link is created after cached section.
            $db = createDbLink();

            pg_query_params($db, 'UPDATE atkIps SET lastseen = to_timestamp($1) WHERE ip = $2', [$rpt_time, $ip]);

            updateReverseDnsInfo($db, $ip);
            $geoReader = prepareIpGeoReader();
            updateAtkIpGeoMetadata($db, $geoReader, $ip);

            if (!$noredirect)
                header('Location: /atk/list.php?pj_status=0&pj_msg=Updated+database');

            apcu_store("atk_posted_{$ip}", $rpt_time, ATK_POST_CACHE_AGE);

            $transaction->finish();
            die('Updated database');
        }
    }
}

if (apcu_exists("atk_ignore_{$ip}")) {
    if (!$noredirect)
        header('Location: /atk/list.php?pj_status=0&pj_msg=No+update+(ignored,+cached)');

    // Do not update cache age.
    // This cause un-sync between cache and database.

    $transaction->finish();
    die('No update (in ignore list, cached)');
}

$db = createDbLink();

$spanContext =  \Sentry\Tracing\SpanContext::make()
    ->setOp('check.atk.ignore.db');
$span = $transaction->startChild($spanContext);
$ignore_check = pg_query_params($db, 'SELECT il.id FROM atkDbIgnoreList il WHERE ($1)::inet << il.net', [$ip]);
if (pg_num_rows($ignore_check) > 0) {
    apcu_store("atk_ignore_{$ip}", true, ATK_POST_CACHE_AGE);

    if (!$noredirect)
        header('Location: /atk/list.php?pj_status=0&pj_msg=No+update+(ignored)');

    $span->finish();
    $transaction->finish();
    die('No update (in ignore list)');
}
$span->finish();

$atk_list = pg_query_params($db, 'SELECT ip, extract(epoch from lastseen) as lastseen FROM atkIps WHERE ip = $1', [$ip]);

if (!$noredirect)
    http_response_code(303);

if (pg_num_rows($atk_list) > 0) {
    $already_row = pg_fetch_row($atk_list, NULL, PGSQL_ASSOC);
    if ($already_row['lastseen'] >= $rpt_time) {
        if (!$noredirect)
            header('Location: /atk/list.php?pj_status=0&pj_msg=No+update');

        apcu_store("atk_posted_{$ip}", $rpt_time, ATK_POST_CACHE_AGE);

        $transaction->finish();
        die('No update');
    } else {
        pg_query_params($db, 'UPDATE atkIps SET lastseen = to_timestamp($1) WHERE ip = $2', [$rpt_time, $ip]);

        updateReverseDnsInfo($db, $ip);
        $geoReader = prepareIpGeoReader();
        updateAtkIpGeoMetadata($db, $geoReader, $ip);

        if (!$noredirect)
            header('Location: /atk/list.php?pj_status=0&pj_msg=Updated+database');

        apcu_store("atk_posted_{$ip}", $rpt_time, ATK_POST_CACHE_AGE);

        $transaction->finish();
        die('Updated database');
    }
}

updateReverseDnsInfo($db, $ip);

pg_query_params($db, 'INSERT INTO atkIps (ip, addedat, lastseen) VALUES ($1, to_timestamp($2), to_timestamp($2))', [$ip, $rpt_time]);

$geoReader = prepareIpGeoReader();
updateAtkIpGeoMetadata($db, $geoReader, $ip);

closeDbLink($db);

if (!$noredirect)
    header('Location: /atk/list.php?pj_status=0&pj_msg=Added+to+list');

apcu_store("atk_posted_{$ip}", $rpt_time, ATK_POST_CACHE_AGE);

$transaction->finish();
die('Added to list');

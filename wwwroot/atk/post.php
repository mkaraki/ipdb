<?php
require_once __DIR__ . '/../_init.php';

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

        if (!$noredirect)
            header('Location: /atk/list.php?pj_status=0&pj_msg=Updated+database');

        die('Updated database');
    }
}

$rdns = gethostbyaddr($_POST['ip']);
if ($rdns !== false && $rdns !== $ip && filter_var($rdns, FILTER_VALIDATE_DOMAIN)) {
    pg_query($db, "INSERT INTO meta_rdns (ip, rdns, last_checked) VALUES ('$ip', '$rdns', NOW()::timestamp)");
}

pg_query($db, "INSERT INTO atkIps (ip, addedat, lastseen) VALUES ('$ip', to_timestamp($rpt_time), to_timestamp($rpt_time))");

closeDbLink($db);

if (!$noredirect)
    header('Location: /atk/list.php?pj_status=0&pj_msg=Added+to+list');

die('Added to list');

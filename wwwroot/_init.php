<?php
require_once __DIR__ . '/../_config.php';

function createDbLink()
{
    $link = pg_connect(DB_CONSTR);

    if (!$link) {
        http_response_code(500);
        die('DB error');
    }

    return $link;
}

function closeDbLink($link)
{
    pg_close($link);
}

function authBasic($userList, $ret = 'plain')
{
    global $_SERVER;
    if (
        !isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) ||
        !isset($userList[$_SERVER['PHP_AUTH_USER']]) ||
        password_verify($_SERVER['PHP_AUTH_PW'], $userList[$_SERVER['PHP_AUTH_USER']]) === false
    ) {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="ipdb"');
        die('Authentication Required');
    }
}

function strDate($unixtime): string
{
    if (!is_numeric($unixtime)) {
        return '';
    }
    return date('Y-m-d H:i:s a', intval($unixtime));
}

function getReverseDnsInfo($link, $ip): array|null
{
    $query_rdns = pg_query($link, "SELECT rdns, extract(epoch from last_checked) as last_checked FROM meta_rdns WHERE ip = '$ip' ORDER BY last_checked DESC LIMIT 1");
    $has_meta_rdns = pg_num_rows($query_rdns) > 0;
    $meta_rdns_data = pg_fetch_array($query_rdns, NULL, PGSQL_ASSOC);

    // Return array info:
    // ['rdns'] => rdns host, string|null
    // ['last_checked'] => last checked timestamp (unix epoch), int|null
    return $has_meta_rdns ? $meta_rdns_data : null;
}

const TTL_REVERSE_DNS = 604_800; // 1 week

function updateReverseDnsInfo($link, $ip) {
    $db_rdns = getReverseDnsInfo($link, $ip);

    if ($db_rdns !== null && $db_rdns['last_checked'] > time() - TTL_REVERSE_DNS) {
        return;
    }

    $rdns = gethostbyaddr($ip);
    if ($rdns !== false && $rdns !== $ip && filter_var($rdns, FILTER_VALIDATE_DOMAIN)) {
        if ($db_rdns === null) {
            // Insert new record if DB doesn't have it
            pg_query($link, "INSERT INTO meta_rdns (ip, rdns, last_checked) VALUES ('$ip', '$rdns', NOW()::timestamp)");
        }
        else if ($db_rdns['rdns'] !== $rdns)
        {
            // Update record if DB has it and it's different
            pg_query($link, "UPDATE meta_rdns SET last_checked = NOW()::timestamp, rdns = '$rdns' WHERE ip = '$ip'");
        }
        else
        {
            // Update last checked time if DB has it and it's same
            pg_query($link, "UPDATE meta_rdns SET last_checked = NOW()::timestamp WHERE ip = '$ip'");
        }
    }
    else
    {
        if ($db_rdns === null) {
            // Insert new record if DB doesn't have it
            pg_query($link, "INSERT INTO meta_rdns (ip, rdns, last_checked) VALUES ('$ip', NULL, NOW()::timestamp)");
        }
        else
        {
            // Update last_checked and keep NULL rdns when DB has it
            pg_query($link, "UPDATE meta_rdns SET last_checked = NOW()::timestamp, rdns = NULL WHERE ip = '$ip'");
        }
    }
};
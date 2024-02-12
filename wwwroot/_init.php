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
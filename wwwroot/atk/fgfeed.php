<?php
require_once __DIR__ . '/../_init.php';
$db = createDbLink();

$range = $_GET['range'] ?? 'host';
if ($range !== 'net') $range = 'host';

if (isset($_GET['family']))
{
    $families = [];

    if (preg_match('/^[a-z0-9,]+$/', $_GET['family']))
    {
        $families_process = explode(',', $_GET['family']);

        foreach ($families_process as $family)
        {
            if (!in_array($family, ['ipv4', 'ipv6']))
            {
                http_response_code(400);
                die('Bad request');
            }

            $families[] = $family;
        }
    }

    if (empty($families))
    {
        http_response_code(400);
        die('Bad request');
    }
}
else
{
    $families = ['ipv4', 'ipv6'];
}

if ($range === 'net') {
    if (in_array('ipv4', $families))
        $atk_list4 = pg_query($db, 'SELECT DISTINCT network(set_masklen(ip, 24)) AS ip FROM atkIps WHERE family(ip) = 4 ORDER BY ip ASC');
    if (in_array('ipv6', $families))
        $atk_list6 = pg_query($db, 'SELECT DISTINCT network(set_masklen(ip, 64)) AS ip FROM atkIps WHERE family(ip) = 6 ORDER BY ip ASC');
} else {
    if (in_array('ipv4', $families))
        $atk_list4 = pg_query($db, 'SELECT ip FROM atkIps WHERE family(ip) = 4 ORDER BY ip ASC');
    if (in_array('ipv6', $families))
        $atk_list6 = pg_query($db, 'SELECT ip FROM atkIps WHERE family(ip) = 6 ORDER BY ip ASC');
}


header('Content-Type: text/plain; charset=utf-8');

print("# Attack detected IPs ($range, infinite)");

if (in_array('ipv4', $families))
{
    for ($i = 0; $i < pg_num_rows($atk_list4); $i++) {
        $rows = pg_fetch_array($atk_list4, NULL, PGSQL_ASSOC);
        echo "\n" . $rows['ip'];
    }
}

if (in_array('ipv6', $families))
{
    for ($i = 0; $i < pg_num_rows($atk_list6); $i++) {
        $rows = pg_fetch_array($atk_list6, NULL, PGSQL_ASSOC);
        echo "\n" . $rows['ip'];
    }
}

closeDbLink($db);

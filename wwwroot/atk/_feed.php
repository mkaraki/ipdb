<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../_ipcombine.php';
$db = createDbLink();

$range = $_GET['range'] ?? 'host';
if ($range !== 'smart' && $range !== 'net') $range = 'host';

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

if ($range === 'smart') {
    if (in_array('ipv4', $families))
        $atk_list4 = pg_query($db, 'SELECT CASE WHEN COUNT(*) >= 2 THEN network(set_masklen(ip, 24)) ELSE MIN(ip) END AS ip FROM atkIps WHERE family(ip) = 4 GROUP BY network(set_masklen(ip, 24)) ORDER BY ip ASC');
    if (in_array('ipv6', $families))
        $atk_list6 = pg_query($db, 'SELECT CASE WHEN COUNT(*) >= 2 THEN network(set_masklen(ip, 64)) ELSE MIN(ip) END AS ip FROM atkIps WHERE family(ip) = 6 GROUP BY network(set_masklen(ip, 64)) ORDER BY ip ASC');
} else if ($range === 'net') {
    if (in_array('ipv4', $families))
        $atk_list4 = pg_query($db, 'SELECT DISTINCT network(set_masklen(ip, 24)) as ip FROM atkIps WHERE family(ip) = 4 ORDER BY ip ASC');
    if (in_array('ipv6', $families))
        $atk_list6 = pg_query($db, 'SELECT DISTINCT network(set_masklen(ip, 64)) as ip FROM atkIps WHERE family(ip) = 6  ORDER BY ip ASC');
} else {
    if (in_array('ipv4', $families))
        $atk_list4 = pg_query($db, 'SELECT ip FROM atkIps WHERE family(ip) = 4 ORDER BY ip ASC');
    if (in_array('ipv6', $families))
        $atk_list6 = pg_query($db, 'SELECT ip FROM atkIps WHERE family(ip) = 6 ORDER BY ip ASC');
}

$ip4_list = [];
$ip6_list = [];

if (in_array('ipv4', $families) && isset($atk_list4)) {
    $ip4_list = pg_fetch_all_columns($atk_list4, 0);
    if (in_array($range, ['smart', 'net'])) {
        // If not cidr, add `/32` to each ip
        foreach ($ip4_list as $key => $ip) {
            if (!str_contains($ip, '/')) {
                $ip4_list[$key] .= '/32';
            }
        }
        $ip4_list = recursiveCombineAdjacentSubnets($ip4_list);
    }
}
if (in_array('ipv6', $families) && isset($atk_list6)) {
    $ip6_list = pg_fetch_all_columns($atk_list6, 0);
}

closeDbLink($db);

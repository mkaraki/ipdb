<?php
require_once __DIR__ . '/../_init.php';
$db = createDbLink();

$range = 'host';

$families = ['ipv4'];

/*if (isset($_GET['family']))
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
}*/

/*if ($range === 'smart') {
    if (in_array('ipv4', $families))
        $atk_list4 = pg_query($db, 'SELECT CASE WHEN COUNT(*) >= 2 THEN network(set_masklen(ip, 24)) ELSE MIN(ip) END AS ip FROM atkIps WHERE family(ip) = 4 GROUP BY network(set_masklen(ip, 24)) ORDER BY ip ASC');
    if (in_array('ipv6', $families))
        $atk_list6 = pg_query($db, 'SELECT CASE WHEN COUNT(*) >= 2 THEN network(set_masklen(ip, 64)) ELSE MIN(ip) END AS ip FROM atkIps WHERE family(ip) = 6 GROUP BY network(set_masklen(ip, 64)) ORDER BY ip ASC');
} else if ($range === 'net') {
    if (in_array('ipv4', $families))
        $atk_list4 = pg_query($db, 'SELECT DISTINCT network(set_masklen(ip, 24)) as ip FROM atkIps WHERE family(ip) = 4 ORDER BY ip ASC');
    if (in_array('ipv6', $families))
        $atk_list6 = pg_query($db, 'SELECT DISTINCT network(set_masklen(ip, 64)) as ip FROM atkIps WHERE family(ip) = 6  ORDER BY ip ASC');
} else { */
    if (in_array('ipv4', $families))
        $atk_list4 = pg_query($db, 'SELECT ip FROM atkIps WHERE family(ip) = 4 ORDER BY ip ASC');
    if (in_array('ipv6', $families))
        $atk_list6 = pg_query($db, 'SELECT ip FROM atkIps WHERE family(ip) = 6 ORDER BY ip ASC');
//}


header('Content-Type: text/plain; charset=utf-8');

$serial_date = date('YmdH', time());

print("\$TTL 86400
@   IN  SOA     ns.atk.ipdb.mkarakiapps.com. ipdb.mkarakiapps.com. (
        $serial_date ;Serial
        3600         ;Refresh
        1800         ;Retry
        86400        ;Expire
        86400        ;Minimum TTL
)

        IN  NS       ns.atk.ipdb.mkarakiapps.com
        IN  A        127.0.0.1
");

if (in_array('ipv4', $families) && isset($atk_list4))
{
    for ($i = 0; $i < pg_num_rows($atk_list4); $i++) {
        $rows = pg_fetch_array($atk_list4, NULL, PGSQL_ASSOC);
        $ip_octets = explode('.', $rows['ip']);
        $blip = implode('.', array_reverse($ip_octets));
        echo "\n$blip IN A 127.0.0.2";
    }
}

/*if (in_array('ipv6', $families) && isset($atk_list6))
{
    for ($i = 0; $i < pg_num_rows($atk_list6); $i++) {
        $rows = pg_fetch_array($atk_list6, NULL, PGSQL_ASSOC);
        echo "\n" . $rows['ip'] . ' REJECT';
    }
}*/

closeDbLink($db);

<?php
global $range, $ip4_list, $ip6_list;
require_once __DIR__ . '/_feed.php';

header('Content-Type: text/plain; charset=utf-8');

print("# Attack detected IPs ($range, infinite)\n");
print("#\n");
print("# This product includes GeoLite2 data created by MaxMind, available from https://www.maxmind.com\n");
print("#\n");

foreach ($ip4_list as $ip)
{
    print($ip . "\n");
}

foreach ($ip6_list as $ip)
{
    print($ip . "\n");
}

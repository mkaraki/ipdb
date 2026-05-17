<?php
require_once __DIR__ . '/../IpCombine.php';
require_once __DIR__ . '/../IpAccessUtils.php';

function optimizeIPv4Subnets($ipList): array {
    $parent = \Sentry\SentrySdk::getCurrentHub()->getSpan();
    $span = null;
    if ($parent !== null) {
        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('optimize.ip4')
            ->setDescription("Optimize IPv4 subnet with mode " . (defined('ATK_FEED_OPTIMIZE_LEVEL') ? ATK_FEED_OPTIMIZE_LEVEL : 'undefined'))
            ->setData([
                'optimize_level' => defined('ATK_FEED_OPTIMIZE_LEVEL') ? ATK_FEED_OPTIMIZE_LEVEL : 'undefined'
            ]);
        $span = $parent->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);
    }

    try {
        foreach ($ipList as $key => $ip) {
            if (!str_contains($ip, '/')) {
                $ipList[$key] .= '/32';
            }
        }

        switch (defined('ATK_FEED_OPTIMIZE_LEVEL') ? ATK_FEED_OPTIMIZE_LEVEL : null) {
            case 2:
            case 3:
                return recursiveCombineAdjacentSubnets($ipList);
            default:
                return $ipList;
        }
    } finally {
        if ($span !== null) {
            $span->finish();
            \Sentry\SentrySdk::getCurrentHub()->setSpan($parent);
        }
    }
}

function getAtkFeedV4Data($link, $range, $sinceUnixTime): array {
    // Now, ignores $dayRange.

    if ($range === 'net') {
        $atk_list4 = query_all_params($link, "
SELECT DISTINCT
    INET6_NTOA(CONCAT(SUBSTRING(INET6_ATON(ip), 1, LENGTH(INET6_ATON(ip)) - 1), UNHEX('00'))) AS ip
FROM
    atkIps
WHERE
    INET6_ATON(ip) BETWEEN
        INET6_ATON('::FFFF:0000:0000')
        AND
        INET6_ATON('::FFFF:FFFF:FFFF') AND
    UNIX_TIMESTAMP(lastseen) >= ?
ORDER BY NULL", 'i', [$sinceUnixTime]);

        for ($i = 0; $i < count($atk_list4); $i++) {
            $atk_list4[$i]['ip'] = formatDbIpForUser($atk_list4[$i]['ip']) . '/24';
        }
    } else if ($range === 'smart') {
        $atk_list4 = query_all_params($link, "
SELECT 
    CASE 
        WHEN COUNT(*) >= 2 THEN
            INET6_NTOA(CONCAT(SUBSTRING(INET6_ATON(MIN(ip)), 1, LENGTH(INET6_ATON(MIN(ip))) - 1), UNHEX('00')))
        ELSE MIN(ip)
    END AS ip,
    CASE
        WHEN COUNT(*) >= 2 THEN 24
        ELSE 32
    END AS cidr
FROM atkIps
WHERE
    INET6_ATON(ip) BETWEEN
        INET6_ATON('::FFFF:0000:0000')
        AND
        INET6_ATON('::FFFF:FFFF:FFFF') AND
    UNIX_TIMESTAMP(lastseen) >= ?
GROUP BY SUBSTRING(INET6_ATON(ip), 1, LENGTH(INET6_ATON(ip)) - 1)
ORDER BY NULL", 'i', [$sinceUnixTime]);

        for ($i = 0; $i < count($atk_list4); $i++) {
            $atk_list4[$i]['ip'] = formatDbIpForUser($atk_list4[$i]['ip']) . '/' . $atk_list4[$i]['cidr'];
        }
    } else {
        // Now, smart will stop to work.
        $atk_list4 = query_all_params($link, "
SELECT DISTINCT
    ip
FROM
    atkIps
WHERE
    INET6_ATON(ip) BETWEEN
        INET6_ATON('::FFFF:0000:0000')
        AND
        INET6_ATON('::FFFF:FFFF:FFFF') AND
    UNIX_TIMESTAMP(lastseen) >= ?
ORDER BY NULL", 'i', [$sinceUnixTime]);

        for ($i = 0; $i < count($atk_list4); $i++) {
            $atk_list4[$i]['ip'] = formatDbIpForUser($atk_list4[$i]['ip']);
        }
    }

    return array_column($atk_list4, 'ip');
}

function getAtkFeedV6Data($link, $range, $sinceUnixTime): array {
    // Now, ignores $dayRange.

    if ($range === 'net') {
        $atk_list6 = query_all_params($link, "
SELECT DISTINCT
    INET6_NTOA(CONCAT(SUBSTRING(INET6_ATON(ip), 1, 8), UNHEX('0000000000000000'))) AS ip
FROM
    atkIps
WHERE
    INET6_ATON(ip) NOT BETWEEN
        INET6_ATON('::FFFF:0000:0000')
        AND
        INET6_ATON('::FFFF:FFFF:FFFF') AND
    UNIX_TIMESTAMP(lastseen) >= ?
ORDER BY NULL", 'i', [$sinceUnixTime]);

        for ($i = 0; $i < count($atk_list6); $i++) {
            $atk_list6[$i]['ip'] = $atk_list6[$i]['ip'] . '/64';
        }
    } else if ($range === 'smart') {
        $atk_list6 = query_all_params($link, "
SELECT 
    CASE 
        WHEN COUNT(*) >= 2 THEN
            INET6_NTOA(CONCAT(SUBSTRING(INET6_ATON(MIN(ip)), 1, 8), UNHEX('0000000000000000')))
        ELSE MIN(ip)
    END AS ip,
    CASE
        WHEN COUNT(*) >= 2 THEN 64
        ELSE 128
    END AS cidr
FROM atkIps
WHERE
    INET6_ATON(ip) NOT BETWEEN
        INET6_ATON('::FFFF:0000:0000')
        AND
        INET6_ATON('::FFFF:FFFF:FFFF') AND
    UNIX_TIMESTAMP(lastseen) >= ?
GROUP BY SUBSTRING(INET6_ATON(ip), 1, 8)
ORDER BY NULL", 'i', [$sinceUnixTime]);

        for ($i = 0; $i < count($atk_list6); $i++) {
            $atk_list6[$i]['ip'] = $atk_list6[$i]['ip'] . '/' . $atk_list6[$i]['cidr'];
        }
    } else {
        $atk_list6 = query_all_params($link, "SELECT DISTINCT
    ip
FROM
    atkIps
WHERE
    INET6_ATON(ip) NOT BETWEEN
        INET6_ATON('::FFFF:0000:0000')
        AND
        INET6_ATON('::FFFF:FFFF:FFFF') AND
    UNIX_TIMESTAMP(lastseen) >= ?
ORDER BY NULL", 'i', [$sinceUnixTime]);
    }

    // Reformat is not needed because it is for IPv4 (remapped to IPv6)

    return array_column($atk_list6, 'ip');
}


function getAtkFeedData($link, $range, $family, $sinceUnixTime): array {
    $families = explode(',', trim($family));

    $includeIpv4 = in_array('ipv4', $families);
    $includeIpv6 = in_array('ipv6', $families);

    $atk_list = [];
    if ($includeIpv4) {
        $ipv4_list = getAtkFeedV4Data($link, $range, $sinceUnixTime);
        $ipv4_list = optimizeIPv4Subnets($ipv4_list);
        $atk_list = array_merge($atk_list, $ipv4_list);
    }
    if ($includeIpv6) {
        $atk_list = array_merge($atk_list, getAtkFeedV6Data($link, $range, $sinceUnixTime));
    }

    return $atk_list;
}
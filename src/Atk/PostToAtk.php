<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../DbProxy.php';
require_once __DIR__ . '/../IpAccessUtils.php';
require_once __DIR__ . '/../ReverseDns.php';
require_once __DIR__ . '/../GeoIpProxy.php';
require_once __DIR__ . '/../IpInSubnetCheck.php';

function updateAtkIpGeoCountryCode($db, $reader, $ip): void
{
    $parent = \Sentry\SentrySdk::getCurrentHub()->getSpan();
    $span = null;
    if ($parent !== null) {
        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('update.geo.country')
            ->setDescription('Update atkIps.ccode');
        $span = $parent->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);
    }

    try {
        if (!isset($reader['cityDb'])) {
            // Unable to update
            return;
        }

        try {
            $cityData = $reader['cityDb']->city($ip);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            // Usual error

            // Remove old country code
            $ip = formatIpForDb($ip);
            $_ = query_params($db, 'UPDATE atkIps SET ccode = NULL WHERE ip = ?', 's', [$ip]);

            return;
        } catch (\Throwable $e) {
            // Unknown error
            \Sentry\captureException($e);

            // Remove un-trusted old country code
            $ip = formatIpForDb($ip);
            $_ = query_params($db, 'UPDATE atkIps SET ccode = NULL WHERE ip = ?', 's', [$ip]);

            // Do not report GeoIP error. This is not important.
            return;
        }

        $countryCode = $cityData->country->isoCode ?? null;

        $ip = formatIpForDb($ip);
        $_ = query_params($db, 'UPDATE atkIps SET ccode = ? WHERE ip = ?', 'ss', [$countryCode, $ip]);

    } catch (\Throwable $e) {
        \Sentry\captureException($e);

        // This process is not important. Just report to Sentry is enough.
        return;
    } finally {
        if ($span !== null) {
            $span->finish();
            \Sentry\SentrySdk::getCurrentHub()->setSpan($parent);
        }
    }
}

function updateAtkIpGeoAsn($db, $reader, $ip): void {
    $parent = \Sentry\SentrySdk::getCurrentHub()->getSpan();
    $span = null;
    if ($parent !== null) {
        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('update.geo.asn')
            ->setDescription('Update atkIps.asn');
        $span = $parent->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);
    }

    try {
        if (!isset($reader['asnDb'])) {
            // Unable to update
            return;
        }

        try {
            $asnData = $reader['asnDb']->asn($ip);
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            // Usual error

            // Remove old country code
            $ip = formatIpForDb($ip);
            $_ = query_params($db, 'UPDATE atkIps SET asn = NULL WHERE ip = ?', 's', [$ip]);

            return;
        } catch (\Throwable $e) {
            // Unknown error
            \Sentry\captureException($e);

            // Remove un-trusted old country code
            $ip = formatIpForDb($ip);
            $_ = query_params($db, 'UPDATE atkIps SET asn = NULL WHERE ip = ?', 's', [$ip]);

            // Do not report GeoIP error. This is not important.
            return;
        }

        $asn = $asnData->autonomousSystemNumber ?? null;

        $ip = formatIpForDb($ip);
        $_ = query_params($db, 'UPDATE atkIps SET asn = ? WHERE ip = ?', 'is', [$asn, $ip]);
    } catch (\Throwable $e) {
        \Sentry\captureException($e);

        // This process is not important. Just report to Sentry is enough.
        return;
    } finally {
        if ($span !== null) {
            $span->finish();
            \Sentry\SentrySdk::getCurrentHub()->setSpan($parent);
        }
    }
}

function updateAtkIpGeoMetadata($db, $reader, $ip): void
{
    updateAtkIpGeoCountryCode($db, $reader, $ip);
    updateAtkIpGeoAsn($db, $reader, $ip);
}


// True if in ignore list/fail
function checkIpForIgnoredDb($db, string $ip, string $dbIp): bool {
    if (isIp4($ip)) {
        $res = query_result_params($db, "
SELECT network, cidr FROM atkDbIgnoreList
WHERE
    INET6_ATON(network) BETWEEN
        INET6_ATON('::FFFF:0000:0000')
        AND
        INET6_ATON('::FFFF:FFFF:FFFF')
ORDER BY NULL");

        if ($res === null) {
            return true;
        }

        $evalIp = ip2long($ip);

        // Loop for each row
        while ($row = $res->fetch_assoc()) {
            $network = $row['network'];
            $network = formatDbIpForUser($network);
            $cidr = $row['cidr'];

            $rawIp = ip2long($network);
            $subnetMask = (1 << (32 - $cidr)) - 1;

            $nwAddress = $rawIp & ~$subnetMask;
            $nwBroadcast = $rawIp | $subnetMask;

            if ($evalIp >= $nwAddress && $evalIp <= $nwBroadcast) {
                return true;
            }
        }

        return false;
    } else /* on IPv6*/ {
        $res = query_result_params($db, "
SELECT network, cidr FROM atkDbIgnoreList
WHERE
    INET6_ATON(network) NOT BETWEEN
        INET6_ATON('::FFFF:0000:0000')
        AND
        INET6_ATON('::FFFF:FFFF:FFFF')
ORDER BY NULL");

        if ($res === null) {
            return true;
        }

        // Loop for each row
        while ($row = $res->fetch_assoc()) {
            $network = $row['network'];
            // IPv6 isn't need to process Db format to usual format.
            $cidr = $row['cidr'];

            if (CheckIpInSubnet($ip, $network, $cidr)) {
                return true;
            }
        }

        return false;
    }
}

function postToAtkDatabase($db, $ip, $lastSeen) {
    $ip = normalizeIp($ip);
    $dbIp = formatIpForDb($ip);

    if (checkIpForIgnoredDb($db, $ip, $dbIp)) {
        db_close($db);
        \Sentry\logger()->info('Posted whitelisted IP', ['ip' => $ip]);
        return;
    }

    $atkList = query_row_params($db, "SELECT id, ip, UNIX_TIMESTAMP(lastseen) as lastseen FROM atkIps WHERE ip = ? LIMIT 1", 's', [$dbIp]);

    if ($atkList === false) {
        \Sentry\logger()->warn('Failed to query atkIps for IP', ['ip' => $ip]);
        return;
    }
    else if ($atkList !== null) {
        $rpt_time = $lastSeen - ATK_POST_GEO_INFO_CACHE_AGE;

        if ($atkList['lastseen'] < $rpt_time) {
            // ATKdb Information expired. Refresh it.
            $geoReader = prepareIpGeoReader();
            updateAtkIpGeoMetadata($db, $geoReader, $ip);
            updateReverseDnsInfo($db, $ip);
        }

        $success = query_params($db, 'UPDATE atkIps SET lastseen = FROM_UNIXTIME(?) WHERE id = ?', 'ii', [$lastSeen, $atkList['id']]);

        if ($success === false) {
            \Sentry\logger()->warn('Failed to update lastseen on ATKdb', ['ip' => $ip]);
            return;
        }
    } else {
        // Newly observed
        $success = query_params($db, "INSERT INTO atkIps (ip, addedat, lastseen) VALUES (?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))", 'sii', [$dbIp, $lastSeen, $lastSeen]);

        if ($success) {
            $geoReader = prepareIpGeoReader();
            updateAtkIpGeoMetadata($db, $geoReader, $ip);
            updateReverseDnsInfo($db, $ip);

            return;
        }

        \Sentry\logger()->warn('Unable to post newly observed ATK ip to db', ['ip' => $ip]);
        return;
    }
}

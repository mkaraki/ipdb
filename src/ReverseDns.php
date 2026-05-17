<?php
const TTL_REVERSE_DNS = 604_800; // 1 week

require_once __DIR__ . '/DbProxy.php';

function getReverseDnsInfo($link, $ip): array|null
{
    $ip = formatIpForDb($ip);
    $rdns_data = query_row_params($link, 'SELECT rdns, UNIX_TIMESTAMP(last_checked) AS last_checked FROM meta_rdns WHERE ip = ? ORDER BY last_checked DESC LIMIT 1', 's', [$ip]);

    if (empty($rdns_data)) {
        return null;
    }

    // Return array info:
    // ['rdns'] => rdns host, string|null
    // ['last_checked'] => last checked timestamp (unix epoch), int|null
    return $rdns_data;
}


function updateReverseDnsInfo($link, $ip): void
{
    $db_rdns = getReverseDnsInfo($link, $ip);

    if ($db_rdns !== null && $db_rdns['last_checked'] > time() - TTL_REVERSE_DNS) {
        return;
    }

    $rdns = gethostbyaddr($ip);
    $origIp = $ip;
    $ip = formatIpForDb($ip);

    if ($rdns !== false && $rdns !== $origIp && filter_var($rdns, FILTER_VALIDATE_DOMAIN)) {
        if ($db_rdns === null) {
            // Insert new record if DB doesn't have it
            $err = query_params($link, 'INSERT INTO meta_rdns (ip, rdns, last_checked) VALUES (?, ?, NOW())', 'ss', [$ip, $rdns]);
        }
        else if ($db_rdns['rdns'] !== $rdns)
        {
            // Update record if DB has it and it's different
            $err = query_params($link, 'UPDATE meta_rdns SET last_checked = NOW(), rdns = ? WHERE ip = ?', 'ss', [$rdns, $ip]);
        }
        else
        {
            // Update last checked time if DB has it and it's same
            $err = query_params($link, 'UPDATE meta_rdns SET last_checked = NOW() WHERE ip = ?', 'ss', [$ip]);
        }
    }
    else
    {
        if ($db_rdns === null) {
            // Insert new record if DB doesn't have it
            $err = query_params($link, 'INSERT INTO meta_rdns (ip, rdns, last_checked) VALUES (?, ?, NOW())', 'ss', [$ip, $rdns]);
        }
        else
        {
            // Update last_checked and keep NULL rdns when DB has it
            $err = query_params($link, 'UPDATE meta_rdns SET last_checked = NOW(), rdns = NULL WHERE ip = ?', 's', [$ip]);
        }
    }

    if (!$err /* This means error */) {
        \Sentry\captureMessage("Failed to update/insert reverse DNS info for IP: $ip", \Sentry\Severity::error());
    }
}

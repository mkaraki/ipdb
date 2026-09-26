<?php
const TTL_REVERSE_DNS = 604_800; // 1 week

require_once __DIR__ . '/DbProxy.php';
require_once __DIR__ . '/IpAccessUtils.php';

/**
 * Provides access to reverse DNS information stored in the database.
 */
class ReverseDnsRepository
{
    /**
     * Find the most recently checked reverse DNS record for an IP.
     *
     * @param mixed $link Database connection.
     */
    public function find($link, string $ip): ?array
    {
        $ip = formatIpForDb($ip);

        $rdnsData = query_row_params(
            $link,
            'SELECT rdns, UNIX_TIMESTAMP(last_checked) AS last_checked
             FROM meta_rdns
             WHERE ip = ?
             ORDER BY last_checked DESC
             LIMIT 1',
            's',
            [$ip]
        );

        if (empty($rdnsData)) {
            return null;
        }

        return $rdnsData;
    }

    /**
     * Insert a new reverse DNS record.
     *
     * @param mixed $link Database connection.
     */
    public function insert(
        $link,
        string $ip,
        ?string $rdns
    ): bool {
        $ip = formatIpForDb($ip);

        if ($rdns === null) {
            $err = query_params(
                $link,
                'INSERT INTO meta_rdns (ip, rdns, last_checked)
                 VALUES (?, NULL, NOW())',
                's',
                [$ip]
            );
        } else {
            $err = query_params(
                $link,
                'INSERT INTO meta_rdns (ip, rdns, last_checked)
                 VALUES (?, ?, NOW())',
                'ss',
                [$ip, $rdns]
            );
        }

        return (bool) $err;
    }

    /**
     * Update an existing reverse DNS record.
     *
     * @param mixed $link Database connection.
     */
    public function update(
        $link,
        string $ip,
        ?string $rdns
    ): bool {
        $ip = formatIpForDb($ip);

        if ($rdns === null) {
            $err = query_params(
                $link,
                'UPDATE meta_rdns
                 SET last_checked = NOW(), rdns = NULL
                 WHERE ip = ?',
                's',
                [$ip]
            );
        } else {
            $err = query_params(
                $link,
                'UPDATE meta_rdns
                 SET last_checked = NOW(), rdns = ?
                 WHERE ip = ?',
                'ss',
                [$rdns, $ip]
            );
        }

        return (bool) $err;
    }

    /**
     * Update only the last_checked timestamp while keeping the hostname.
     *
     * @param mixed $link Database connection.
     */
    public function touch($link, string $ip): bool
    {
        $ip = formatIpForDb($ip);

        $err = query_params(
            $link,
            'UPDATE meta_rdns
             SET last_checked = NOW()
             WHERE ip = ?',
            's',
            [$ip]
        );

        return (bool) $err;
    }
}


/**
 * Resolves an IP address to its reverse DNS hostname.
 */
class ReverseDnsResolver
{
    /**
     * @return string|false
     */
    public function resolve(string $ip): string|false
    {
        return gethostbyaddr($ip);
    }
}


/**
 * Coordinates reverse DNS lookup and database caching.
 */
class ReverseDnsService
{
    public function __construct(
        private ReverseDnsRepository $repository,
        private ReverseDnsResolver $resolver,
    ) {
    }

    /**
     * Get cached reverse DNS information.
     *
     * @param mixed $link Database connection.
     */
    public function get($link, string $ip): ?array
    {
        return $this->repository->find($link, $ip);
    }

    /**
     * Update cached reverse DNS information when necessary.
     *
     * @param mixed $link Database connection.
     */
    public function update($link, string $ip): void
    {
        $dbRdns = $this->repository->find($link, $ip);

        // Cached value is still fresh.
        if (
            $dbRdns !== null &&
            $dbRdns['last_checked'] > time() - TTL_REVERSE_DNS
        ) {
            return;
        }

        // Keep the original IP for the DNS lookup.
        $originalIp = $ip;

        $rdns = $this->resolver->resolve($originalIp);

        /*
         * A valid reverse DNS result must:
         *
         * 1. Be different from the original IP.
         * 2. Be a valid domain name.
         */
        if (
            $rdns !== false &&
            $rdns !== $originalIp &&
            filter_var($rdns, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        ) {
            if ($dbRdns === null) {
                $success = $this->repository->insert(
                    $link,
                    $originalIp,
                    $rdns
                );
            } elseif ($dbRdns['rdns'] !== $rdns) {
                $success = $this->repository->update(
                    $link,
                    $originalIp,
                    $rdns
                );
            } else {
                $success = $this->repository->touch(
                    $link,
                    $originalIp
                );
            }
        } else {
            if ($dbRdns === null) {
                $success = $this->repository->insert(
                    $link,
                    $originalIp,
                    null
                );
            } else {
                $success = $this->repository->update(
                    $link,
                    $originalIp,
                    null
                );
            }
        }

        if (!$success) {
            $dbIp = formatIpForDb($originalIp);

            \Sentry\captureMessage(
                "Failed to update/insert reverse DNS info for IP: $dbIp",
                \Sentry\Severity::error()
            );
        }
    }
}
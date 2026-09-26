<?php
function getAccessingIp(\Psr\Http\Message\ServerRequestInterface $request): string|null {
    $cfHeader = null;
    if (defined('IS_CLOUDFLARE_PROXIED') && IS_CLOUDFLARE_PROXIED) {
        $cfHeader = $request->getHeader('CF-Connecting-IP')[0] ?? null;
    }
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

    $ret_ip = $cfHeader ?? $remoteAddr ?? null;
    if (empty($ret_ip)) {
        return null;
    }

    if (!filter_var($ret_ip, FILTER_VALIDATE_IP)) {
        return null;
    }

    return $ret_ip;
}

function validateIpIsPublic(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE);
}

function normalizeIp(string $ip): string {
    return inet_ntop(inet_pton($ip));
}

function formatIpForDb(string $ip): string {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return '::FFFF:' . $ip;
    } else if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return $ip;
    }

    return $ip;
}

function formatDbIpForUser(string $ip): string {
    $ip = strtolower($ip);
    if (str_starts_with($ip, '::ffff:')) {
        $remain_ip = substr($ip, 7);
        if (isIp4($remain_ip)) {
            return $remain_ip;
        }
    }
    return $ip;
}

function isIp4(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}
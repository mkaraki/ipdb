<?php
# Source: https://qiita.com/keitaromiura/items/984cef948668f06ba92c

function conevrt_addr_mask(string $ip, int $subnet): int|string
{
    $addr = inet_pton($ip);
    $len = 8 * strlen($addr);
    $mask = str_repeat('f', $subnet >> 2);
    switch ($subnet & 3) {
        case 1:
            $mask .= '8';
            break;
        case 2:
            $mask .= 'c';
            break;
        case 3:
            $mask .= 'e';
            break;
        default:
            break;
    }
    $mask = pack('H*', str_pad($mask, $len >> 2, '0'));
    $filt = $addr & $mask;
    return $filt;
}

function CheckIpInSubnet(string $needle, string $networkIp, int $cidr): bool {
    $chk_mask = conevrt_addr_mask($networkIp, $cidr);
    $ip_mask  = conevrt_addr_mask($needle, $cidr);
    if ($chk_mask === $ip_mask) {
        return true;
    }
    return false;
}
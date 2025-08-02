<?php

function format_ip4_obj($ip_obj): string
{
    $net = long2ip($ip_obj['network']);
    $cidr = $ip_obj['cidr'];
    return "$net/$cidr";
}

function create_v4_net(int $addr, int $cidr): array
{
    $ip_obj = [];

    $ip_obj['cidr'] = $cidr;
    $cidr_inv = 32 - $cidr;
    $full_mask = 0b11111111_11111111_11111111_11111111;
    $mask = $full_mask >> $cidr_inv << $cidr_inv;
    $mask_inv = (~$mask) & $full_mask;

    $network = $addr & $mask;
    $ip_obj['network'] = $network;

    $bcast = $network | $mask_inv;
    $ip_obj['broadcast'] = $bcast;

    return $ip_obj;
}

function ip4_combine($ip): array
{
    if (count($ip) < 1) {
        return [$ip, []];
    }

    $decided = [];
    $not_decided = [];

    for ($i = 0; $i < count($ip); $i ++)
    {
        if (!($i + 1 < count($ip))) {
            // If this is last, this cannot compare with next value.
            array_push($decided, $ip[$i]);
            break; // means continue; because this is last.
        }

        $s = $ip[$i];
        $l = $ip[$i + 1];

        // Check subnet is connected or not.
        // if not, there are no chance to combine

        if ($s['broadcast'] + 1 != $l['network']) {
            array_push($decided, $s);
            continue;
        }

        // If CIDR is different, this cannot combine.
        // So, skip

        if ($s['cidr'] != $l['cidr']) {
            array_push($not_decided, $s);
            continue;
        }

        // Try creating new subnet with larger network.
        // If network addr and broadcast addr is match to smaller and larger subnet,
        // this subnet can be merged.

        $try_subnet = create_v4_net($s['network'], $s['cidr'] - 1);

        if (
            ($try_subnet['network'] == $s['network']) &&
            ($try_subnet['broadcast'] == $l['broadcast'])
        ) {
            array_push($not_decided, $try_subnet);

            $i++;
            continue; // This 2 lines make finally $i += 2;
        }

        // else

        array_push($not_decided, $s);
        // continue;
    }

    return [$decided, $not_decided];
}

/*
 * Recursively combine subnets until no further changes occur
 * Example:
 *   recursiveCombineAdjacentSubnets(['192.168.0.0/24', '192.168.1.0/24']) => ['192.168.0.0/23']
 *
 * @param array $subnets List of subnets in IP/CIDR format
 * @return array List of subnets where adjacent subnets are combined
 */
function recursiveCombineAdjacentSubnets(array $subnets): array
{
    $nw_ips = [];
    $ips = [];
    foreach ($subnets as $content_line) {
        $cidr = 32;
        if (str_contains($content_line, '/')) {
            $cidr_str = explode('/', $content_line);
            $content_line = $cidr_str[0];
            $cidr = intval($cidr_str[1]);
        }

        $addr = ip2long($content_line);

        if ($addr == 0) {
            continue;
        }

        $ip_obj = create_v4_net($addr, $cidr);
        $nw_ips[] = $ip_obj['network'];

        $ips[] = $ip_obj;
    }

    array_multisort($nw_ips, SORT_ASC, SORT_REGULAR, $ips);
    unset($nw_ips);

    $decided = [];
    $not_decided = $ips;

    while (count($not_decided) != 0) {
        $data = ip4_combine($not_decided);

        if (count($data[0]) == 0 && array_diff($data[1], $not_decided) === []) {
            // If there is no new change.
            break;
        }

        $decided = array_merge($decided, $data[0]);
        $not_decided = $data[1];

        uasort($not_decided, function ($a, $b) {
            if ($a['network'] === $b['network']) {
                return 0;
            }
            return ($a['network'] < $b['network']) ? -1 : 1;
        });
    }

    return array_map(function ($ip_obj) {
        return format_ip4_obj($ip_obj);
    }, $decided);
}

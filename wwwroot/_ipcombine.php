<?php
/*
 * Combine adjacent subnets
 * This requires LongIP and Prefix format: [LongIP, CIDR]
 */
function combineAdjacentSubnets(array $subnets): array
{
    // Sort subnets in ascending order based on IP value
    usort($subnets, function ($a, $b) {
        // Get 32bit integer from IP addressed and compare
        return $a[0] - $b[0];
    });

    $combined = [];

    // Make sure $subnets are all network addresses
    // and normalize IP address format to [32bit integer, prefix]
    $subnets = array_map(function ($subnet) {
        [$ip, $prefix] = $subnet;
        // Get network address
        $ip = $ip & (0xFFFFFFFF << (32 - $prefix));
        return [$ip, $prefix];
    }, $subnets);

    // Remove duplicates
    $subnets = array_unique($subnets, SORT_REGULAR);

    while (!empty($subnets)) {
        $current = array_shift($subnets);

        [$currentIpLong, $currentPrefix] = $current;

        // Calculate the next possible adjacent IP based on prefix (get broadcast addr + 1)
        $nextIpLong = $currentIpLong + pow(2, (32 - $currentPrefix));

        if (
            count($subnets) > 0 && // Make sure there is a next subnet
            $subnets[0][0] == $nextIpLong && $subnets[0][1] == $currentPrefix // Check if next subnet is adjacent
        ) {
            // If next subnet exists, merge by decreasing prefix
            array_shift($subnets); // Remove next subnet
            $currentPrefix--;
            array_unshift($subnets, [$currentIpLong, $currentPrefix]);
        } else {
            // If next item starts with (broadcast addr + 1), add to combine.
            // Might be able to merge with next item.
            $combined[] = [$currentIpLong, $currentPrefix];
        }
    }

    return $combined;
}

/*
 * Binary search for long IP address
 */
function searchLongIpAddress(array $ary, int $tgt): int|false
{
    $left = 0;
    $right = count($ary) - 1;

    while ($left <= $right) {
        $mid = $left + intval(($right - $left) / 2);

        if ($ary[$mid] === $tgt) {
            // Find first occurrence
            while ($mid > 0 && $ary[$mid - 1] === $tgt) {
                $mid--;
            }
            return $mid;
        }

        if ($ary[$mid] < $tgt) {
            $left = $mid + 1;
        } else {
            $right = $mid - 1;
        }
    }

    return false;
}

/*
 * Remove subnets that are already covered by larger subnets
 * This requires LongIP and Prefix format: [LongIP, CIDR]
 * and LongIP must be network address
 *
 * @param array $subnets List of subnets in IP/CIDR format
 * @return array Filtered list of subnets where no subnet is a subset of another subnet
 */
function removeOverlappedSubnets(array $subnets): array
{
    $filtered = [];

    // Get minimum CIDR in $subnets
    $minimumCidr = min(array_column($subnets, 1));

    $allNetworkAddresses = array_column($subnets, 0);

    $subnets = array_unique($subnets, SORT_REGULAR);

    for ($i = 0; $i < count($subnets); $i++) {
        $isExists = false;

        // Search from larger subnet (might can be overlap this subnet)
        // Limit minimum CIDR to the smallest CIDR in list
        for ($searchCidr = $subnets[$i][1] - 1; $searchCidr >= $minimumCidr; $searchCidr--) {
            $searchNetwork = $subnets[$i][0] & (0xFFFFFFFF << (32 - $searchCidr));
            $searchNetworkIndex = searchLongIpAddress($allNetworkAddresses, $searchNetwork);

            // Search network (expected larger subnet's network addr) is not exists in list
            //  => means no larger subnet exists in this size of cidr.
            if ($searchNetworkIndex === false) {
                continue;
            }

            // In case of 2 or more same network exists, search from next index
            for ($j = $searchNetworkIndex; $j < count($subnets); $j++) {
                // Skip self search
                if ($searchNetworkIndex === $i) {
                    continue;
                } else if ($subnets[$j][0] !== $searchNetwork) {
                    // This isn't contains same network (because original array is sorted)
                    break;
                } else if (/* $subnets[$j][0] === $searchNetwork && */ $subnets[$j][1] === $searchCidr) {
                    // This is searching network. Break.
                    $isExists = true;
                    break;
                }
            }

            if ($isExists) {
                break;
            }
        }
        if (!$isExists) {
            $filtered[] = $subnets[$i];
        }
    }

    return $filtered;
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
    do {
        $newSubnets = combineAdjacentSubnets($subnets);
        $changed = count($newSubnets) !== 0 && ($newSubnets !== $subnets);
        $subnets = $newSubnets;
    } while ($changed);
    return $subnets;
}

/*
 * Format IP long and prefix to IP/CIDR format
 */
function formatIpLongSubnetToCidr(array $ipLongSubnet): array
{
    return array_map(function ($subnet) {
        [$ipLong, $prefix] = $subnet;
        $ip = long2ip($ipLong);
        return "$ip/$prefix";
    }, $ipLongSubnet);
}

function getIpLongSubnetFromCidr(array $cidr): array
{
    return array_map(function ($subnet) {
        [$ip, $prefix] = explode('/', $subnet);
        $ipLong = ip2long($ip);
        return [$ipLong, $prefix];
    }, $cidr);
}

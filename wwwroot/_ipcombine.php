<?php
// Combine adjacent subnets if they can be merged
// Example:
//   combineAdjacentSubnets(['192.168.0.0/24', '192.168.1.0/24']) => ['192.168.0.0/23']
function combineAdjacentSubnets(array $subnets): array {
    // Sort subnets in ascending order based on IP value
    usort($subnets, function($a, $b) {
        // Get 32bit integer from IP addressed and compare
        return ip2long(explode('/', $a)[0]) - ip2long(explode('/', $b)[0]);
    });

    $combined = [];

    // Make sure $subnets are all network addresses
    // and normalize IP address format to [32bit integer, prefix]
    $subnets = array_map(function($subnet) {
        [$ip, $prefix] = explode('/', $subnet);
        // Get network address
        $ipLong = ip2long($ip) & (0xFFFFFFFF << (32 - $prefix));
        return [$ipLong, $prefix];
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
            // If no merge, add current subnet to combined list (because this subnet can't be merged anymore)
            // And convert to IP/CIDR format
            $currentIp = long2ip($currentIpLong);
            $combined[] = "$currentIp/$currentPrefix";
        }
    }

    return removeDuplicatedSubnets($combined);
}

/*
 * Remove subnets that are already covered by larger subnets
 * Example:
 *  removeSubnets(['192.168.0.0/23', '192.168.1.0/24']) => ['192.168.0.0/23']
 *
 * @param array $subnets List of subnets in IP/CIDR format
 * @return array Filtered list of subnets where no subnet is a subset of another subnet
 */
function removeDuplicatedSubnets(array $subnets): array {
    $filtered = [];

    $subnets = array_unique($subnets);

    // for each subnet, check if it is a subset of any other subnet
    foreach ($subnets as $subnet) {
        // Split IP and prefix
        list($ip, $prefix) = explode('/', $subnet);
        $ipLong = ip2long($ip);

        // FLAG for current subnet is a subnetted by any existing subnet.
        $isSubset = false;

        // Check if the current subnet is a subset of any existing subnet
        foreach ($filtered as $existing) {
            list($existingIp, $existingPrefix) = explode('/', $existing);
            $existingIpLong = ip2long($existingIp);

            $existingIpNetworkLong = $existingIpLong & (0xFFFFFFFF << (32 - $existingPrefix));
            $existingIpBroadcastLong = $existingIpNetworkLong + pow(2, (32 - $existingPrefix)) - 1;

            // Check if the current subnet falls within an existing larger subnet
            if (
                $ipLong >= $existingIpNetworkLong && // IP is greater than or equal to existing network address
                $ipLong <= $existingIpBroadcastLong && // IP is less than or equal to existing broadcast address
                $prefix >= $existingPrefix // Prefix is greater than or equal (smaller or equal network size) to existing prefix
            ) {
                $isSubset = true;
                break;
            }
        }

        // If subnet is not subnetted of any existing subnet, add it to the filtered list
        // If subnetted, ignore (delete) it.
        if (!$isSubset) {
            $filtered[] = $subnet;
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
function recursiveCombineAdjacentSubnets(array $subnets): array {
    do {
        $newSubnets = combineAdjacentSubnets($subnets);
        $changed = ($newSubnets !== $subnets);
        $subnets = $newSubnets;
    } while ($changed);
    return $subnets;
}

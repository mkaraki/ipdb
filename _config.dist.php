<?php
const DB_CONSTR = 'host=postgres port=5432 dbname=ipdb user=ipdb password=ipdb';
const USER_ATK_REPORTER = [
    'example' => 'INVALID HASH',
];
const USER_ATK_MANAGER = [
    'admin' => 'INVALID HASH',
];
const GEOIP_PARENT = '/usr/local/GeoIP';
const ATK_IGNORE_IP = [
    '1.1.1.1'
];

// 0: No optimization
// 1: Combine adjacent subnets once (like /24 + /24 -> /23)
// 2: Combine adjacent subnets recursively (like /24 x 4 -> /22)
// 3: Combine adjacent subnets recursively and remove overlap)
const ATK_FEED_OPTIMIZE_LEVEL = 2;
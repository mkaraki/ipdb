<?php
const DB_CONSTR = 'host=postgres port=5432 dbname=ipdb user=ipdb password=ipdb';
const USER_ATK_REPORTER = [
    'example' => 'INVALID HASH',
];
const USER_ATK_MANAGER = [
    'admin' => 'INVALID HASH',
];
const GEOIP_PARENT = '/usr/local/GeoIP';

// 0: No optimization
// 1: No optimization ~~Combine adjacent subnets once (like /24 + /24 -> /23)~~
// 2: Combine adjacent subnets recursively (like /24 x 4 -> /22)
// 3: Same as 2 ~~Combine adjacent subnets recursively and remove overlap)~~
const ATK_FEED_OPTIMIZE_LEVEL = 2;

const ATK_POST_CACHE_AGE = 60 * 60 * 24 * 7; // 1 week
const ATK_SKIP_UPDATE_ON_CACHE_HIT = false;

// const SENTRY_DSN = "";
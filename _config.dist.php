<?php
const DB_HOSTNAME = 'mariadb';
const DB_USERNAME = 'ipdb';
const DB_PASSWORD = 'ipdb';
const DB_DATABASE = 'ipdb';

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

const ATK_POST_GEO_INFO_CACHE_AGE = 60 * 60 * 24 * 7; // 1 week

// const SENTRY_DSN = "";

const MAX_ATK_FEED_TIME = 60 * 60 * 24 * 31 * 12; // 1 year

const PROVIDE_ATK_WP_ADMIN_ENDPOINT = true;
const PROVIDE_ATK_XML_RPC_ENDPOINT = true;
const PROVIDE_ATK_DOT_ENV_ENDPOINT = true;
const PROVIDE_ATK_GIT_DIR_ENDPOINT = true;

// If enabled, use `CF-Connecting-IP` for remote ip address detection.
const IS_CLOUDFLARE_PROXIED = false;

const APP_ENV = 'production';
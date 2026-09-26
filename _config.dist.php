<?php
const DB_HOSTNAME = 'db';
const DB_USERNAME = 'ipdb';
const DB_PASSWORD = 'ipdb';
const DB_DATABASE = 'ipdb';

// Password hashes (bcrypt, cost 12). Password for every account below: "password"
// Regenerate with: php -r 'echo password_hash("password", PASSWORD_BCRYPT, ["cost" => 12]), PHP_EOL;'
const USER_ATK_REPORTER = [
    'example' => '$2y$12$2p./B1TfKNus.Q.Xj9u6n.erTjbWkziR5Cqr0Cyko7ICyPegaQaAK', // password
];
const USER_ATK_MANAGER = [
    'admin' => '$2y$12$2p./B1TfKNus.Q.Xj9u6n.erTjbWkziR5Cqr0Cyko7ICyPegaQaAK', // password
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

/*const CUSTOM_HEAD_HTML = <<<HTML
    <style>
        body {
            background-color: #f0f0f0;
        }
    </style>
HTML;*/

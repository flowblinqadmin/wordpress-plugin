<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'FQGEO_SERVE_BASE' ) ) {
    define( 'FQGEO_SERVE_BASE', 'https://geo.flowblinq.com/api/serve' );
}
if ( ! defined( 'FQGEO_PROXY_TIMEOUT' ) ) {
    define( 'FQGEO_PROXY_TIMEOUT', 10 );
}
if ( ! defined( 'FQGEO_PROXY_MAX_SIZE' ) ) {
    define( 'FQGEO_PROXY_MAX_SIZE', 524288 );
}
if ( ! defined( 'FQGEO_CACHE_TTL' ) ) {
    define( 'FQGEO_CACHE_TTL', 3600 );
}
if ( ! defined( 'FQGEO_TOKEN_TTL' ) ) {
    define( 'FQGEO_TOKEN_TTL', 3500 );
}
if ( ! defined( 'FQGEO_MAX_POLLS' ) ) {
    define( 'FQGEO_MAX_POLLS', 120 );
}

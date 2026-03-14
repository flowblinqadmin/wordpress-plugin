<?php
/**
 * Bootstrap for WordPress integration + security tests (ES-044).
 *
 * Loaded by phpunit.xml before any test class.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo 'ERROR: WordPress test library not found at ' . $_tests_dir . PHP_EOL;
    echo 'Run install-wp-tests.sh first or set WP_TESTS_DIR correctly.' . PHP_EOL;
    exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Define Flowblinq_GEO_Exit_Exception before plugin loads.
 *
 * The proxy checks class_exists('Flowblinq_GEO_Exit_Exception') to decide
 * whether to call exit() or throw. Defining it here enables throw behavior
 * in integration tests.
 */
if ( ! class_exists( 'Flowblinq_GEO_Exit_Exception' ) ) {
    class Flowblinq_GEO_Exit_Exception extends RuntimeException {}
}

tests_add_filter( 'muplugins_loaded', function () {
    // Define overridable constants BEFORE plugin loads.
    // Plugin's constants.php uses if(!defined()) guards.
    if ( ! defined( 'FQGEO_SERVE_BASE' ) ) {
        define( 'FQGEO_SERVE_BASE', getenv( 'FQGEO_SERVE_BASE' ) ?: 'http://mock-upstream:8080/api/serve' );
    }
    if ( ! defined( 'FQGEO_PROXY_TIMEOUT' ) ) {
        $timeout = getenv( 'FQGEO_PROXY_TIMEOUT' );
        define( 'FQGEO_PROXY_TIMEOUT', $timeout !== false ? (int) $timeout : 2 );
    }

    // Load the plugin
    $plugin_path = dirname( dirname( __DIR__ ) ) . '/flowblinq-geo/flowblinq-geo.php';
    if ( ! file_exists( $plugin_path ) ) {
        // Try bind-mount path inside Docker
        $plugin_path = '/app/plugin/flowblinq-geo.php';
    }
    require $plugin_path;
} );

require $_tests_dir . '/includes/bootstrap.php';

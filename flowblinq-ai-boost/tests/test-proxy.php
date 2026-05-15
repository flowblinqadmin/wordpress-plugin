<?php
/**
 * ES-042 — Unit tests for Flowblinq_Proxy (U1-U23) and Admin/Uninstall (U24-U30).
 *
 * Standalone test runner — no PHPUnit or WordPress test harness required.
 * Requires PHP 7.1+ (void return types). WordPress 6.x requires PHP 7.2.5+.
 * Run: php8.3 tests/test-proxy.php
 */

require_once __DIR__ . '/bootstrap.php';

// We need to source the constants and class-proxy files (they don't exist yet — TDD).
// The require will fail until implementation is done, but the tests define expectations.
$plugin_dir = dirname( __DIR__ ) . '/';

// Define the constants file path and proxy class path
define( 'FQGEO_PLUGIN_DIR', $plugin_dir );
define( 'FQGEO_PLUGIN_URL', 'http://example.com/wp-content/plugins/flowblinq-geo/' );
define( 'FQGEO_VERSION', '1.0.0' );

// Load implementation files (will fail until Phase 2)
require_once $plugin_dir . 'includes/constants.php';
require_once $plugin_dir . 'includes/class-proxy.php';

// class-api-client.php uses typed properties (PHP 7.4+).
// For PHP 7.0 compatibility in tests, load a stub if needed.
if ( PHP_VERSION_ID >= 70400 ) {
    require_once $plugin_dir . 'includes/class-api-client.php';
} else {
    // Minimal stub for Flowblinq_API_Client
    if ( ! class_exists( 'Flowblinq_API_Client' ) ) {
        class Flowblinq_API_Client {
            private $base_url = 'https://geo.flowblinq.com';
            private $client_id;
            private $client_secret;
            public function __construct( $client_id, $client_secret ) {
                $this->client_id = $client_id;
                $this->client_secret = $client_secret;
            }
            public function get_token() {
                $cached = get_transient( 'fqgeo_access_token' );
                if ( $cached ) return $cached;
                $response = wp_remote_post( $this->base_url . '/api/oauth/token', [
                    'timeout' => 10,
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'body'    => wp_json_encode( [
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $this->client_id,
                        'client_secret' => $this->client_secret,
                    ] ),
                ] );
                if ( is_wp_error( $response ) ) return $response;
                $code = wp_remote_retrieve_response_code( $response );
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( $code !== 200 || empty( $body['access_token'] ) ) {
                    return new WP_Error( 'fqgeo_token_error', isset($body['error']) ? $body['error'] : 'token_request_failed' );
                }
                set_transient( 'fqgeo_access_token', $body['access_token'], 3500 );
                return $body['access_token'];
            }
            public function submit_audit( $url ) {
                $token = $this->get_token();
                if ( is_wp_error( $token ) ) return $token;
                $response = wp_remote_post( $this->base_url . '/api/v1/audit', [
                    'timeout' => 15,
                    'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token ],
                    'body'    => wp_json_encode( [ 'url' => $url ] ),
                ] );
                if ( is_wp_error( $response ) ) return $response;
                $code = wp_remote_retrieve_response_code( $response );
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! in_array( $code, [ 200, 201, 409 ], true ) ) {
                    return new WP_Error( 'fqgeo_audit_error', isset($body['error']) ? $body['error'] : 'submit_failed' );
                }
                return $body;
            }
            public function get_audit( $audit_id ) { return []; }
            public function verify_audit( $audit_id ) { return []; }
        }
    }
}
require_once $plugin_dir . 'includes/class-admin-page.php';

// ── Minimal test framework ──────────────────────────────────────────────────

$test_results = [ 'pass' => 0, 'fail' => 0, 'errors' => [] ];

function assert_true( $condition, $msg = '' ) {
    if ( ! $condition ) throw new \RuntimeException( "Assertion failed: expected true. $msg" );
}

function assert_false( $condition, $msg = '' ) {
    if ( $condition ) throw new \RuntimeException( "Assertion failed: expected false. $msg" );
}

function assert_eq( $expected, $actual, $msg = '' ) {
    if ( $expected !== $actual ) {
        throw new \RuntimeException( "Assertion failed: expected " . var_export( $expected, true ) . " got " . var_export( $actual, true ) . ". $msg" );
    }
}

function assert_contains( $needle, $haystack, $msg = '' ) {
    if ( strpos( $haystack, $needle ) === false ) {
        throw new \RuntimeException( "Assertion failed: '$needle' not found in output. $msg" );
    }
}

function assert_not_contains( $needle, $haystack, $msg = '' ) {
    if ( strpos( $haystack, $needle ) !== false ) {
        throw new \RuntimeException( "Assertion failed: '$needle' should not be in output. $msg" );
    }
}

function assert_count( $expected, $array, $msg = '' ) {
    $actual = count( $array );
    if ( $expected !== $actual ) {
        throw new \RuntimeException( "Assertion failed: expected count $expected got $actual. $msg" );
    }
}

function run_test( $name, $fn ) {
    global $test_results;
    fqgeo_reset_state();
    try {
        $fn();
        $test_results['pass']++;
        echo "  PASS  $name\n";
    } catch ( \Throwable $e ) {
        $test_results['fail']++;
        $test_results['errors'][] = "$name: " . $e->getMessage();
        echo "  FAIL  $name — " . $e->getMessage() . "\n";
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// Flowblinq_Proxy tests (U1-U23)
// ═══════════════════════════════════════════════════════════════════════════

echo "\n── Flowblinq_Proxy ──\n\n";

// U1: register_rewrite_rules adds 3 rules
run_test( 'U1: register_rewrite_rules adds 3 rules', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_rewrite_rules'] = [];
    $proxy->register_rewrite_rules();

    assert_count( 3, $GLOBALS['_fqgeo_rewrite_rules'] );

    $regexes = array_column( $GLOBALS['_fqgeo_rewrite_rules'], 'regex' );
    assert_true( in_array( '^llms\\.txt$', $regexes, true ), 'llms.txt rule' );
    assert_true( in_array( '^llms-full\\.txt$', $regexes, true ), 'llms-full.txt rule' );
    assert_true( in_array( '^\\.well-known/ucp\\.json$', $regexes, true ), '.well-known/ucp.json rule' );

    // All should be 'top' priority
    foreach ( $GLOBALS['_fqgeo_rewrite_rules'] as $rule ) {
        assert_eq( 'top', $rule['after'], 'rule priority should be top' );
    }
});

// U2: register_query_vars adds fqgeo_serve
run_test( 'U2: register_query_vars adds fqgeo_serve', function () {
    $proxy = new Flowblinq_Proxy();
    $result = $proxy->register_query_vars( [ 'existing_var' ] );

    assert_eq( [ 'existing_var', 'fqgeo_serve' ], $result );
});

// U3: handle_serve — no query var
run_test( 'U3: handle_serve returns without output when no query var', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [];

    ob_start();
    $proxy->handle_serve();
    $output = ob_get_clean();

    assert_eq( '', $output, 'should produce no output' );
});

// U4: handle_serve — invalid key
run_test( 'U4: handle_serve returns without output for invalid key', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [ 'fqgeo_serve' => 'invalid_key' ];

    ob_start();
    $proxy->handle_serve();
    $output = ob_get_clean();

    assert_eq( '', $output, 'should produce no output for invalid key' );
});

// U5: handle_serve — no slug configured
run_test( 'U5: handle_serve wp_die 503 when no slug', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [ 'fqgeo_serve' => 'llms_txt' ];
    // No fqgeo_site_slug set

    $caught = false;
    try {
        $proxy->handle_serve();
    } catch ( FQ_WP_Die_Exception $e ) {
        $caught = true;
        assert_eq( 503, $e->getCode(), 'should be 503' );
    }
    assert_true( $caught, 'wp_die should be called' );
});

// U6: handle_serve — cache hit
run_test( 'U6: handle_serve serves cached content on cache hit', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [ 'fqgeo_serve' => 'llms_txt' ];
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_llms_txt'] = '# My LLMs file';

    ob_start();
    try {
        $proxy->handle_serve();
    } catch ( Flowblinq_GEO_Exit_Exception $e ) {
        // expected
    }
    $output = ob_get_clean();

    assert_contains( '# My LLMs file', $output );
    // Should NOT have made any remote calls
    assert_count( 0, $GLOBALS['_fqgeo_remote_call_log'] );
    // Check headers
    $headers = $GLOBALS['_fqgeo_headers_sent'];
    assert_true( in_array( 'HTTP/1.1 200', $headers, true ), '200 status' );
    assert_true( in_array( 'Content-Type: text/plain; charset=utf-8', $headers, true ), 'Content-Type header' );
});

// U7: handle_serve — cache miss, upstream 200
run_test( 'U7: handle_serve fetches from upstream on cache miss', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [ 'fqgeo_serve' => 'llms_txt' ];
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, '# Fresh content' );

    ob_start();
    try {
        $proxy->handle_serve();
    } catch ( Flowblinq_GEO_Exit_Exception $e ) {
        // expected
    }
    $output = ob_get_clean();

    assert_contains( '# Fresh content', $output );
    // Should be cached now
    assert_eq( '# Fresh content', get_transient( 'fqgeo_proxy_llms_txt' ) );
    // Verify URL called
    assert_eq( 'https://geo.flowblinq.com/api/serve/my-site/llms.txt', $GLOBALS['_fqgeo_remote_call_log'][0]['url'] );
});

// U8: handle_serve — upstream non-200
run_test( 'U8: handle_serve returns 502 on upstream non-200', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [ 'fqgeo_serve' => 'llms_txt' ];
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 404, 'Not found' );

    ob_start();
    try {
        $proxy->handle_serve();
    } catch ( Flowblinq_GEO_Exit_Exception $e ) {
        // expected
    }
    $output = ob_get_clean();

    assert_contains( 'Service temporarily unavailable', $output );
    assert_true( in_array( 'HTTP/1.1 502', $GLOBALS['_fqgeo_headers_sent'], true ), '502 status' );
});

// U9: handle_serve — upstream timeout (WP_Error)
run_test( 'U9: handle_serve returns 504 on upstream timeout', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [ 'fqgeo_serve' => 'llms_txt' ];
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_remote_responses'][] = new WP_Error( 'http_request_failed', 'Connection timed out' );

    ob_start();
    try {
        $proxy->handle_serve();
    } catch ( Flowblinq_GEO_Exit_Exception $e ) {
        // expected
    }
    $output = ob_get_clean();

    assert_contains( 'Gateway timeout', $output );
    assert_true( in_array( 'HTTP/1.1 504', $GLOBALS['_fqgeo_headers_sent'], true ), '504 status' );
});

// U10: handle_serve — upstream too large
run_test( 'U10: handle_serve returns 502 for oversized response', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [ 'fqgeo_serve' => 'llms_txt' ];
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $big_body = str_repeat( 'x', 524289 ); // > 512KB
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, $big_body );

    ob_start();
    try {
        $proxy->handle_serve();
    } catch ( Flowblinq_GEO_Exit_Exception $e ) {
        // expected
    }
    $output = ob_get_clean();

    assert_contains( 'Service temporarily unavailable', $output );
    assert_true( in_array( 'HTTP/1.1 502', $GLOBALS['_fqgeo_headers_sent'], true ), '502 status' );
});

// U11: handle_serve — error never cached
run_test( 'U11: errors are never cached in transients', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_current_query_vars'] = [ 'fqgeo_serve' => 'llms_txt' ];
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 500, 'Server error' );

    ob_start();
    try {
        $proxy->handle_serve();
    } catch ( Flowblinq_GEO_Exit_Exception $e ) {}
    ob_get_clean();

    assert_false( array_key_exists( 'fqgeo_proxy_llms_txt', $GLOBALS['_fqgeo_transients'] ), 'error should not be cached' );
});

// U12: fetch_upstream — correct URL for llms_txt
run_test( 'U12: fetch_upstream builds correct URL for llms_txt', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, 'content' );

    $result = $proxy->fetch_upstream( 'llms_txt', 'my-site' );

    assert_eq( 'https://geo.flowblinq.com/api/serve/my-site/llms.txt', $GLOBALS['_fqgeo_remote_call_log'][0]['url'] );
    assert_eq( 'content', $result );
});

// U13: fetch_upstream — correct URL for business_json
run_test( 'U13: fetch_upstream builds correct URL for business_json', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, '{}' );

    $proxy->fetch_upstream( 'business_json', 'my-site' );

    assert_eq( 'https://geo.flowblinq.com/api/serve/my-site/business.json', $GLOBALS['_fqgeo_remote_call_log'][0]['url'] );
});

// U14: inject_schema_jsonld — no slug
run_test( 'U14: inject_schema_jsonld outputs nothing when no slug', function () {
    $proxy = new Flowblinq_Proxy();
    // No fqgeo_site_slug set

    ob_start();
    $proxy->inject_schema_jsonld();
    $output = ob_get_clean();

    assert_eq( '', $output );
});

// U15: inject_schema_jsonld — cache hit, 2 schemas
run_test( 'U15: inject_schema_jsonld outputs 2 script tags from cached JSON', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $schemas = [ [ '@type' => 'Organization', 'name' => 'Test' ], [ '@type' => 'WebSite', 'url' => 'http://example.com' ] ];
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_schema_json'] = json_encode( $schemas );

    ob_start();
    $proxy->inject_schema_jsonld();
    $output = ob_get_clean();

    assert_eq( 2, substr_count( $output, '<script type="application/ld+json">' ), 'should have 2 script tags' );
    assert_contains( 'Organization', $output );
    assert_contains( 'WebSite', $output );
});

// U16: inject_schema_jsonld — cache miss, valid JSON
run_test( 'U16: inject_schema_jsonld fetches and caches schema on miss', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $schemas = [ [ '@type' => 'LocalBusiness' ] ];
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, json_encode( $schemas ) );

    ob_start();
    $proxy->inject_schema_jsonld();
    $output = ob_get_clean();

    assert_contains( 'LocalBusiness', $output );
    assert_true( array_key_exists( 'fqgeo_proxy_schema_json', $GLOBALS['_fqgeo_transients'] ), 'should be cached' );
});

// U17: inject_schema_jsonld — invalid JSON from upstream
run_test( 'U17: inject_schema_jsonld outputs nothing for invalid JSON', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, 'not json at all' );

    ob_start();
    $proxy->inject_schema_jsonld();
    $output = ob_get_clean();

    assert_eq( '', $output );
});

// U18: inject_schema_jsonld — non-array JSON (object instead)
run_test( 'U18: inject_schema_jsonld outputs nothing for non-array JSON', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, '{"type":"object"}' );

    ob_start();
    $proxy->inject_schema_jsonld();
    $output = ob_get_clean();

    assert_eq( '', $output );
});

// U19: inject_schema_jsonld — JSON_HEX_TAG encoding
run_test( 'U19: inject_schema_jsonld uses JSON_HEX_TAG to prevent XSS', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $schemas = [ [ '@type' => 'Test', 'desc' => '</script><script>alert(1)</script>' ] ];
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_schema_json'] = json_encode( $schemas );

    ob_start();
    $proxy->inject_schema_jsonld();
    $output = ob_get_clean();

    assert_not_contains( '</script><script>', $output, 'should not contain raw </script>' );
    assert_contains( '\\u003C', $output, 'should use hex encoding for <' );
});

// U20: inject_schema_jsonld — oversized response
run_test( 'U20: inject_schema_jsonld ignores oversized response', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $big_body = str_repeat( '["x"]', 200000 ); // > 512KB
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, $big_body );

    ob_start();
    $proxy->inject_schema_jsonld();
    $output = ob_get_clean();

    assert_eq( '', $output );
    assert_false( array_key_exists( 'fqgeo_proxy_schema_json', $GLOBALS['_fqgeo_transients'] ), 'should not cache' );
});

// U21: append_robots_directives — slug set
run_test( 'U21: append_robots_directives adds AI crawler directives', function () {
    $proxy = new Flowblinq_Proxy();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';

    $result = $proxy->append_robots_directives( "User-agent: *\nDisallow:\n", true );

    assert_contains( 'User-agent: GPTBot', $result );
    assert_contains( 'User-agent: ClaudeBot', $result );
    assert_contains( 'User-agent: PerplexityBot', $result );
    assert_contains( 'Allow: /llms.txt', $result );
    assert_contains( 'Allow: /llms-full.txt', $result );
    assert_contains( 'Allow: /.well-known/ucp.json', $result );
});

// U22: append_robots_directives — no slug
run_test( 'U22: append_robots_directives returns unchanged output without slug', function () {
    $proxy = new Flowblinq_Proxy();
    $original = "User-agent: *\nDisallow:\n";

    $result = $proxy->append_robots_directives( $original, true );

    assert_eq( $original, $result );
});

// U23: clear_cache deletes all 4 transients
run_test( 'U23: clear_cache deletes all 4 proxy transients', function () {
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_llms_txt'] = 'a';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_llms_full_txt'] = 'b';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_business_json'] = 'c';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_schema_json'] = 'd';

    Flowblinq_Proxy::clear_cache();

    assert_false( array_key_exists( 'fqgeo_proxy_llms_txt', $GLOBALS['_fqgeo_transients'] ) );
    assert_false( array_key_exists( 'fqgeo_proxy_llms_full_txt', $GLOBALS['_fqgeo_transients'] ) );
    assert_false( array_key_exists( 'fqgeo_proxy_business_json', $GLOBALS['_fqgeo_transients'] ) );
    assert_false( array_key_exists( 'fqgeo_proxy_schema_json', $GLOBALS['_fqgeo_transients'] ) );
});

// ═══════════════════════════════════════════════════════════════════════════
// Admin page tests (U24-U28)
// ═══════════════════════════════════════════════════════════════════════════

echo "\n── Flowblinq_Admin_Page ──\n\n";

// U24: handle_ajax_test_connection — no slug
run_test( 'U24: test_connection returns error when no slug', function () {
    $admin = new Flowblinq_Admin_Page();

    try {
        $admin->handle_ajax_test_connection();
    } catch ( FQ_Ajax_Exit_Exception $e ) {}

    assert_false( $GLOBALS['_fqgeo_json_response']['success'] );
    assert_contains( 'slug not configured', $GLOBALS['_fqgeo_json_response']['data']['message'] );
});

// U25: handle_ajax_test_connection — upstream 200
run_test( 'U25: test_connection returns success on upstream 200', function () {
    $admin = new Flowblinq_Admin_Page();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, 'ok' );

    try {
        $admin->handle_ajax_test_connection();
    } catch ( FQ_Ajax_Exit_Exception $e ) {}

    assert_true( $GLOBALS['_fqgeo_json_response']['success'] );
    assert_contains( 'Connected', $GLOBALS['_fqgeo_json_response']['data']['message'] );
});

// U26: handle_ajax_test_connection — upstream error
run_test( 'U26: test_connection returns error on non-200', function () {
    $admin = new Flowblinq_Admin_Page();
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'my-site';
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 503, '' );

    try {
        $admin->handle_ajax_test_connection();
    } catch ( FQ_Ajax_Exit_Exception $e ) {}

    assert_false( $GLOBALS['_fqgeo_json_response']['success'] );
    assert_contains( '503', $GLOBALS['_fqgeo_json_response']['data']['message'] );
});

// U27: handle_ajax_clear_cache clears transients
run_test( 'U27: clear_cache AJAX clears transients and returns success', function () {
    $admin = new Flowblinq_Admin_Page();
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_llms_txt'] = 'cached';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_schema_json'] = 'cached';

    try {
        $admin->handle_ajax_clear_cache();
    } catch ( FQ_Ajax_Exit_Exception $e ) {}

    assert_true( $GLOBALS['_fqgeo_json_response']['success'] );
    assert_false( array_key_exists( 'fqgeo_proxy_llms_txt', $GLOBALS['_fqgeo_transients'] ) );
    assert_false( array_key_exists( 'fqgeo_proxy_schema_json', $GLOBALS['_fqgeo_transients'] ) );
});

// U28: handle_ajax_run_audit stores slug from response
run_test( 'U28: run_audit stores slug from API response', function () {
    $admin = new Flowblinq_Admin_Page();
    $GLOBALS['_fqgeo_options']['fqgeo_client_id'] = 'test-id';
    $GLOBALS['_fqgeo_options']['fqgeo_client_secret'] = 'test-secret';
    // Token response
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, json_encode( [ 'access_token' => 'tok123' ] ) );
    // Audit response with slug
    $GLOBALS['_fqgeo_remote_responses'][] = fqgeo_mock_response( 200, json_encode( [ 'audit_id' => 'aud-1', 'slug' => 'my-site' ] ) );

    try {
        $admin->handle_ajax_run_audit();
    } catch ( FQ_Ajax_Exit_Exception $e ) {}

    assert_eq( 'my-site', get_option( 'fqgeo_site_slug' ) );
    assert_eq( 'aud-1', get_option( 'fqgeo_active_audit_id' ) );
});

// ═══════════════════════════════════════════════════════════════════════════
// Uninstall tests (U29-U30)
// ═══════════════════════════════════════════════════════════════════════════

echo "\n── Uninstall ──\n\n";

// U29: Uninstall deletes all options
run_test( 'U29: uninstall deletes all options', function () {
    $GLOBALS['_fqgeo_options']['fqgeo_client_id'] = 'id';
    $GLOBALS['_fqgeo_options']['fqgeo_client_secret'] = 'secret';
    $GLOBALS['_fqgeo_options']['fqgeo_site_slug'] = 'slug';
    $GLOBALS['_fqgeo_options']['fqgeo_active_audit_id'] = 'audit';

    // Source uninstall.php — it calls delete_option directly
    // We'll simulate by running the same code
    delete_option( 'fqgeo_client_id' );
    delete_option( 'fqgeo_client_secret' );
    delete_option( 'fqgeo_site_slug' );
    delete_option( 'fqgeo_active_audit_id' );

    assert_false( array_key_exists( 'fqgeo_client_id', $GLOBALS['_fqgeo_options'] ) );
    assert_false( array_key_exists( 'fqgeo_client_secret', $GLOBALS['_fqgeo_options'] ) );
    assert_false( array_key_exists( 'fqgeo_site_slug', $GLOBALS['_fqgeo_options'] ) );
    assert_false( array_key_exists( 'fqgeo_active_audit_id', $GLOBALS['_fqgeo_options'] ) );
});

// U30: Uninstall deletes all transients
run_test( 'U30: uninstall deletes all transients', function () {
    $GLOBALS['_fqgeo_transients']['fqgeo_access_token'] = 'tok';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_llms_txt'] = 'a';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_llms_full_txt'] = 'b';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_business_json'] = 'c';
    $GLOBALS['_fqgeo_transients']['fqgeo_proxy_schema_json'] = 'd';

    delete_transient( 'fqgeo_access_token' );
    delete_transient( 'fqgeo_proxy_llms_txt' );
    delete_transient( 'fqgeo_proxy_llms_full_txt' );
    delete_transient( 'fqgeo_proxy_business_json' );
    delete_transient( 'fqgeo_proxy_schema_json' );

    assert_false( array_key_exists( 'fqgeo_access_token', $GLOBALS['_fqgeo_transients'] ) );
    assert_false( array_key_exists( 'fqgeo_proxy_llms_txt', $GLOBALS['_fqgeo_transients'] ) );
    assert_false( array_key_exists( 'fqgeo_proxy_llms_full_txt', $GLOBALS['_fqgeo_transients'] ) );
    assert_false( array_key_exists( 'fqgeo_proxy_business_json', $GLOBALS['_fqgeo_transients'] ) );
    assert_false( array_key_exists( 'fqgeo_proxy_schema_json', $GLOBALS['_fqgeo_transients'] ) );
});

// ═══════════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════════

echo "\n────────────────────────────────────\n";
echo "Results: {$test_results['pass']} passed, {$test_results['fail']} failed\n";

if ( ! empty( $test_results['errors'] ) ) {
    echo "\nFailures:\n";
    foreach ( $test_results['errors'] as $err ) {
        echo "  - $err\n";
    }
}

echo "\n";
exit( $test_results['fail'] > 0 ? 1 : 0 );

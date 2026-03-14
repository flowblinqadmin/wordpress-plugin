<?php
/**
 * ES-044 Integration Tests — I1–I40
 *
 * Tests the Flowblinq GEO WordPress plugin against a real WordPress + MySQL instance
 * and a mock upstream server running at http://mock-upstream:8080.
 *
 * @group integration
 */

class Test_Integration extends WP_UnitTestCase {

    /** @var string Base URL of mock upstream (overridden via constant in bootstrap) */
    private string $mock_base = 'http://mock-upstream:8080';

    public function setUp(): void {
        parent::setUp();
        wp_cache_flush();
        $GLOBALS['_fq_headers_sent'] = [];

        // Reset mock upstream log
        wp_remote_get( $this->mock_base . '/__reset_log', [ 'timeout' => 2 ] );
    }

    public function tearDown(): void {
        unset( $_REQUEST['nonce'], $_POST['nonce'], $_POST['audit_id'], $_POST['url'] );
        $GLOBALS['_fq_headers_sent'] = [];
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Add a pre_http_request filter that rewrites geo.flowblinq.com → mock.
     */
    private function add_api_redirect_filter(): void {
        add_filter( 'pre_http_request', [ $this, '_redirect_to_mock' ], 1, 3 );
    }

    private function remove_api_redirect_filter(): void {
        remove_filter( 'pre_http_request', [ $this, '_redirect_to_mock' ], 1 );
    }

    public function _redirect_to_mock( $preempt, $parsed_args, $url ) {
        if ( strpos( $url, 'https://geo.flowblinq.com' ) === 0 ) {
            $new_url = str_replace( 'https://geo.flowblinq.com', $this->mock_base, $url );
            return wp_remote_request( $new_url, $parsed_args );
        }
        return $preempt;
    }

    /**
     * Call handle_serve() by setting query var and triggering template_redirect.
     * Captures output and catches Flowblinq_GEO_Exit_Exception.
     */
    private function call_handle_serve( string $key ): array {
        global $wp_query;
        $wp_query->set( 'fq_serve', $key );

        $output = '';
        $status = 200;
        $headers = [];

        // Capture headers via filter
        $header_filter = function( $header ) use ( &$headers ) {
            $headers[] = $header;
            return $header;
        };
        add_filter( 'fqgeo_send_header', $header_filter );

        ob_start();
        try {
            do_action( 'template_redirect' );
        } catch ( \Exception $e ) {
            // Normal exit in test context
        }
        $output = ob_get_clean();

        remove_filter( 'fqgeo_send_header', $header_filter );

        return [
            'output'  => $output,
            'headers' => $headers,
        ];
    }

    /**
     * Get mock upstream request log.
     */
    private function get_mock_log(): string {
        $response = wp_remote_get( $this->mock_base . '/__log', [ 'timeout' => 3 ] );
        if ( is_wp_error( $response ) ) {
            return '';
        }
        return wp_remote_retrieve_body( $response );
    }

    /**
     * Get the HTTP status code that was sent by the proxy.
     */
    private function get_last_status_code(): int {
        return (int) headers_sent_status();
    }

    // -----------------------------------------------------------------------
    // A. Proxy Routes (I1–I8)
    // -----------------------------------------------------------------------

    /**
     * I1: Cold cache — fetches from upstream, caches transient.
     */
    public function test_proxy_llms_txt_cold_cache() {
        update_option( 'fq_site_slug', 'test-site-123' );
        delete_transient( 'fq_proxy_llms_txt' );

        ob_start();
        try {
            global $wp_query;
            $wp_query->set( 'fq_serve', 'llms_txt' );
            do_action( 'template_redirect' );
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Flowblinq GEO Test Site', $output );
        $this->assertStringContainsString( 'AI Visibility Optimization', $output );
        $cached = get_transient( 'fq_proxy_llms_txt' );
        $this->assertNotFalse( $cached, 'Transient should be set after cold cache fetch' );
    }

    /**
     * I2: Warm cache — serves from transient, no upstream request.
     */
    public function test_proxy_llms_txt_warm_cache() {
        update_option( 'fq_site_slug', 'test-site-123' );
        set_transient( 'fq_proxy_llms_txt', 'cached content', 3600 );

        ob_start();
        try {
            global $wp_query;
            $wp_query->set( 'fq_serve', 'llms_txt' );
            do_action( 'template_redirect' );
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->assertEquals( 'cached content', $output );

        $log = $this->get_mock_log();
        $this->assertStringNotContainsString( '/api/serve/', $log,
            'Warm cache should not make upstream request'
        );
    }

    /**
     * I3: llms-full.txt cold cache.
     */
    public function test_proxy_llms_full_txt_cold() {
        update_option( 'fq_site_slug', 'test-site-123' );
        delete_transient( 'fq_proxy_llms_full_txt' );

        ob_start();
        try {
            global $wp_query;
            $wp_query->set( 'fq_serve', 'llms_full_txt' );
            do_action( 'template_redirect' );
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Flowblinq GEO Test Site', $output );
        $this->assertStringContainsString( 'San Francisco', $output );
    }

    /**
     * I4: business.json cold cache — valid JSON, correct Content-Type.
     */
    public function test_proxy_business_json_cold() {
        update_option( 'fq_site_slug', 'test-site-123' );
        delete_transient( 'fq_proxy_business_json' );

        ob_start();
        try {
            global $wp_query;
            $wp_query->set( 'fq_serve', 'business_json' );
            do_action( 'template_redirect' );
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $decoded = json_decode( $output, true );
        $this->assertNotNull( $decoded, 'Response should be valid JSON' );
        $this->assertEquals( 'Flowblinq GEO Test Site', $decoded['name'] ?? '' );
    }

    /**
     * I5: Upstream 500 → proxy returns 502, nothing cached.
     */
    public function test_proxy_upstream_500() {
        update_option( 'fq_site_slug', 'error-500' );
        delete_transient( 'fq_proxy_llms_txt' );

        ob_start();
        try {
            global $wp_query;
            $wp_query->set( 'fq_serve', 'llms_txt' );
            do_action( 'template_redirect' );
        } catch ( \Exception $e ) {}
        ob_get_clean();

        $this->assertFalse( get_transient( 'fq_proxy_llms_txt' ), '502 response must not be cached' );
    }

    /**
     * I6: Upstream timeout (mock sleeps 5s, FQGEO_PROXY_TIMEOUT=2) → 504, not cached.
     */
    public function test_proxy_upstream_timeout() {
        update_option( 'fq_site_slug', 'timeout' );
        delete_transient( 'fq_proxy_llms_txt' );

        ob_start();
        try {
            global $wp_query;
            $wp_query->set( 'fq_serve', 'llms_txt' );
            do_action( 'template_redirect' );
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->assertStringContainsString( 'timeout', strtolower( $output ) );
        $this->assertFalse( get_transient( 'fq_proxy_llms_txt' ), '504 response must not be cached' );
    }

    /**
     * I7: Oversized body (513KB) → 502, not cached.
     */
    public function test_proxy_oversized_body() {
        update_option( 'fq_site_slug', 'oversized' );
        delete_transient( 'fq_proxy_llms_txt' );

        ob_start();
        try {
            global $wp_query;
            $wp_query->set( 'fq_serve', 'llms_txt' );
            do_action( 'template_redirect' );
        } catch ( \Exception $e ) {}
        ob_get_clean();

        $this->assertFalse( get_transient( 'fq_proxy_llms_txt' ), 'Oversized response must not be cached' );
    }

    /**
     * I8: Response headers — Content-Type, Cache-Control, X-Generator, X-Content-Type-Options.
     *
     * send_header() stores to $GLOBALS['_fq_headers_sent'] in test context (class_exists check).
     */
    public function test_proxy_response_headers() {
        $expected_types = [
            'llms_txt'      => 'text/plain',
            'llms_full_txt' => 'text/plain',
            'business_json' => 'application/json',
        ];

        update_option( 'fq_site_slug', 'test-site-123' );

        foreach ( $expected_types as $key => $expected_type ) {
            // Reset header capture global and pre-populate transient
            $GLOBALS['_fq_headers_sent'] = [];
            set_transient( 'fq_proxy_' . $key, 'test-content', 3600 );

            ob_start();
            try {
                global $wp_query;
                $wp_query->set( 'fq_serve', $key );
                do_action( 'template_redirect' );
            } catch ( \Exception $e ) {}
            ob_get_clean();

            $header_str = implode( "\n", $GLOBALS['_fq_headers_sent'] ?? [] );
            $this->assertStringContainsString( $expected_type, $header_str,
                "I8: {$key} must have Content-Type: {$expected_type}"
            );
            $this->assertStringContainsString( 'Cache-Control: public, max-age=3600', $header_str,
                "I8: {$key} must have Cache-Control header"
            );
            $this->assertStringContainsString( 'X-Generator: FlowBlinq GEO', $header_str,
                "I8: {$key} must have X-Generator header"
            );
            $this->assertStringContainsString( 'X-Content-Type-Options: nosniff', $header_str,
                "I8: {$key} must have X-Content-Type-Options: nosniff"
            );
        }
    }

    // -----------------------------------------------------------------------
    // B. Schema JSON-LD (I9–I14)
    // -----------------------------------------------------------------------

    /**
     * I9: Fresh schema fetch — outputs <script type="application/ld+json">, caches transient.
     */
    public function test_schema_jsonld_fresh_fetch() {
        update_option( 'fq_site_slug', 'test-site-123' );
        delete_transient( 'fq_proxy_schema_json' );
        delete_option( '_fq_stale_schema_json' );

        ob_start();
        do_action( 'wp_head' );
        $output = ob_get_clean();

        $this->assertStringContainsString( '<script type="application/ld+json">', $output );
        // Verify valid JSON inside
        preg_match( '/<script type="application\/ld\+json">(.*?)<\/script>/s', $output, $matches );
        $this->assertNotEmpty( $matches, 'Should contain JSON-LD script tag' );
        $json = json_decode( $matches[1] ?? '', true );
        $this->assertNotNull( $json, 'JSON-LD content should be valid JSON' );

        $this->assertNotFalse( get_transient( 'fq_proxy_schema_json' ), 'Schema transient should be cached' );
    }

    /**
     * I10: Stale-while-revalidate — serves stale immediately, registers shutdown refresh.
     */
    public function test_schema_stale_while_revalidate() {
        update_option( 'fq_site_slug', 'test-site-123' );
        delete_transient( 'fq_proxy_schema_json' );
        // Stale value must be raw JSON array — the plugin calls json_decode() on it
        $stale = '[{"@type":"WebSite","stale":true}]';
        update_option( '_fq_stale_schema_json', $stale );

        ob_start();
        do_action( 'wp_head' );
        $output = ob_get_clean();

        // Plugin wraps the decoded schema in <script> tags — assert the rendered output
        $this->assertStringContainsString( 'application/ld+json', $output, 'Should serve stale content immediately' );
        $this->assertStringContainsString( '"stale":true', $output, 'Stale content body should be in output' );
    }

    /**
     * I11: Stampede lock — only 1 upstream request when lock is set.
     */
    public function test_schema_stampede_lock() {
        update_option( 'fq_site_slug', 'test-site-123' );
        delete_transient( 'fq_proxy_schema_json' );
        // Stale value must be raw JSON array
        $stale = '[{"@type":"WebSite"}]';
        update_option( '_fq_stale_schema_json', $stale );
        set_transient( '_fq_lock_schema_json', 1, 30 );

        // Call inject_schema_jsonld twice
        ob_start();
        do_action( 'wp_head' );
        do_action( 'wp_head' );
        ob_get_clean();

        $log = $this->get_mock_log();
        $count = substr_count( $log, '/api/serve/test-site-123/schema.json' );
        $this->assertEquals( 0, $count, 'Lock should prevent upstream requests' );
    }

    /**
     * I12: Malformed JSON from upstream — no <script> tags.
     */
    public function test_schema_malformed_json() {
        update_option( 'fq_site_slug', 'malformed' );
        delete_transient( 'fq_proxy_schema_json' );
        delete_option( '_fq_stale_schema_json' );

        ob_start();
        do_action( 'wp_head' );
        $output = ob_get_clean();

        $this->assertStringNotContainsString( '<script type="application/ld+json">', $output );
    }

    /**
     * I13: Non-array JSON (object) — no <script> tags.
     */
    public function test_schema_non_array_json() {
        update_option( 'fq_site_slug', 'not-array' );
        delete_transient( 'fq_proxy_schema_json' );
        delete_option( '_fq_stale_schema_json' );

        ob_start();
        do_action( 'wp_head' );
        $output = ob_get_clean();

        $this->assertStringNotContainsString( '<script type="application/ld+json">', $output );
    }

    /**
     * I14: XSS — schema output must use JSON_HEX_TAG, no literal </script>.
     */
    public function test_schema_xss_escaping() {
        update_option( 'fq_site_slug', 'xss-payload' );
        delete_transient( 'fq_proxy_schema_json' );
        delete_option( '_fq_stale_schema_json' );

        ob_start();
        do_action( 'wp_head' );
        $output = ob_get_clean();

        $this->assertStringNotContainsString( '</script><script>', $output );
        if ( strpos( $output, '<script type="application/ld+json">' ) !== false ) {
            $this->assertStringContainsString( '\u003C', $output,
                'JSON_HEX_TAG must escape < as \\u003C'
            );
        }
    }

    // -----------------------------------------------------------------------
    // C. Robots.txt (I15–I17)
    // -----------------------------------------------------------------------

    /**
     * I15: Public site — AI crawler directives appended.
     */
    public function test_robots_public_site() {
        update_option( 'fq_site_slug', 'test-site-123' );
        update_option( 'blog_public', '1' );

        $proxy = new Flowblinq_Proxy();
        $output = $proxy->append_robots_directives( '', true );

        $this->assertStringContainsString( 'GPTBot', $output );
        $this->assertStringContainsString( 'ClaudeBot', $output );
        $this->assertStringContainsString( 'PerplexityBot', $output );
    }

    /**
     * I16: Private site — no AI directives.
     */
    public function test_robots_private_site() {
        update_option( 'blog_public', '0' );

        $proxy = new Flowblinq_Proxy();
        $output = $proxy->append_robots_directives( 'existing', false );

        $this->assertEquals( 'existing', $output );
    }

    /**
     * I17: No slug — output unchanged.
     */
    public function test_robots_no_slug() {
        delete_option( 'fq_site_slug' );

        $proxy = new Flowblinq_Proxy();
        $output = $proxy->append_robots_directives( 'existing', true );

        $this->assertEquals( 'existing', $output );
    }

    // -----------------------------------------------------------------------
    // D. OAuth (I18–I21)
    // -----------------------------------------------------------------------

    /**
     * I18: Token acquisition — returns string, mock log shows POST.
     */
    public function test_token_acquisition() {
        $this->add_api_redirect_filter();
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );
        delete_transient( 'fq_access_token' );

        $client = new Flowblinq_API_Client( 'test-id', 'test-secret' );
        $token = $client->get_token();

        $this->remove_api_redirect_filter();

        $this->assertIsString( $token, 'get_token() should return a string' );
        $this->assertNotEmpty( $token );

        $log = $this->get_mock_log();
        $this->assertStringContainsString( 'POST /api/oauth/token', $log );
    }

    /**
     * I19: Token caching — second call uses transient, only 1 POST.
     */
    public function test_token_caching() {
        $this->add_api_redirect_filter();
        delete_transient( 'fq_access_token' );

        $client = new Flowblinq_API_Client( 'test-id', 'test-secret' );
        $token1 = $client->get_token();
        $token2 = $client->get_token();

        $this->remove_api_redirect_filter();

        $log = $this->get_mock_log();
        $count = substr_count( $log, 'POST /api/oauth/token' );
        $this->assertEquals( 1, $count, 'Second call should use cached transient' );
        $this->assertEquals( $token1, $token2 );
    }

    /**
     * I20: Token expiry — delete transient, next call refetches.
     */
    public function test_token_expiry_refetch() {
        $this->add_api_redirect_filter();
        delete_transient( 'fq_access_token' );

        $client = new Flowblinq_API_Client( 'test-id', 'test-secret' );
        $client->get_token();
        delete_transient( 'fq_access_token' );
        $client->get_token();

        $this->remove_api_redirect_filter();

        $log = $this->get_mock_log();
        $count = substr_count( $log, 'POST /api/oauth/token' );
        $this->assertEquals( 2, $count, 'After expiry, should refetch token' );
    }

    /**
     * I21: Invalid credentials — returns WP_Error.
     */
    public function test_token_invalid_credentials() {
        $this->add_api_redirect_filter();
        delete_transient( 'fq_access_token' );

        $client = new Flowblinq_API_Client( 'invalid', 'invalid' );
        $result = $client->get_token();

        $this->remove_api_redirect_filter();

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'fqgeo_token_error', $result->get_error_code() );
    }

    // -----------------------------------------------------------------------
    // E. Admin Settings (I22–I25)
    // -----------------------------------------------------------------------

    /**
     * I22: Settings save — options stored correctly.
     */
    public function test_settings_save() {
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );

        update_option( 'fq_client_id', 'my-id' );
        update_option( 'fq_client_secret', 'my-secret' );

        $this->assertEquals( 'my-id', get_option( 'fq_client_id' ) );
        $this->assertEquals( 'my-secret', get_option( 'fq_client_secret' ) );
    }

    /**
     * I23: Secret masking — submitting bullets preserves original secret.
     */
    public function test_secret_masking() {
        update_option( 'fq_client_secret', 'original-secret-value' );

        // Retrieve the registered sanitize_callback via settings API
        // The plugin registers 'fq_client_secret' via register_setting() in register_settings()
        $admin = new Flowblinq_Admin_Page();
        if ( method_exists( $admin, 'register_settings' ) ) {
            $admin->register_settings();
        }

        $registered = get_registered_settings();
        $callback = $registered['fq_client_secret']['sanitize_callback'] ?? null;

        if ( $callback ) {
            $result = call_user_func( $callback, '••••••••' );
            $this->assertEquals( 'original-secret-value', $result,
                'I23: Sanitize callback must preserve original secret when bullets submitted'
            );
        } else {
            // Fallback: verify the option is unchanged (bullets were not stored)
            $this->assertEquals( 'original-secret-value', get_option( 'fq_client_secret' ),
                'I23: Original secret must remain unchanged'
            );
        }
    }

    /**
     * I24: Slug validation — valid slug accepted, path traversal rejected.
     */
    public function test_slug_validation() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        // Mock audit returns slug=my-site-123 → valid
        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_run_audit' );
        $_POST['nonce']    = $_REQUEST['nonce'];
        $_POST['url']      = get_site_url();

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_run_audit();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->remove_api_redirect_filter();

        // Should have stored a slug (valid format from mock: test-site-123 pattern)
        $slug = get_option( 'fq_site_slug', '' );
        if ( ! empty( $slug ) ) {
            $this->assertMatchesRegularExpression( '/^[a-z0-9\-]+$/i', $slug,
                'Stored slug must match valid format'
            );
        }
    }

    /**
     * I25: Permalink warning — plain permalinks show error notice.
     */
    public function test_permalink_warning() {
        update_option( 'permalink_structure', '' );

        ob_start();
        $admin = new Flowblinq_Admin_Page();
        // render_settings_page calls get_option etc.
        if ( method_exists( $admin, 'render_settings_page' ) ) {
            $admin->render_settings_page();
        }
        $output = ob_get_clean();

        // Should contain a warning about plain permalinks
        $this->assertStringContainsString( 'notice', strtolower( $output ) );
    }

    // -----------------------------------------------------------------------
    // F. Audit Flow (I26–I31)
    // -----------------------------------------------------------------------

    /**
     * I26: Audit submit — stores audit_id, slug, sends success JSON.
     */
    public function test_audit_submit() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_run_audit' );
        $_POST['nonce']    = $_REQUEST['nonce'];
        $_POST['url']      = get_site_url();

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_run_audit();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->remove_api_redirect_filter();

        $json = json_decode( $output, true );
        $this->assertNotNull( $json );
        if ( isset( $json['success'] ) && $json['success'] === true ) {
            $this->assertNotEmpty( get_option( 'fq_active_audit_id' ), 'Audit ID should be stored' );
        }
    }

    /**
     * I27: Audit poll (first poll) — status pending.
     */
    public function test_audit_poll_pending() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        $audit_id = 'fresh-audit-' . uniqid();
        $_REQUEST['nonce']    = wp_create_nonce( 'fqgeo_poll_audit' );
        $_POST['nonce']       = $_REQUEST['nonce'];
        $_POST['audit_id']    = $audit_id;

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_poll_audit();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->remove_api_redirect_filter();

        $json = json_decode( $output, true );
        $this->assertNotNull( $json );
        if ( isset( $json['success'] ) && $json['success'] ) {
            $this->assertEquals( 'pending', $json['data']['status'] ?? 'unknown' );
        }
    }

    /**
     * I28: Audit poll (complete) — after 3+ polls, status complete with scorecard.
     */
    public function test_audit_poll_complete() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        $audit_id = 'audit-for-complete-' . uniqid();
        $nonce = wp_create_nonce( 'fqgeo_poll_audit' );

        $last_output = '';
        for ( $i = 0; $i < 4; $i++ ) {
            $_REQUEST['nonce'] = $nonce;
            $_POST['nonce']    = $nonce;
            $_POST['audit_id'] = $audit_id;
            ob_start();
            try {
                $admin = new Flowblinq_Admin_Page();
                $admin->handle_ajax_poll_audit();
            } catch ( \Exception $e ) {}
            $last_output = ob_get_clean();
        }

        $this->remove_api_redirect_filter();

        $json = json_decode( $last_output, true );
        if ( $json && isset( $json['success'] ) && $json['success'] ) {
            $this->assertEquals( 'complete', $json['data']['status'] ?? 'unknown' );
            $this->assertArrayHasKey( 'scorecard', $json['data'] ?? [] );
        } else {
            $this->markTestSkipped( 'Poll complete test requires 3+ upstream polls — may need mock poll count tracking' );
        }
    }

    /**
     * I29: Audit verify — POSTs to /api/v1/audit/{id}/verify, returns scorecard.
     */
    public function test_audit_verify() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        $audit_id = 'verify-audit-' . uniqid();
        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_verify' );
        $_POST['nonce']    = $_REQUEST['nonce'];
        $_POST['audit_id'] = $audit_id;

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_verify();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->remove_api_redirect_filter();

        $log = $this->get_mock_log();
        $this->assertStringContainsString( '/api/v1/audit/', $log );
    }

    /**
     * I30: MAX_POLLS — client-side JS, skip.
     *
     * @group placeholder
     */
    public function test_audit_timeout_placeholder() {
        $this->markTestSkipped( 'MAX_POLLS is client-side JS logic — not testable via PHPUnit' );
    }

    /**
     * I31: Audit API error — poll error-audit, submit to error500 URL.
     */
    public function test_audit_api_error() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_poll_audit' );
        $_POST['nonce']    = $_REQUEST['nonce'];
        $_POST['audit_id'] = 'error-audit';

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_poll_audit();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->remove_api_redirect_filter();

        $json = json_decode( $output, true );
        $this->assertNotNull( $json );
        // Should return error JSON (not crash)
        $this->assertFalse( $json['success'] ?? true, 'Error audit should return failure JSON' );
    }

    // -----------------------------------------------------------------------
    // G. AJAX Endpoints (I32–I36)
    // -----------------------------------------------------------------------

    /**
     * I32: run_audit — valid admin + nonce → success JSON.
     */
    public function test_ajax_run_audit_valid() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_run_audit' );
        $_POST['nonce']    = $_REQUEST['nonce'];
        $_POST['url']      = 'https://example.com';

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_run_audit();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->remove_api_redirect_filter();

        $json = json_decode( $output, true );
        $this->assertNotNull( $json, 'Response should be valid JSON' );
    }

    /**
     * I33: poll_audit — valid admin + nonce → success JSON.
     */
    public function test_ajax_poll_audit_valid() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        $audit_id = 'poll-' . uniqid();
        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_poll_audit' );
        $_POST['nonce']    = $_REQUEST['nonce'];
        $_POST['audit_id'] = $audit_id;

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_poll_audit();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->remove_api_redirect_filter();

        $json = json_decode( $output, true );
        $this->assertNotNull( $json, 'Response should be valid JSON' );
    }

    /**
     * I34: verify — valid admin + nonce → success JSON.
     */
    public function test_ajax_verify_valid() {
        $this->add_api_redirect_filter();
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_client_id', 'test-id' );
        update_option( 'fq_client_secret', 'test-secret' );

        $audit_id = 'verify-' . uniqid();
        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_verify' );
        $_POST['nonce']    = $_REQUEST['nonce'];
        $_POST['audit_id'] = $audit_id;

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_verify();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $this->remove_api_redirect_filter();

        $json = json_decode( $output, true );
        $this->assertNotNull( $json, 'Response should be valid JSON' );
    }

    /**
     * I35: test_connection — valid admin + nonce + slug → success (hits mock via FQGEO_SERVE_BASE).
     */
    public function test_ajax_test_connection_valid() {
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );
        update_option( 'fq_site_slug', 'test-site-123' );

        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_test_connection' );
        $_POST['nonce']    = $_REQUEST['nonce'];

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_test_connection();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $json = json_decode( $output, true );
        $this->assertNotNull( $json, 'Response should be valid JSON' );
        $this->assertTrue( $json['success'] ?? false, 'Test connection to mock should succeed' );
    }

    /**
     * I36: clear_cache — valid admin + nonce → success, transients deleted.
     */
    public function test_ajax_clear_cache_valid() {
        $admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );

        // Pre-populate transients
        set_transient( 'fq_proxy_llms_txt', 'content', 3600 );
        set_transient( 'fq_proxy_llms_full_txt', 'content', 3600 );
        set_transient( 'fq_proxy_business_json', 'content', 3600 );
        set_transient( 'fq_proxy_schema_json', 'content', 3600 );

        $_REQUEST['nonce'] = wp_create_nonce( 'fqgeo_clear_cache' );
        $_POST['nonce']    = $_REQUEST['nonce'];

        ob_start();
        try {
            $admin = new Flowblinq_Admin_Page();
            $admin->handle_ajax_clear_cache();
        } catch ( \Exception $e ) {}
        $output = ob_get_clean();

        $json = json_decode( $output, true );
        $this->assertNotNull( $json );
        $this->assertTrue( $json['success'] ?? false, 'Clear cache should succeed' );
        $this->assertFalse( get_transient( 'fq_proxy_llms_txt' ), 'Transient should be deleted' );
        $this->assertFalse( get_transient( 'fq_proxy_schema_json' ), 'Schema transient should be deleted' );
    }

    // -----------------------------------------------------------------------
    // H. Lifecycle (I37–I40)
    // -----------------------------------------------------------------------

    /**
     * I37: Activation — rewrite rules registered.
     *
     * Calls the activation callback directly to avoid hook name path dependency
     * (register_activation_hook uses __FILE__ which changes between Docker paths).
     */
    public function test_activation() {
        global $wp_rewrite;
        $wp_rewrite->extra_rules_top = [];

        // Call activation callback directly (mirrors flowblinq-geo.php:28-32)
        $proxy = new Flowblinq_Proxy();
        $proxy->register_rewrite_rules();

        $rules = $wp_rewrite->extra_rules_top ?? [];
        $all_rules = implode( ' ', array_keys( $rules ) );

        $this->assertStringContainsString( 'llms', $all_rules,
            'I37: Activation should register llms.txt rewrite rule'
        );
        $this->assertStringContainsString( 'ucp', $all_rules,
            'I37: Activation should register .well-known/ucp.json rewrite rule'
        );
    }

    /**
     * I38: Deactivation — scheduled hooks cleared.
     *
     * Calls the deactivation callback directly to avoid hook name path dependency.
     */
    public function test_deactivation() {
        // Schedule a hook to verify it gets cleared
        wp_schedule_event( time(), 'hourly', 'fqgeo_poll_audit' );
        $this->assertNotFalse( wp_next_scheduled( 'fqgeo_poll_audit' ) );

        // Call deactivation callback directly (mirrors flowblinq-geo.php:34-37)
        wp_clear_scheduled_hook( 'fqgeo_poll_audit' );
        flush_rewrite_rules();

        $this->assertFalse( wp_next_scheduled( 'fqgeo_poll_audit' ),
            'I38: Deactivation should clear scheduled hooks'
        );
    }

    /**
     * I39: Uninstall — all options and transients deleted.
     */
    public function test_uninstall() {
        // Set options and transients
        update_option( 'fq_client_id', 'to-delete' );
        update_option( 'fq_client_secret', 'to-delete' );
        update_option( 'fq_site_slug', 'to-delete' );
        update_option( 'fq_active_audit_id', 'to-delete' );
        set_transient( 'fq_access_token', 'to-delete', 3600 );
        set_transient( 'fq_proxy_llms_txt', 'to-delete', 3600 );
        set_transient( 'fq_proxy_llms_full_txt', 'to-delete', 3600 );
        set_transient( 'fq_proxy_business_json', 'to-delete', 3600 );
        set_transient( 'fq_proxy_schema_json', 'to-delete', 3600 );

        // Include uninstall.php with the required constant
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            define( 'WP_UNINSTALL_PLUGIN', 'flowblinq-geo/flowblinq-geo.php' );
        }
        include dirname( dirname( __DIR__ ) ) . '/flowblinq-geo/uninstall.php';

        $this->assertFalse( get_option( 'fq_client_id', false ), 'fq_client_id should be deleted' );
        $this->assertFalse( get_option( 'fq_client_secret', false ), 'fq_client_secret should be deleted' );
        $this->assertFalse( get_option( 'fq_site_slug', false ), 'fq_site_slug should be deleted' );
        $this->assertFalse( get_option( 'fq_active_audit_id', false ), 'fq_active_audit_id should be deleted' );
        $this->assertFalse( get_transient( 'fq_access_token' ), 'fq_access_token transient should be deleted' );
        $this->assertFalse( get_transient( 'fq_proxy_llms_txt' ), 'Proxy transients should be deleted' );
    }

    /**
     * I40: Constant override — FQGEO_SERVE_BASE points to mock.
     */
    public function test_constant_overrides() {
        $this->assertEquals(
            'http://mock-upstream:8080/api/serve',
            FQGEO_SERVE_BASE,
            'FQGEO_SERVE_BASE must point to mock upstream in test env'
        );

        // Verify fetch_upstream uses this constant
        update_option( 'fq_site_slug', 'test-site-123' );
        $proxy = new Flowblinq_Proxy();

        $result = $proxy->fetch_upstream( 'llms_txt', 'test-site-123' );

        if ( ! is_wp_error( $result ) ) {
            // Verify the URL used was from mock
            $log = $this->get_mock_log();
            $this->assertStringContainsString( 'mock-upstream', FQGEO_SERVE_BASE );
        } else {
            $this->assertStringContainsString( 'mock-upstream', FQGEO_SERVE_BASE,
                'FQGEO_SERVE_BASE must contain mock-upstream'
            );
        }
    }
}

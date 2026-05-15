<?php
/**
 * Flowblinq AI Boost — Proxy
 *
 * Thin proxy that catches requests to well-known paths and proxies them
 * to geo.flowblinq.com/api/serve/{slug}/*, with transient caching.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Flowblinq_Proxy {

    private static $serve_map = [
        'llms_txt'      => [ 'path' => 'llms.txt',      'type' => 'text/plain; charset=utf-8' ],
        'llms_full_txt' => [ 'path' => 'llms-full.txt',  'type' => 'text/plain; charset=utf-8' ],
        'business_json' => [ 'path' => 'business.json',  'type' => 'application/json' ],
    ];

    public function __construct() {
        add_action( 'init',               [ $this, 'register_rewrite_rules' ] );
        add_action( 'init',               [ $this, 'set_referrer_cookie' ], 1 );
        add_filter( 'query_vars',         [ $this, 'register_query_vars' ] );
        add_action( 'template_redirect',  [ $this, 'handle_serve' ] );
        add_action( 'wp_head',            [ $this, 'inject_schema_jsonld' ] );
        add_filter( 'robots_txt',         [ $this, 'append_robots_directives' ], 10, 2 );
        // Prevent WordPress canonical 301 redirect on proxy routes (e.g. /llms.txt → /llms.txt/)
        add_filter( 'redirect_canonical', function ( $redirect ) {
            if ( get_query_var( 'fqgeo_serve', '' ) ) {
                return false;
            }
            return $redirect;
        }, 10, 1 );
    }

    /**
     * Set _geo_ref first-party cookie so the GEO beacon can attribute traffic
     * from LinkedIn, Twitter, and email correctly.
     *
     * LinkedIn and Twitter strip document.referrer in the browser via rel="noreferrer".
     * PHP reads the HTTP Referer header before JS runs, giving us the true source.
     * The beacon reads this cookie and sends it as `sr` in the collect payload.
     */
    public function set_referrer_cookie() {
        if ( headers_sent() ) {
            return;
        }

        // Only set on front-end page loads — skip admin, AJAX, REST, cron.
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return;
        }

        // Already have a session cookie — don't overwrite.
        if ( ! empty( $_COOKIE['_geo_ref'] ) ) {
            return;
        }

        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
        if ( empty( $referer ) ) {
            return;
        }

        // Don't attribute internal navigation (same-site).
        $site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
        $referer_host = wp_parse_url( $referer, PHP_URL_HOST );
        if ( $referer_host === $site_host ) {
            return;
        }

        setcookie( '_geo_ref', $referer, [
            'expires'  => time() + 1800,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => false,   // beacon JS must be able to read it
            'samesite' => 'Strict',
        ] );
    }

    /**
     * Register rewrite rules for all proxy endpoints.
     */
    public function register_rewrite_rules() {
        add_rewrite_rule( '^llms\\.txt$',                'index.php?fqgeo_serve=llms_txt', 'top' );
        add_rewrite_rule( '^llms-full\\.txt$',           'index.php?fqgeo_serve=llms_full_txt', 'top' );
        add_rewrite_rule( '^\\.well-known/ucp\\.json$',  'index.php?fqgeo_serve=business_json', 'top' );
    }

    /**
     * Register fqgeo_serve query var.
     *
     * @param array $vars
     * @return array
     */
    public function register_query_vars( array $vars ) {
        $vars[] = 'fqgeo_serve';
        return $vars;
    }

    /**
     * Main proxy handler — serves content from upstream or cache.
     */
    public function handle_serve() {
        $key = get_query_var( 'fqgeo_serve', '' );
        if ( empty( $key ) ) {
            return;
        }

        if ( ! isset( self::$serve_map[ $key ] ) ) {
            return;
        }

        $slug = get_option( 'fqgeo_site_slug', '' );
        if ( empty( $slug ) ) {
            wp_die( 'Flowblinq AI Boost not configured', '', [ 'response' => 503 ] );
        }

        // Check transient cache
        $content = get_transient( 'fqgeo_proxy_' . $key );

        if ( false === $content ) {
            // Cache miss — fetch from upstream
            $result = $this->fetch_upstream( $key, $slug );

            if ( is_wp_error( $result ) ) {
                if ( $result->get_error_code() === 'fqgeo_upstream_timeout' ) {
                    status_header( 504 );
                    echo 'Gateway timeout';
                } else {
                    status_header( 502 );
                    echo 'Service temporarily unavailable';
                }
                $this->do_exit();
                return;
            }

            // Cache the successful response
            set_transient( 'fqgeo_proxy_' . $key, $result, FQGEO_CACHE_TTL );
            $content = $result;
        }

        // Serve the response
        status_header( 200 );
        $this->send_header( 'Content-Type: ' . self::$serve_map[ $key ]['type'] );
        $this->send_header( 'Cache-Control: public, max-age=3600' );
        $this->send_header( 'X-Generator: FlowBlinq GEO' );
        $this->send_header( 'X-Content-Type-Options: nosniff' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is plain-text/JSON from the controlled FlowBlinq API; binary-safe pass-through required for llms.txt fidelity.
        echo $content;
        $this->do_exit();
    }

    /**
     * Fetch content from upstream geo.flowblinq.com.
     *
     * @param string $key  Key from $serve_map
     * @param string $slug Site slug
     * @return string|WP_Error  Body string on success, WP_Error on failure
     */
    public function fetch_upstream( $key, $slug ) {
        $url = FQGEO_SERVE_BASE . '/' . rawurlencode( $slug ) . '/' . self::$serve_map[ $key ]['path'];

        $response = wp_remote_get( $url, [ 'timeout' => FQGEO_PROXY_TIMEOUT ] );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[Flowblinq AI Boost] Upstream timeout: ' . $key ); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-gated above; production-safe.
            return new WP_Error( 'fqgeo_upstream_timeout', 'Gateway timeout' );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[Flowblinq AI Boost] Upstream error: ' . $key . ' HTTP ' . $code ); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-gated above; production-safe.
            return new WP_Error( 'fqgeo_upstream_error', 'Service temporarily unavailable' );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > FQGEO_PROXY_MAX_SIZE ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[Flowblinq AI Boost] Upstream response too large: ' . $key ); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-gated above; production-safe.
            return new WP_Error( 'fqgeo_upstream_too_large', 'Response too large' );
        }

        return $body;
    }

    /**
     * Inject schema JSON-LD into <head>.
     *
     * Uses stale-while-revalidate: serves cached content immediately even if
     * expired, and triggers a single background refresh via transient lock.
     */
    public function inject_schema_jsonld() {
        $slug = get_option( 'fqgeo_site_slug', '' );
        if ( empty( $slug ) ) {
            return;
        }

        $raw = get_transient( 'fqgeo_proxy_schema_json' );

        if ( false === $raw ) {
            // Hard cache miss — check for stale value
            $stale = get_option( '_fqgeo_stale_schema_json', '' );

            if ( $stale !== '' ) {
                // Serve stale content; trigger background refresh (lock prevents stampede)
                $raw = $stale;
                $this->maybe_refresh_schema( $slug );
            } else {
                // First-ever fetch — no stale content available
                $raw = $this->fetch_schema_upstream( $slug );
                if ( false === $raw ) {
                    return;
                }
            }
        }

        $schemas = json_decode( $raw, true );
        if ( ! is_array( $schemas ) ) {
            return;
        }

        foreach ( $schemas as $schema ) {
            if ( ! is_array( $schema ) ) {
                continue;
            }
            echo '<script type="application/ld+json">'
                . wp_json_encode( $schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
                . '</script>' . "\n";
        }
    }

    /**
     * Append AI crawler directives to robots.txt.
     *
     * @param string $output Current robots.txt content
     * @param bool   $public Whether the site is public
     * @return string Modified robots.txt content
     */
    public function append_robots_directives( $output, $public ) {
        if ( ! $public ) {
            return $output;
        }

        $slug = get_option( 'fqgeo_site_slug', '' );
        if ( empty( $slug ) ) {
            return $output;
        }

        $directives = "\nUser-agent: GPTBot\n"
            . "Allow: /llms.txt\n"
            . "Allow: /llms-full.txt\n"
            . "Allow: /.well-known/ucp.json\n"
            . "\nUser-agent: ClaudeBot\n"
            . "Allow: /llms.txt\n"
            . "Allow: /llms-full.txt\n"
            . "Allow: /.well-known/ucp.json\n"
            . "\nUser-agent: PerplexityBot\n"
            . "Allow: /llms.txt\n"
            . "Allow: /llms-full.txt\n"
            . "Allow: /.well-known/ucp.json\n";

        return $output . $directives;
    }

    /**
     * Fetch schema JSON from upstream. Sets both transient and stale option.
     *
     * @param string $slug Site slug
     * @return string|false  Body on success, false on failure
     */
    private function fetch_schema_upstream( $slug ) {
        $url      = FQGEO_SERVE_BASE . '/' . rawurlencode( $slug ) . '/schema.json';
        $response = wp_remote_get( $url, [ 'timeout' => FQGEO_PROXY_TIMEOUT ] );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[Flowblinq AI Boost] Schema fetch error: timeout' ); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-gated above; production-safe.
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[Flowblinq AI Boost] Schema fetch error: HTTP ' . $code ); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-gated above; production-safe.
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > FQGEO_PROXY_MAX_SIZE ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[Flowblinq AI Boost] Schema response too large' ); } // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-gated above; production-safe.
            return false;
        }

        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) || ! isset( $decoded[0] ) ) {
            return false;
        }

        set_transient( 'fqgeo_proxy_schema_json', $body, FQGEO_CACHE_TTL );
        update_option( '_fqgeo_stale_schema_json', $body, false );
        return $body;
    }

    /**
     * Trigger a background schema refresh if no other request is already doing so.
     * Uses a short-lived lock transient to prevent cache stampede.
     *
     * @param string $slug Site slug
     */
    private function maybe_refresh_schema( $slug ) {
        // Acquire lock — only one request refreshes at a time
        if ( false !== get_transient( '_fqgeo_lock_schema_json' ) ) {
            return;
        }
        set_transient( '_fqgeo_lock_schema_json', 1, 30 );

        // Non-blocking fetch in a shutdown action so page rendering isn't delayed
        add_action( 'shutdown', function () use ( $slug ) {
            $this->fetch_schema_upstream( $slug );
            delete_transient( '_fqgeo_lock_schema_json' );
        } );
    }

    /**
     * Clear all proxy transients.
     */
    public static function clear_cache() {
        delete_transient( 'fqgeo_proxy_llms_txt' );
        delete_transient( 'fqgeo_proxy_llms_full_txt' );
        delete_transient( 'fqgeo_proxy_business_json' );
        delete_transient( 'fqgeo_proxy_schema_json' );
        delete_transient( '_fqgeo_lock_schema_json' );
        // Stale option preserved intentionally — allows stale-while-revalidate
        // to serve content immediately after cache clear
    }

    /**
     * Header wrapper — captured by test environment, calls header() in production.
     *
     * @param string $header
     */
    protected function send_header( $header ) {
        if ( ! class_exists( 'Flowblinq_GEO_Exit_Exception', false ) ) {
            header( $header ); // @codeCoverageIgnore
        } else {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- _fqgeo_ is the plugin prefix; matches existing fqgeo_* options/transients namespace.
            $GLOBALS['_fqgeo_headers_sent'][] = $header;
        }
    }

    /**
     * Exit wrapper — throws in test environment, calls exit() in production.
     */
    protected function do_exit() {
        if ( class_exists( 'Flowblinq_GEO_Exit_Exception', false ) ) {
            throw new Flowblinq_GEO_Exit_Exception( 'exit' );
        }
        exit; // @codeCoverageIgnore
    }
}

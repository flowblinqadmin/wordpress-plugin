<?php
/**
 * Flowblinq GEO — Proxy
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
        add_filter( 'query_vars',         [ $this, 'register_query_vars' ] );
        add_action( 'template_redirect',  [ $this, 'handle_serve' ] );
        add_action( 'wp_head',            [ $this, 'inject_schema_jsonld' ] );
        add_filter( 'robots_txt',         [ $this, 'append_robots_directives' ], 10, 2 );
    }

    /**
     * Register rewrite rules for all proxy endpoints.
     */
    public function register_rewrite_rules() {
        add_rewrite_rule( '^llms\\.txt$',                'index.php?fq_serve=llms_txt', 'top' );
        add_rewrite_rule( '^llms-full\\.txt$',           'index.php?fq_serve=llms_full_txt', 'top' );
        add_rewrite_rule( '^\\.well-known/ucp\\.json$',  'index.php?fq_serve=business_json', 'top' );
    }

    /**
     * Register fq_serve query var.
     *
     * @param array $vars
     * @return array
     */
    public function register_query_vars( array $vars ) {
        $vars[] = 'fq_serve';
        return $vars;
    }

    /**
     * Main proxy handler — serves content from upstream or cache.
     */
    public function handle_serve() {
        $key = get_query_var( 'fq_serve', '' );
        if ( empty( $key ) ) {
            return;
        }

        if ( ! isset( self::$serve_map[ $key ] ) ) {
            return;
        }

        $slug = get_option( 'fq_site_slug', '' );
        if ( empty( $slug ) ) {
            wp_die( 'Flowblinq GEO not configured', '', [ 'response' => 503 ] );
        }

        // Check transient cache
        $content = get_transient( 'fq_proxy_' . $key );

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
            set_transient( 'fq_proxy_' . $key, $result, FQGEO_CACHE_TTL );
            $content = $result;
        }

        // Serve the response
        status_header( 200 );
        $this->send_header( 'Content-Type: ' . self::$serve_map[ $key ]['type'] );
        $this->send_header( 'Cache-Control: public, max-age=3600' );
        $this->send_header( 'X-Generator: FlowBlinq GEO' );
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
        $url = FQGEO_SERVE_BASE . '/' . $slug . '/' . self::$serve_map[ $key ]['path'];

        $response = wp_remote_get( $url, [ 'timeout' => FQGEO_PROXY_TIMEOUT ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[Flowblinq GEO] Upstream timeout: ' . $key );
            return new WP_Error( 'fqgeo_upstream_timeout', 'Gateway timeout' );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            error_log( '[Flowblinq GEO] Upstream error: ' . $key . ' HTTP ' . $code );
            return new WP_Error( 'fqgeo_upstream_error', 'Service temporarily unavailable' );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > FQGEO_PROXY_MAX_SIZE ) {
            error_log( '[Flowblinq GEO] Upstream response too large: ' . $key );
            return new WP_Error( 'fqgeo_upstream_too_large', 'Response too large' );
        }

        return $body;
    }

    /**
     * Inject schema JSON-LD into <head>.
     */
    public function inject_schema_jsonld() {
        $slug = get_option( 'fq_site_slug', '' );
        if ( empty( $slug ) ) {
            return;
        }

        $raw = get_transient( 'fq_proxy_schema_json' );

        if ( false === $raw ) {
            // Cache miss — fetch from upstream
            $url = FQGEO_SERVE_BASE . '/' . $slug . '/schema.json';
            $response = wp_remote_get( $url, [ 'timeout' => FQGEO_PROXY_TIMEOUT ] );

            if ( is_wp_error( $response ) ) {
                error_log( '[Flowblinq GEO] Schema fetch error: timeout' );
                return;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 200 ) {
                error_log( '[Flowblinq GEO] Schema fetch error: HTTP ' . $code );
                return;
            }

            $body = wp_remote_retrieve_body( $response );
            if ( strlen( $body ) > FQGEO_PROXY_MAX_SIZE ) {
                error_log( '[Flowblinq GEO] Schema response too large' );
                return;
            }

            $decoded = json_decode( $body, true );
            if ( ! is_array( $decoded ) || ! isset( $decoded[0] ) ) {
                // Must be a numerically-indexed array (list of schemas)
                return;
            }

            set_transient( 'fq_proxy_schema_json', $body, FQGEO_CACHE_TTL );
            $raw = $body;
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
        $slug = get_option( 'fq_site_slug', '' );
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
     * Clear all proxy transients.
     */
    public static function clear_cache() {
        delete_transient( 'fq_proxy_llms_txt' );
        delete_transient( 'fq_proxy_llms_full_txt' );
        delete_transient( 'fq_proxy_business_json' );
        delete_transient( 'fq_proxy_schema_json' );
    }

    /**
     * Header wrapper — captured by test environment, calls header() in production.
     *
     * @param string $header
     */
    protected function send_header( $header ) {
        if ( ! class_exists( 'FQ_Exit_Exception', false ) ) {
            header( $header ); // @codeCoverageIgnore
        }
        $GLOBALS['_fq_headers_sent'][] = $header;
    }

    /**
     * Exit wrapper — throws in test environment, calls exit() in production.
     */
    protected function do_exit() {
        if ( class_exists( 'FQ_Exit_Exception', false ) ) {
            throw new FQ_Exit_Exception( 'exit' );
        }
        exit; // @codeCoverageIgnore
    }
}

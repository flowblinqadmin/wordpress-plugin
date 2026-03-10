<?php
/**
 * Flowblinq GEO — Injector
 *
 * Injects schema blocks and llms.txt link into the site front-end.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Flowblinq_Injector {

    public function __construct() {
        add_action( 'wp_head',  [ $this, 'output_schema_blocks' ] );
        add_action( 'wp_head',  [ $this, 'output_llms_txt_link' ] );
        add_filter( 'query_vars', [ $this, 'register_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_serve_llms_txt' ] );
        add_action( 'init', [ $this, 'add_rewrite_rule' ] );
    }

    /**
     * Apply all injections from audit data.
     *
     * @param array $audit_data Response from GET /api/v1/audit/{id}
     */
    public function inject_all( array $audit_data ): void {
        update_option( 'fq_active_audit_id', $audit_data['audit_id'] ?? '' );

        $schema_url  = $audit_data['files']['schema_json_url']  ?? '';
        $llms_url    = $audit_data['files']['llms_txt_url']     ?? '';

        if ( $schema_url ) {
            $this->inject_schema_blocks( $schema_url );
        }

        if ( $llms_url ) {
            $this->register_llms_txt_rewrite( $llms_url );
        }

        $this->inject_llms_txt_link();
    }

    /**
     * Fetch schema JSON from the GEO platform and store in wp_options.
     *
     * @param string $schema_url
     */
    public function inject_schema_blocks( string $schema_url ): void {
        if ( wp_parse_url( $schema_url, PHP_URL_HOST ) !== 'geo.flowblinq.com' ) {
            return;
        }

        $response = wp_remote_get( $schema_url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) {
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return;
        }

        $body = wp_remote_retrieve_body( $response );
        // Validate it's JSON and within size limit before storing
        if ( strlen( $body ) > 512 * 1024 ) {
            return;
        }
        if ( null === json_decode( $body ) ) {
            return;
        }

        update_option( 'fq_schema_blocks', $body );
    }

    /**
     * Output the stored schema JSON-LD in <head>.
     */
    public function output_schema_blocks(): void {
        $schema = get_option( 'fq_schema_blocks', '' );
        if ( ! $schema ) {
            return;
        }
        echo '<script type="application/ld+json">' . wp_json_encode( json_decode( $schema ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }

    /**
     * Output the llms.txt <link> tag in <head>.
     */
    public function output_llms_txt_link(): void {
        if ( ! get_option( 'fq_llms_txt_content', '' ) ) {
            return;
        }
        echo '<link rel="alternate" type="text/plain" href="' . esc_url( home_url( '/flowblinq-llms.txt' ) ) . '">' . "\n";
    }

    /**
     * Fetch llms.txt content from the GEO platform and store in wp_options.
     * Also registers the rewrite rule so /flowblinq-llms.txt is served by WP.
     *
     * @param string $source_url
     */
    public function register_llms_txt_rewrite( string $source_url ): void {
        if ( wp_parse_url( $source_url, PHP_URL_HOST ) !== 'geo.flowblinq.com' ) {
            return;
        }

        $response = wp_remote_get( $source_url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) {
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return;
        }

        $content = wp_remote_retrieve_body( $response );
        update_option( 'fq_llms_txt_content', $content );

        // Flush rewrite rules so the new rule takes effect
        flush_rewrite_rules();
    }

    /**
     * Register the rewrite rule for /flowblinq-llms.txt on 'init'.
     */
    public function add_rewrite_rule(): void {
        add_rewrite_rule( '^flowblinq-llms\.txt$', 'index.php?fq_llms_txt=1', 'top' );
    }

    /**
     * Register fq_llms_txt query var.
     *
     * @param array $vars
     * @return array
     */
    public function register_query_var( array $vars ): array {
        $vars[] = 'fq_llms_txt';
        return $vars;
    }

    /**
     * Serve the llms.txt content when the rewrite rule matches.
     */
    public function maybe_serve_llms_txt(): void {
        if ( ! get_query_var( 'fq_llms_txt' ) ) {
            return;
        }

        $content = get_option( 'fq_llms_txt_content', '' );
        if ( ! $content ) {
            wp_die( 'Not found', '', [ 'response' => 404 ] );
        }

        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Cache-Control: public, max-age=86400' );
        // Plain-text output — esc_html() would corrupt & < > in URLs and markdown
        echo $content;
        exit;
    }
}

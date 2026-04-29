<?php
/**
 * Flowblinq GEO — Admin Page
 *
 * Registers WP Admin pages for settings and the GEO audit tool.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Flowblinq_Admin_Page {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_pages' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_fqgeo_run_audit',  [ $this, 'handle_ajax_run_audit' ] );
        add_action( 'wp_ajax_fqgeo_poll_audit', [ $this, 'handle_ajax_poll_audit' ] );
        add_action( 'wp_ajax_fqgeo_verify',     [ $this, 'handle_ajax_verify' ] );
        add_action( 'wp_ajax_fqgeo_test_connection', [ $this, 'handle_ajax_test_connection' ] );
        add_action( 'wp_ajax_fqgeo_clear_cache', [ $this, 'handle_ajax_clear_cache' ] );
        add_action( 'admin_init',               [ $this, 'register_settings' ] );
    }

    public function add_menu_pages() {
        add_options_page(
            __( 'Flowblinq GEO Settings', 'flowblinq-geo' ),
            __( 'Flowblinq GEO', 'flowblinq-geo' ),
            'manage_options',
            'fqgeo-settings',
            [ $this, 'render_settings_page' ]
        );

        add_management_page(
            __( 'GEO Audit', 'flowblinq-geo' ),
            __( 'GEO Audit', 'flowblinq-geo' ),
            'manage_options',
            'fqgeo-audit',
            [ $this, 'render_audit_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'fqgeo_settings', 'fq_client_id', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'fqgeo_settings', 'fq_client_secret', [
            'sanitize_callback' => function ( $value ) {
                if ( $value === '••••••••' ) {
                    return get_option( 'fq_client_secret', '' );
                }
                return sanitize_text_field( $value );
            },
        ] );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'tools_page_fqgeo-audit' && $hook !== 'settings_page_fqgeo-settings' ) {
            return;
        }
        wp_enqueue_style( 'fqgeo-admin', FQGEO_PLUGIN_URL . 'assets/admin.css', [], FQGEO_VERSION );
        wp_enqueue_script( 'fqgeo-admin', FQGEO_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], FQGEO_VERSION, true );
        wp_localize_script( 'fqgeo-admin', 'fqgeo', [
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce_run'        => wp_create_nonce( 'fqgeo_run_audit' ),
            'nonce_poll'       => wp_create_nonce( 'fqgeo_poll_audit' ),
            'nonce_verify'     => wp_create_nonce( 'fqgeo_verify' ),
            'nonce_test'       => wp_create_nonce( 'fqgeo_test_connection' ),
            'nonce_clear'      => wp_create_nonce( 'fqgeo_clear_cache' ),
            'site_url'         => get_site_url(),
            'site_slug'        => get_option( 'fq_site_slug', '' ),
            'active_audit_id'  => get_option( 'fq_active_audit_id', '' ),
            'max_polls'        => FQGEO_MAX_POLLS,
        ] );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Flowblinq GEO Settings', 'flowblinq-geo' ); ?></h1>

            <?php if ( ! get_option( 'permalink_structure' ) ) : ?>
                <div class="notice notice-error inline">
                    <p><?php esc_html_e( 'Flowblinq GEO requires "pretty permalinks" to be enabled. Go to Settings → Permalinks and select any structure other than "Plain".', 'flowblinq-geo' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'fqgeo_settings' );
                do_settings_sections( 'fqgeo_settings' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fq_client_id"><?php esc_html_e( 'Client ID', 'flowblinq-geo' ); ?></label></th>
                        <td>
                            <input type="text" id="fq_client_id" name="fq_client_id"
                                   value="<?php echo esc_attr( get_option( 'fq_client_id', '' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e( 'Your Flowblinq API Client ID.', 'flowblinq-geo' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fq_client_secret"><?php esc_html_e( 'Client Secret', 'flowblinq-geo' ); ?></label></th>
                        <td>
                            <?php $has_secret = (bool) get_option( 'fq_client_secret', '' ); ?>
                            <input type="password" id="fq_client_secret" name="fq_client_secret"
                                   value="<?php echo $has_secret ? '••••••••' : ''; ?>"
                                   class="regular-text" autocomplete="new-password" />
                            <p class="description"><?php esc_html_e( 'Your Flowblinq API Client Secret. Stored securely in WordPress options.', 'flowblinq-geo' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Site Slug', 'flowblinq-geo' ); ?></th>
                        <td>
                            <?php $slug = get_option( 'fq_site_slug', '' ); ?>
                            <code><?php echo $slug ? esc_html( $slug ) : esc_html__( '(auto-populated after first audit)', 'flowblinq-geo' ); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Proxy Status', 'flowblinq-geo' ); ?></th>
                        <td>
                            <span id="fqgeo-connection-status">&mdash;</span>
                            <button type="button" id="fqgeo-test-connection" class="button"><?php esc_html_e( 'Test Connection', 'flowblinq-geo' ); ?></button>
                            <button type="button" id="fqgeo-clear-cache" class="button"><?php esc_html_e( 'Clear Cache', 'flowblinq-geo' ); ?></button>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <p>
                <?php
                printf(
                    wp_kses(
                        /* translators: %s: Flowblinq dashboard settings URL */
                        __( 'Get your credentials at <a href="%s" target="_blank" rel="noopener">geo.flowblinq.com → Settings → API</a>.', 'flowblinq-geo' ),
                        [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                    ),
                    esc_url( 'https://geo.flowblinq.com/dashboard/settings' )
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function render_audit_page() {
        $client_id     = get_option( 'fq_client_id', '' );
        $client_secret = get_option( 'fq_client_secret', '' );
        $configured    = $client_id && $client_secret;
        ?>
        <div class="wrap" id="fqgeo-wrap">
            <h1><?php esc_html_e( 'GEO Audit', 'flowblinq-geo' ); ?></h1>

            <?php if ( ! $configured ) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        printf(
                            wp_kses(
                                /* translators: %s: WP admin URL of the Flowblinq settings page */
                                __( 'Please <a href="%s">configure your Flowblinq API credentials</a> first.', 'flowblinq-geo' ),
                                [ 'a' => [ 'href' => [] ] ]
                            ),
                            esc_url( admin_url( 'options-general.php?page=fqgeo-settings' ) )
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <?php /* translators: %s: site URL of the WordPress installation */ ?>
                <p><?php printf( esc_html__( 'Site: %s', 'flowblinq-geo' ), esc_html( get_site_url() ) ); ?></p>

                <div id="fqgeo-actions">
                    <button id="fqgeo-run" class="button button-primary"><?php esc_html_e( 'Run Free Audit', 'flowblinq-geo' ); ?></button>
                    <button id="fqgeo-verify" class="button" style="display:none"><?php esc_html_e( 'Verify My Changes', 'flowblinq-geo' ); ?></button>
                </div>

                <div id="fqgeo-progress" style="display:none">
                    <p id="fqgeo-status"><?php esc_html_e( 'Starting audit…', 'flowblinq-geo' ); ?></p>
                    <progress id="fqgeo-bar" max="100" value="0"></progress>
                </div>

                <div id="fqgeo-results" style="display:none">
                    <h2><?php esc_html_e( 'Audit Results', 'flowblinq-geo' ); ?></h2>
                    <div id="fqgeo-scorecard"></div>

                    <?php if ( get_option( 'fq_site_slug' ) ) : ?>
                        <div class="notice notice-success inline">
                            <p><?php esc_html_e( 'Proxy is active — your GEO files are being served automatically.', 'flowblinq-geo' ); ?></p>
                        </div>
                    <?php endif; ?>

                    <div id="fqgeo-comparison" style="display:none">
                        <h3><?php esc_html_e( 'Before / After', 'flowblinq-geo' ); ?></h3>
                        <table class="widefat" id="fqgeo-before-after">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Metric', 'flowblinq-geo' ); ?></th>
                                    <th><?php esc_html_e( 'Before', 'flowblinq-geo' ); ?></th>
                                    <th><?php esc_html_e( 'After', 'flowblinq-geo' ); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── AJAX handlers ─────────────────────────────────────────────────────────

    private function verify_request( $action ) {
        check_ajax_referer( $action, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
        }
    }

    private function get_api_client() {
        return new Flowblinq_API_Client(
            get_option( 'fq_client_id', '' ),
            get_option( 'fq_client_secret', '' )
        );
    }

    public function handle_ajax_run_audit() {
        $this->verify_request( 'fqgeo_run_audit' );

        $result = $this->get_api_client()->submit_audit( get_site_url() );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        if ( ! empty( $result['audit_id'] ) ) {
            update_option( 'fq_active_audit_id', $result['audit_id'] );
        }

        if ( ! empty( $result['slug'] ) ) {
            $clean_slug = sanitize_text_field( $result['slug'] );
            if ( preg_match( '/^[a-z0-9\-]+$/i', $clean_slug ) ) {
                update_option( 'fq_site_slug', $clean_slug );
            } else {
                wp_send_json_error( [ 'message' => 'Invalid slug format returned by API' ] );
            }
        }

        wp_send_json_success( $result );
    }

    public function handle_ajax_poll_audit() {
        $this->verify_request( 'fqgeo_poll_audit' );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified via check_ajax_referer in $this->verify_request() above.
        $audit_id = isset( $_POST['audit_id'] ) ? sanitize_text_field( wp_unslash( $_POST['audit_id'] ) ) : '';
        if ( ! $audit_id ) {
            wp_send_json_error( [ 'message' => 'audit_id required' ] );
        }

        $result = $this->get_api_client()->get_audit( $audit_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    public function handle_ajax_verify() {
        $this->verify_request( 'fqgeo_verify' );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified via check_ajax_referer in $this->verify_request() above.
        $audit_id = isset( $_POST['audit_id'] ) ? sanitize_text_field( wp_unslash( $_POST['audit_id'] ) ) : '';
        if ( ! $audit_id ) {
            wp_send_json_error( [ 'message' => 'audit_id required' ] );
        }

        $result = $this->get_api_client()->verify_audit( $audit_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    public function handle_ajax_test_connection() {
        $this->verify_request( 'fqgeo_test_connection' );

        $slug = get_option( 'fq_site_slug', '' );
        if ( ! $slug ) {
            wp_send_json_error( [ 'message' => 'Site slug not configured. Run an audit first.' ] );
        }

        $url      = FQGEO_SERVE_BASE . '/' . rawurlencode( $slug ) . '/llms.txt';
        $response = wp_remote_get( $url, [ 'timeout' => FQGEO_PROXY_TIMEOUT ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Connection failed: ' . $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 200 ) {
            wp_send_json_success( [ 'message' => 'Connected — proxy is working.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Upstream returned HTTP ' . $code ] );
        }
    }

    public function handle_ajax_clear_cache() {
        $this->verify_request( 'fqgeo_clear_cache' );
        Flowblinq_Proxy::clear_cache();
        wp_send_json_success( [ 'message' => 'Cache cleared.' ] );
    }
}

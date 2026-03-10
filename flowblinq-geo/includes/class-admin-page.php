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
        add_action( 'wp_ajax_fqgeo_apply',      [ $this, 'handle_ajax_apply' ] );
        add_action( 'wp_ajax_fqgeo_verify',     [ $this, 'handle_ajax_verify' ] );
        add_action( 'admin_init',               [ $this, 'register_settings' ] );
    }

    public function add_menu_pages(): void {
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

    public function register_settings(): void {
        register_setting( 'fqgeo_settings', 'fq_client_id', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'fqgeo_settings', 'fq_client_secret', [
            'sanitize_callback' => function ( $value ) {
                // If the masked placeholder is submitted, keep the existing secret
                if ( $value === '••••••••' ) {
                    return get_option( 'fq_client_secret', '' );
                }
                return sanitize_text_field( $value );
            },
        ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'tools_page_fqgeo-audit' ) {
            return;
        }
        wp_enqueue_style( 'fqgeo-admin', FQGEO_PLUGIN_URL . 'assets/admin.css', [], FQGEO_VERSION );
        wp_enqueue_script( 'fqgeo-admin', FQGEO_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], FQGEO_VERSION, true );
        wp_localize_script( 'fqgeo-admin', 'fqgeo', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce_run'    => wp_create_nonce( 'fqgeo_run_audit' ),
            'nonce_poll'   => wp_create_nonce( 'fqgeo_poll_audit' ),
            'nonce_apply'  => wp_create_nonce( 'fqgeo_apply' ),
            'nonce_verify' => wp_create_nonce( 'fqgeo_verify' ),
            'site_url'   => get_site_url(),
            'active_audit_id' => get_option( 'fq_active_audit_id', '' ),
        ] );
    }

    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Flowblinq GEO Settings', 'flowblinq-geo' ); ?></h1>
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
                </table>
                <?php submit_button(); ?>
            </form>
            <p>
                <?php
                printf(
                    /* translators: %s: URL to Flowblinq API settings */
                    wp_kses(
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

    public function render_audit_page(): void {
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
                                /* translators: %s: settings page URL */
                                __( 'Please <a href="%s">configure your Flowblinq API credentials</a> first.', 'flowblinq-geo' ),
                                [ 'a' => [ 'href' => [] ] ]
                            ),
                            esc_url( admin_url( 'options-general.php?page=fqgeo-settings' ) )
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <p><?php printf( esc_html__( 'Site: %s', 'flowblinq-geo' ), esc_html( get_site_url() ) ); ?></p>

                <div id="fqgeo-actions">
                    <button id="fqgeo-run" class="button button-primary"><?php esc_html_e( 'Run Free Audit', 'flowblinq-geo' ); ?></button>
                    <button id="fqgeo-apply" class="button" style="display:none"><?php esc_html_e( 'Apply Optimizations', 'flowblinq-geo' ); ?></button>
                    <button id="fqgeo-verify" class="button" style="display:none"><?php esc_html_e( 'Verify My Changes', 'flowblinq-geo' ); ?></button>
                </div>

                <div id="fqgeo-progress" style="display:none">
                    <p id="fqgeo-status"><?php esc_html_e( 'Starting audit…', 'flowblinq-geo' ); ?></p>
                    <progress id="fqgeo-bar" max="100" value="0"></progress>
                </div>

                <div id="fqgeo-results" style="display:none">
                    <h2><?php esc_html_e( 'Audit Results', 'flowblinq-geo' ); ?></h2>
                    <div id="fqgeo-scorecard"></div>
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

    private function verify_request( string $action ): void {
        check_ajax_referer( $action, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
        }
    }

    private function get_api_client(): Flowblinq_API_Client {
        return new Flowblinq_API_Client(
            get_option( 'fq_client_id', '' ),
            get_option( 'fq_client_secret', '' )
        );
    }

    public function handle_ajax_run_audit(): void {
        $this->verify_request( 'fqgeo_run_audit' );

        $result = $this->get_api_client()->submit_audit( get_site_url() );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // Store audit_id for polling
        if ( ! empty( $result['audit_id'] ) ) {
            update_option( 'fq_active_audit_id', $result['audit_id'] );
        }

        wp_send_json_success( $result );
    }

    public function handle_ajax_poll_audit(): void {
        $this->verify_request( 'fqgeo_poll_audit' );

        $audit_id = sanitize_text_field( $_POST['audit_id'] ?? '' );
        if ( ! $audit_id ) {
            wp_send_json_error( [ 'message' => 'audit_id required' ] );
        }

        $result = $this->get_api_client()->get_audit( $audit_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    public function handle_ajax_apply(): void {
        $this->verify_request( 'fqgeo_apply' );

        $audit_id = sanitize_text_field( $_POST['audit_id'] ?? '' );
        if ( ! $audit_id ) {
            wp_send_json_error( [ 'message' => 'audit_id required' ] );
        }

        $audit = $this->get_api_client()->get_audit( $audit_id );
        if ( is_wp_error( $audit ) ) {
            wp_send_json_error( [ 'message' => $audit->get_error_message() ] );
        }

        $injector = new Flowblinq_Injector();
        $injector->inject_all( $audit );

        wp_send_json_success( [ 'message' => 'Optimizations applied.' ] );
    }

    public function handle_ajax_verify(): void {
        $this->verify_request( 'fqgeo_verify' );

        $audit_id = sanitize_text_field( $_POST['audit_id'] ?? '' );
        if ( ! $audit_id ) {
            wp_send_json_error( [ 'message' => 'audit_id required' ] );
        }

        $result = $this->get_api_client()->verify_audit( $audit_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }
}

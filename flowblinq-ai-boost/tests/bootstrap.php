<?php
/**
 * Test bootstrap — standalone WordPress function mocks.
 *
 * This file defines stubs for WordPress core functions so we can unit-test
 * plugin classes without a full WordPress installation.
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'WP_UNINSTALL_PLUGIN', true );

// ── Global state stores ─────────────────────────────────────────────────────

$GLOBALS['_fq_options']    = [];
$GLOBALS['_fq_transients'] = [];
$GLOBALS['_fq_actions']    = [];
$GLOBALS['_fq_filters']    = [];
$GLOBALS['_fq_query_vars'] = [];
$GLOBALS['_fq_rewrite_rules'] = [];
$GLOBALS['_fq_remote_responses'] = [];   // stack: each call shifts one off
$GLOBALS['_fq_wp_die_calls'] = [];
$GLOBALS['_fq_headers_sent'] = [];
$GLOBALS['_fq_output'] = '';
$GLOBALS['_fq_exit_called'] = false;
$GLOBALS['_fq_current_query_vars'] = [];

// ── WordPress function stubs ────────────────────────────────────────────────

function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['_fq_options'] ) ? $GLOBALS['_fq_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
    $GLOBALS['_fq_options'][ $key ] = $value;
    return true;
}

function delete_option( $key ) {
    unset( $GLOBALS['_fq_options'][ $key ] );
    return true;
}

function get_transient( $key ) {
    return array_key_exists( $key, $GLOBALS['_fq_transients'] ) ? $GLOBALS['_fq_transients'][ $key ] : false;
}

function set_transient( $key, $value, $ttl = 0 ) {
    $GLOBALS['_fq_transients'][ $key ] = $value;
    return true;
}

function delete_transient( $key ) {
    unset( $GLOBALS['_fq_transients'][ $key ] );
    return true;
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['_fq_actions'][] = [ 'tag' => $tag, 'callback' => $callback, 'priority' => $priority ];
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['_fq_filters'][] = [ 'tag' => $tag, 'callback' => $callback, 'priority' => $priority ];
}

function add_rewrite_rule( $regex, $redirect, $after = 'bottom' ) {
    $GLOBALS['_fq_rewrite_rules'][] = [ 'regex' => $regex, 'redirect' => $redirect, 'after' => $after ];
}

function flush_rewrite_rules() {
    // no-op
}

function get_query_var( $var, $default = '' ) {
    return isset( $GLOBALS['_fq_current_query_vars'][ $var ] ) ? $GLOBALS['_fq_current_query_vars'][ $var ] : $default;
}

function wp_die( $message = '', $title = '', $args = [] ) {
    $GLOBALS['_fq_wp_die_calls'][] = [ 'message' => $message, 'title' => $title, 'args' => $args ];
    throw new FQ_WP_Die_Exception( $message, isset( $args['response'] ) ? $args['response'] : 0 );
}

function wp_remote_get( $url, $args = [] ) {
    return _fq_mock_remote( 'GET', $url, $args );
}

function wp_remote_post( $url, $args = [] ) {
    return _fq_mock_remote( 'POST', $url, $args );
}

function _fq_mock_remote( $method, $url, $args ) {
    $GLOBALS['_fq_last_remote_url'] = $url;
    $GLOBALS['_fq_last_remote_args'] = $args;
    $GLOBALS['_fq_remote_call_log'][] = [ 'method' => $method, 'url' => $url, 'args' => $args ];

    if ( ! empty( $GLOBALS['_fq_remote_responses'] ) ) {
        return array_shift( $GLOBALS['_fq_remote_responses'] );
    }
    return new WP_Error( 'http_request_failed', 'No mock response configured' );
}

function wp_remote_retrieve_response_code( $response ) {
    if ( is_wp_error( $response ) ) return 0;
    return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
    if ( is_wp_error( $response ) ) return '';
    return isset( $response['body'] ) ? $response['body'] : '';
}

function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
    return json_encode( $data, $flags, $depth );
}

function esc_html( $text ) {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html_e( $text, $domain = 'default' ) {
    echo esc_html( $text );
}

function esc_attr( $text ) {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
    return filter_var( $url, FILTER_SANITIZE_URL );
}

function sanitize_text_field( $str ) {
    return trim( strip_tags( $str ) );
}

function status_header( $code ) {
    $GLOBALS['_fq_headers_sent'][] = "HTTP/1.1 $code";
}

// Override native header() to capture in tests — use namespace trick not available,
// so we rely on the proxy using our status_header() for status codes.
// For Content-Type/Cache-Control headers, they'll trigger PHP warnings in CLI
// but tests still pass on output/status assertions.

function register_activation_hook( $file, $callback ) {
    $GLOBALS['_fq_activation_hooks'][] = $callback;
}

function register_deactivation_hook( $file, $callback ) {
    $GLOBALS['_fq_deactivation_hooks'][] = $callback;
}

function wp_create_nonce( $action ) {
    return 'nonce_' . $action;
}

function check_ajax_referer( $action, $query_arg ) {
    return true;
}

function current_user_can( $capability ) {
    return true;
}

function wp_send_json_success( $data = null ) {
    $GLOBALS['_fq_json_response'] = [ 'success' => true, 'data' => $data ];
    throw new FQ_Ajax_Exit_Exception( 'json_success' );
}

function wp_send_json_error( $data = null, $status_code = null ) {
    $GLOBALS['_fq_json_response'] = [ 'success' => false, 'data' => $data ];
    throw new FQ_Ajax_Exit_Exception( 'json_error' );
}

function plugin_dir_path( $file ) {
    return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
    return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function load_plugin_textdomain() {}
function admin_url( $path = '' ) { return 'http://example.com/wp-admin/' . $path; }
function home_url( $path = '' ) { return 'http://example.com' . $path; }
function get_site_url() { return 'http://example.com'; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function add_options_page() {}
function add_management_page() {}
function register_setting() {}
function settings_fields() {}
function do_settings_sections() {}
function submit_button() {}
function wp_kses( $string, $allowed ) { return $string; }
function __( $text, $domain = 'default' ) { return $text; }
function is_admin() { return true; }
function wp_clear_scheduled_hook() {}
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }

// ── Minimal WP_Error class ──────────────────────────────────────────────────

class WP_Error {
    private $code;
    private $message;
    private $data;

    public function __construct( $code = '', $message = '', $data = '' ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

// ── Test exception types ────────────────────────────────────────────────────

class FQ_WP_Die_Exception extends \RuntimeException {}
class FQ_Ajax_Exit_Exception extends \RuntimeException {}
class Flowblinq_GEO_Exit_Exception extends \RuntimeException {}

// ── Helper to build mock HTTP response ──────────────────────────────────────

function fq_mock_response( $code, $body ) {
    return [
        'response' => [ 'code' => $code ],
        'body'     => $body,
    ];
}

// ── Reset all global state ──────────────────────────────────────────────────

function fq_reset_state() {
    $GLOBALS['_fq_options']          = [];
    $GLOBALS['_fq_transients']       = [];
    $GLOBALS['_fq_actions']          = [];
    $GLOBALS['_fq_filters']          = [];
    $GLOBALS['_fq_query_vars']       = [];
    $GLOBALS['_fq_rewrite_rules']    = [];
    $GLOBALS['_fq_remote_responses'] = [];
    $GLOBALS['_fq_remote_call_log']  = [];
    $GLOBALS['_fq_wp_die_calls']     = [];
    $GLOBALS['_fq_headers_sent']     = [];
    $GLOBALS['_fq_output']           = '';
    $GLOBALS['_fq_exit_called']      = false;
    $GLOBALS['_fq_current_query_vars'] = [];
    $GLOBALS['_fq_json_response']    = null;
}

<?php
/**
 * Flowblinq AI Boost — API Client
 *
 * Handles all HTTP communication with geo.flowblinq.com.
 * Uses WordPress HTTP API (wp_remote_*) — no cURL direct calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Fqgeo_API_Client {

    private string $base_url    = 'https://geo.flowblinq.com';
    private string $client_id;
    private string $client_secret;

    public function __construct( string $client_id, string $client_secret ) {
        $this->client_id     = $client_id;
        $this->client_secret = $client_secret;
    }

    /**
     * Get (or refresh) an access token.
     * Caches in transient 'fqgeo_access_token' for 3500 seconds (just under 1hr TTL).
     *
     * @return string|WP_Error
     */
    public function get_token() {
        $cached = get_transient( 'fqgeo_access_token' );
        if ( $cached ) {
            return $cached;
        }

        $response = wp_remote_post(
            $this->base_url . '/api/oauth/token',
            [
                'timeout' => 10,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || empty( $body['access_token'] ) ) {
            return new WP_Error( 'fqgeo_token_error', $body['error'] ?? 'token_request_failed', [ 'status' => $code ] );
        }

        set_transient( 'fqgeo_access_token', $body['access_token'], 3500 );
        return $body['access_token'];
    }

    /**
     * Submit a URL for a new GEO audit.
     *
     * @param string $url
     * @return array|WP_Error
     */
    public function submit_audit( string $url ) {
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_remote_post(
            $this->base_url . '/api/v1/audit',
            [
                'timeout' => 15,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'body'    => wp_json_encode( [ 'url' => $url ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! in_array( $code, [ 200, 201, 409 ], true ) ) {
            return new WP_Error( 'fqgeo_audit_error', $body['error'] ?? 'submit_failed', [ 'status' => $code, 'body' => $body ] );
        }

        return $body;
    }

    /**
     * Poll an audit by ID.
     *
     * @param string $audit_id
     * @return array|WP_Error
     */
    public function get_audit( string $audit_id ) {
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_remote_get(
            $this->base_url . '/api/v1/audit/' . rawurlencode( $audit_id ),
            [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return new WP_Error( 'fqgeo_get_audit_error', $body['error'] ?? 'get_audit_failed', [ 'status' => $code ] );
        }

        return $body;
    }

    /**
     * Trigger the post-optimization second run.
     *
     * @param string $audit_id
     * @return array|WP_Error
     */
    public function verify_audit( string $audit_id ) {
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_remote_post(
            $this->base_url . '/api/v1/audit/' . rawurlencode( $audit_id ) . '/verify',
            [
                'timeout' => 15,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'body'    => '{}',
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return new WP_Error( 'fqgeo_verify_error', $body['error'] ?? 'verify_failed', [ 'status' => $code, 'body' => $body ] );
        }

        return $body;
    }

    /**
     * Get account credit balance and usage.
     *
     * @return array|WP_Error
     */
    public function get_account() {
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_remote_get(
            $this->base_url . '/api/v1/account',
            [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return new WP_Error( 'fqgeo_account_error', $body['error'] ?? 'account_failed', [ 'status' => $code ] );
        }

        return $body;
    }
}

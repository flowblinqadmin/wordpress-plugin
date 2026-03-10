<?php
/**
 * Flowblinq GEO — Uninstall
 *
 * Cleans up all plugin data from wp_options when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'fq_client_id' );
delete_option( 'fq_client_secret' );
delete_option( 'fq_active_audit_id' );
delete_option( 'fq_schema_blocks' );
delete_option( 'fq_llms_txt_content' );
delete_transient( 'fq_access_token' );

flush_rewrite_rules();

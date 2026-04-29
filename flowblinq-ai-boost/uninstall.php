<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Options
delete_option( 'fq_client_id' );
delete_option( 'fq_client_secret' );
delete_option( 'fq_site_slug' );
delete_option( 'fq_active_audit_id' );

// Transients
delete_transient( 'fq_access_token' );
delete_transient( 'fq_proxy_llms_txt' );
delete_transient( 'fq_proxy_llms_full_txt' );
delete_transient( 'fq_proxy_business_json' );
delete_transient( 'fq_proxy_schema_json' );

flush_rewrite_rules();

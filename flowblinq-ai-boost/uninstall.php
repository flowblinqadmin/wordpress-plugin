<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Options
delete_option( 'fqgeo_client_id' );
delete_option( 'fqgeo_client_secret' );
delete_option( 'fqgeo_site_slug' );
delete_option( 'fqgeo_active_audit_id' );

// Transients
delete_transient( 'fqgeo_access_token' );
delete_transient( 'fqgeo_proxy_llms_txt' );
delete_transient( 'fqgeo_proxy_llms_full_txt' );
delete_transient( 'fqgeo_proxy_business_json' );
delete_transient( 'fqgeo_proxy_schema_json' );

flush_rewrite_rules();

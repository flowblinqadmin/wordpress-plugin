<?php
/*
 * Plugin Name:       Flowblinq GEO
 * Plugin URI:        https://geo.flowblinq.com
 * Description:       AI visibility optimization for your WordPress site.
 * Version:           0.1.0
 * Author:            Flowblinq
 * Author URI:        https://flowblinq.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flowblinq-geo
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FQGEO_VERSION', '0.1.0' );
define( 'FQGEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FQGEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FQGEO_PLUGIN_DIR . 'includes/class-api-client.php';
require_once FQGEO_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once FQGEO_PLUGIN_DIR . 'includes/class-injector.php';

register_activation_hook( __FILE__, function () {
    add_rewrite_rule( '^flowblinq-llms\.txt$', 'index.php?fq_llms_txt=1', 'top' );
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
    wp_clear_scheduled_hook( 'fqgeo_poll_audit' );
} );

add_action( 'init', function () {
    load_plugin_textdomain( 'flowblinq-geo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Boot admin UI and front-end injector
if ( is_admin() ) {
    new Flowblinq_Admin_Page();
}
new Flowblinq_Injector();

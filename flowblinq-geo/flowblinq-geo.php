<?php
/*
 * Plugin Name:       Flowblinq GEO
 * Plugin URI:        https://geo.flowblinq.com
 * Description:       AI visibility optimization for your WordPress site.
 * Version:           1.0.0
 * Author:            Flowblinq
 * Author URI:        https://flowblinq.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flowblinq-geo
 * Domain Path:       /languages
 */

/*
 * Flowblinq GEO is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Flowblinq GEO is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with Flowblinq GEO. If not, see https://www.gnu.org/licenses/.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FQGEO_VERSION', '1.0.0' );
define( 'FQGEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FQGEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FQGEO_PLUGIN_DIR . 'includes/constants.php';
require_once FQGEO_PLUGIN_DIR . 'includes/class-api-client.php';
require_once FQGEO_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once FQGEO_PLUGIN_DIR . 'includes/class-proxy.php';

register_activation_hook( __FILE__, function () {
    $proxy = new Flowblinq_Proxy();
    $proxy->register_rewrite_rules();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
    wp_clear_scheduled_hook( 'fqgeo_poll_audit' );
} );

add_action( 'init', function () {
    load_plugin_textdomain( 'flowblinq-geo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Boot admin UI
if ( is_admin() ) {
    new Flowblinq_Admin_Page();
}

// Boot proxy (runs on all requests — front-end + admin)
new Flowblinq_Proxy();

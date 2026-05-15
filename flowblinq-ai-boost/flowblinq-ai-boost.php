<?php
/*
 * Plugin Name:       Flowblinq AI Boost
 * Plugin URI:        https://geo.flowblinq.com
 * Description:       AI visibility optimization for your WordPress site.
 * Version:           1.3.2
 * Author:            Flowblinq
 * Author URI:        https://flowblinq.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flowblinq-ai-boost
 * Domain Path:       /languages
 */

/*
 * Flowblinq AI Boost is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Flowblinq AI Boost is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with Flowblinq AI Boost. If not, see https://www.gnu.org/licenses/.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FQGEO_VERSION', '1.3.2' );
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

// Translations are auto-loaded by WordPress core since 4.6 for plugins
// hosted on wordpress.org under their own slug; no manual load_plugin_textdomain call needed.

// Defer all hook registration to plugins_loaded so the load order is
// inspectable by plugin-conflict tooling and other plugins can attach
// to ours via standard WP hook timing.
add_action( 'plugins_loaded', function () {
    if ( is_admin() ) {
        new Flowblinq_Admin_Page();
    }
    new Flowblinq_Proxy();
} );

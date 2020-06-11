<?php
/**
 * Plugin Name:         Single Post Autoload
 * Plugin URI:          http://46.101.181.97
 * Description:         Adds the ability to auto-load next post on single post page
 * Version:             1.0
 * Requires at least:   4.0
 * Requires PHP:        7.2
 * Author:              Stoil Dobreff
 * Text Domain:         post-autoload
 *
 * Copyright: © 2020 Stoil Dobreff, (sdobreff@gmail.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   PostAutoload
 * @author    Stoil Dobreff
 * @copyright Copyright © 2020, Stoil Dobreff
 * @license   GNU General Public License v3.0 http://www.gnu.org/licenses/gpl-3.0.html
 */

/** Prevent default call */
if ( !function_exists( 'add_action' ) ) {
    exit;
}

define( 'REQUIRED_PHP_VERSION', '7.0' );
define( 'REQUIRED_WP_VERSION', '4.0' );
define( 'REQUIRED_MYSQL_VERSION', '5.7' );
define( 'POSTAUTOLOAD__PLUGIN_VERSION', '1.0' );
define( 'POSTAUTOLOAD__PLUGIN_NAME', 'Posts Autoload' );
define( 'POSTAUTOLOAD__PLUGIN_SLUG', 'post-autoload' );
define( 'POSTAUTOLOAD__PLUGIN_DIR_CLASSES', trailingslashit( plugin_dir_path( __FILE__ ) . 'classes') );
define( 'POSTAUTOLOAD__ASSETS_DIR', trailingslashit( plugin_dir_path( __FILE__ ) . 'assets') );
define( 'POSTAUTOLOAD__ASSETS_URL_DIR', trailingslashit( plugins_url( '/', __FILE__ ) . 'assets') );

require_once( POSTAUTOLOAD__PLUGIN_DIR_CLASSES . 'PostAutoload.php' );

add_action( 'init', [ 'PostAutoload\PostAutoload', 'init' ] );
add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), [ 'PostAutoload\\PostAutoload', 'add_action_links' ] );


register_uninstall_hook( __FILE__, 'post_autoload_uninstall_plugin' );

/**
 * Removes the plugin data
 */
function post_autoload_uninstall_plugin() {
   global $wpdb;

   // Delete Oprions
   delete_site_option( 'post_autoload_options' );

   // Delete Transient
   delete_transient( 'autoload_template_part' );

   // Removes the table
   $sql = 'DROP TABLE IF EXISTS `'.$wpdb->prefix.'track_users`';
   $wpdb->query($sql);

   // Clear any cached data.
   wp_cache_flush();
}
<?php
/**
 * Plugin Name: Modern Catholic – Parish Alerts
 * Plugin URI: https://github.com/twitchd8/modern-catholic-plugin-parish-alerts
 * Description: Publishes scheduled parish alerts for Modern Catholic parish websites with acknowledgments, priority modals, and opt-in browser notifications.
 * Version: 1.0.1
 * Requires PHP: 8.2
 * Author: Andrew T. Schmitt
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: parish-alerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARISH_ALERTS_VERSION', '1.0.1' );
define( 'PARISH_ALERTS_DB_VERSION', '1.1.0' );
define( 'PARISH_ALERTS_FILE', __FILE__ );
define( 'PARISH_ALERTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PARISH_ALERTS_URL', plugin_dir_url( __FILE__ ) );
define( 'PARISH_ALERTS_PUSH_HOOK', 'parish_alerts_send_push_notification' );

if ( file_exists( PARISH_ALERTS_DIR . 'vendor/autoload.php' ) ) {
	require_once PARISH_ALERTS_DIR . 'vendor/autoload.php';
}

require_once PARISH_ALERTS_DIR . 'includes/install.php';
require_once PARISH_ALERTS_DIR . 'includes/post-type.php';
require_once PARISH_ALERTS_DIR . 'includes/push.php';
require_once PARISH_ALERTS_DIR . 'includes/rest-api.php';
require_once PARISH_ALERTS_DIR . 'includes/admin.php';
require_once PARISH_ALERTS_DIR . 'includes/admin-notifications.php';
require_once PARISH_ALERTS_DIR . 'includes/frontend.php';

/**
 * Registers the post type before refreshing rewrite rules.
 */
function parish_alerts_activate() {
	parish_alerts_install_or_upgrade();
	parish_alerts_register_post_type();
	flush_rewrite_rules();
}

/**
 * Refreshes rewrite rules after the post type is removed.
 */
function parish_alerts_deactivate() {
	wp_unschedule_hook( PARISH_ALERTS_PUSH_HOOK );
	flush_rewrite_rules();
}

/**
 * Refreshes rewrite rules once after an in-place post type migration.
 */
function parish_alerts_maybe_flush_rewrite_rules() {
	if ( get_option( 'parish_alerts_flush_rewrite' ) ) {
		flush_rewrite_rules( false );
		delete_option( 'parish_alerts_flush_rewrite' );
	}
}

register_activation_hook( __FILE__, 'parish_alerts_activate' );
register_deactivation_hook( __FILE__, 'parish_alerts_deactivate' );
add_action( 'plugins_loaded', 'parish_alerts_maybe_upgrade', 5 );
add_action( 'init', 'parish_alerts_maybe_flush_rewrite_rules', 99 );

<?php
/**
 * Removes Web Push infrastructure when the plugin is deleted.
 *
 * Alert posts and their editorial schedule data are deliberately retained.
 *
 * @package ParishAlerts
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

wp_unschedule_hook( 'parish_alerts_send_push_notification' );

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}parish_alert_subscriptions" );

delete_option( 'parish_alerts_db_version' );
delete_option( 'parish_alerts_vapid_public_key' );
delete_option( 'parish_alerts_vapid_private_key' );
delete_option( 'parish_alerts_vapid_error' );
delete_option( 'parish_alerts_last_delivery' );
delete_transient( 'parish_alerts_vapid_retry' );

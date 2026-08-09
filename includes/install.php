<?php
/**
 * Database and VAPID installation lifecycle.
 *
 * @package ParishAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the subscription table name.
 *
 * @return string
 */
function parish_alerts_subscriptions_table() {
	global $wpdb;

	return $wpdb->prefix . 'parish_alert_subscriptions';
}

/**
 * Creates or updates the Web Push database and long-lived VAPID keys.
 */
function parish_alerts_install_or_upgrade() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = parish_alerts_subscriptions_table();
	$charset_collate = $wpdb->get_charset_collate();
	$sql             = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		endpoint_hash char(64) NOT NULL,
		endpoint text NOT NULL,
		public_key varchar(255) NOT NULL,
		auth_token varchar(255) NOT NULL,
		content_encoding varchar(32) NOT NULL DEFAULT 'aes128gcm',
		status varchar(20) NOT NULL DEFAULT 'active',
		failure_count smallint(5) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		last_success_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY endpoint_hash (endpoint_hash),
		KEY status (status)
	) {$charset_collate};";

	dbDelta( $sql );

	if ( ! get_option( 'parish_alerts_vapid_public_key' ) || ! get_option( 'parish_alerts_vapid_private_key' ) ) {
		parish_alerts_generate_vapid_keys();
	}

	update_option( 'parish_alerts_db_version', PARISH_ALERTS_DB_VERSION, false );
}

/**
 * Generates the VAPID identity once and retains it across plugin updates.
 *
 * @return bool
 */
function parish_alerts_generate_vapid_keys() {
	if ( ! class_exists( '\\Minishlink\\WebPush\\VAPID' ) ) {
		update_option( 'parish_alerts_vapid_error', __( 'The bundled Web Push library is unavailable.', 'parish-alerts' ), false );
		return false;
	}

	try {
		$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
		update_option( 'parish_alerts_vapid_public_key', $keys['publicKey'], false );
		update_option( 'parish_alerts_vapid_private_key', $keys['privateKey'], false );
		delete_option( 'parish_alerts_vapid_error' );
		return true;
	} catch ( Throwable $error ) {
		update_option( 'parish_alerts_vapid_error', sanitize_text_field( $error->getMessage() ), false );
		return false;
	}
}

/**
 * Applies database changes after an in-place plugin update.
 */
function parish_alerts_maybe_upgrade() {
	if ( PARISH_ALERTS_DB_VERSION !== get_option( 'parish_alerts_db_version' ) ) {
		parish_alerts_install_or_upgrade();
		return;
	}

	if ( ( ! get_option( 'parish_alerts_vapid_public_key' ) || ! get_option( 'parish_alerts_vapid_private_key' ) )
		&& ! get_transient( 'parish_alerts_vapid_retry' ) ) {
		set_transient( 'parish_alerts_vapid_retry', 1, HOUR_IN_SECONDS );
		parish_alerts_generate_vapid_keys();
	}
}

/**
 * Returns whether the server-side Web Push identity is ready.
 *
 * @return bool
 */
function parish_alerts_push_is_configured() {
	return class_exists( '\\Minishlink\\WebPush\\WebPush' )
		&& (bool) get_option( 'parish_alerts_vapid_public_key' )
		&& (bool) get_option( 'parish_alerts_vapid_private_key' );
}

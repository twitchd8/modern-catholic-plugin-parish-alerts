<?php
/**
 * Web Push diagnostics for parish administrators.
 *
 * @package ParishAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the Notifications diagnostics page below Alerts.
 */
function parish_alerts_add_notifications_page() {
	add_submenu_page(
		'edit.php?post_type=parish_alert',
		__( 'Alert Notifications', 'parish-alerts' ),
		__( 'Notifications', 'parish-alerts' ),
		'manage_options',
		'parish-alert-notifications',
		'parish_alerts_render_notifications_page'
	);
}

/**
 * Finds the next event for the push hook regardless of its arguments.
 *
 * @return int
 */
function parish_alerts_get_next_push_timestamp() {
	$events = _get_cron_array();

	foreach ( $events as $timestamp => $hooks ) {
		if ( isset( $hooks[ PARISH_ALERTS_PUSH_HOOK ] ) ) {
			return absint( $timestamp );
		}
	}

	return 0;
}

/**
 * Renders privacy-safe Web Push diagnostics.
 */
function parish_alerts_render_notifications_page() {
	global $wpdb;

	$table       = parish_alerts_subscriptions_table();
	$active      = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ) );
	$disabled    = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'disabled'" ) );
	$next_event  = parish_alerts_get_next_push_timestamp();
	$last        = get_option( 'parish_alerts_last_delivery', array() );
	$vapid_error = get_option( 'parish_alerts_vapid_error' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Alert Notifications', 'parish-alerts' ); ?></h1>
		<p><?php esc_html_e( 'Web Push is offered only after a visitor explicitly enables browser notifications. Subscription endpoints and encryption keys are never displayed here.', 'parish-alerts' ); ?></p>

		<table class="widefat striped" style="max-width:760px">
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'HTTPS', 'parish-alerts' ); ?></th><td><?php echo 'https' === wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) ? esc_html__( 'Ready', 'parish-alerts' ) : esc_html__( 'Not active on this Local site; required in production', 'parish-alerts' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Web Push library', 'parish-alerts' ); ?></th><td><?php echo class_exists( '\\Minishlink\\WebPush\\WebPush' ) ? esc_html__( 'Ready', 'parish-alerts' ) : esc_html__( 'Unavailable', 'parish-alerts' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'VAPID identity', 'parish-alerts' ); ?></th><td><?php echo parish_alerts_push_is_configured() ? esc_html__( 'Configured', 'parish-alerts' ) : esc_html__( 'Not configured', 'parish-alerts' ); ?><?php echo $vapid_error ? ' — ' . esc_html( $vapid_error ) : ''; ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Active browser subscriptions', 'parish-alerts' ); ?></th><td><?php echo esc_html( number_format_i18n( $active ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Disabled subscriptions', 'parish-alerts' ); ?></th><td><?php echo esc_html( number_format_i18n( $disabled ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Next scheduled delivery', 'parish-alerts' ); ?></th><td><?php echo $next_event ? esc_html( wp_date( 'M j, Y g:i a T', $next_event, wp_timezone() ) ) : esc_html__( 'None', 'parish-alerts' ); ?></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Last Delivery', 'parish-alerts' ); ?></h2>
		<?php if ( $last ) : ?>
			<pre style="max-width:760px;padding:1rem;background:#fff;border:1px solid #ccd0d4;white-space:pre-wrap"><?php echo esc_html( wp_json_encode( $last, JSON_PRETTY_PRINT ) ); ?></pre>
		<?php else : ?>
			<p><?php esc_html_e( 'No delivery has been attempted yet.', 'parish-alerts' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_menu', 'parish_alerts_add_notifications_page' );

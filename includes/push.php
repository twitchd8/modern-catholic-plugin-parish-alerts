<?php
/**
 * Web Push scheduling and delivery.
 *
 * @package ParishAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a plugin-specific service-worker scope that will not replace a PWA worker.
 *
 * @return string
 */
function parish_alerts_service_worker_scope() {
	$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$home_path = $home_path ? trailingslashit( $home_path ) : '/';

	return $home_path . 'parish-alerts-push/';
}

/**
 * Serves the plugin-specific service worker through WordPress.
 */
function parish_alerts_maybe_serve_service_worker() {
	if ( ! isset( $_GET['parish-alerts-service-worker'] ) ) {
		return;
	}

	$status = isset( $_GET['parish-alerts-service-worker'] ) ? sanitize_text_field( wp_unslash( $_GET['parish-alerts-service-worker'] ) ) : '';
	if ( '1' !== $status ) {
		return;
	}

	$file = PARISH_ALERTS_DIR . 'assets/service-worker.js';
	if ( ! is_readable( $file ) ) {
		status_header( 404 );
		exit;
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: application/javascript; charset=UTF-8' );
	header( 'Service-Worker-Allowed: ' . parish_alerts_service_worker_scope() );
	readfile( $file );
	exit;
}

/**
 * Removes a pending initial or retry event for an alert.
 *
 * @param int $post_id Alert ID.
 */
function parish_alerts_cancel_push_for_alert( $post_id ) {
	$args = get_post_meta( $post_id, '_parish_alert_push_event_args', true );

	if ( is_array( $args ) && 3 === count( $args ) ) {
		$timestamp = wp_next_scheduled( PARISH_ALERTS_PUSH_HOOK, $args );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, PARISH_ALERTS_PUSH_HOOK, $args );
		}
	}

	delete_post_meta( $post_id, '_parish_alert_push_event_args' );
	delete_post_meta( $post_id, '_parish_alert_push_scheduled_at' );
}

/**
 * Schedules the requested alert revision at its active time.
 *
 * @param int $post_id Alert ID.
 */
function parish_alerts_reschedule_push_for_alert( $post_id ) {
	$post_id = absint( $post_id );
	parish_alerts_cancel_push_for_alert( $post_id );

	$post   = get_post( $post_id );
	$notify = (bool) get_post_meta( $post_id, '_parish_alert_push_requested', true );
	$level  = $post ? parish_alerts_sanitize_level( get_post_meta( $post_id, '_parish_alert_level', true ) ) : 'notice';

	if ( ! $post || 'publish' !== $post->post_status || ! $notify || ! in_array( $level, array( 'important', 'emergency' ), true ) ) {
		return;
	}

	$state = parish_alerts_get_state( $post );
	if ( 'expired' === $state || 'inactive' === $state ) {
		return;
	}

	$start     = absint( get_post_meta( $post_id, '_parish_alert_start', true ) );
	$timestamp = 'scheduled' === $state && $start ? $start : time() + 5;
	$token     = parish_alerts_get_revision_token( $post );
	$args      = array( $post_id, $token, 0 );
	$result    = wp_schedule_single_event( $timestamp, PARISH_ALERTS_PUSH_HOOK, $args, true );

	if ( ! is_wp_error( $result ) ) {
		update_post_meta( $post_id, '_parish_alert_push_event_args', $args );
		update_post_meta( $post_id, '_parish_alert_push_scheduled_at', $timestamp );
	} else {
		parish_alerts_record_delivery_status(
			array(
				'status'  => 'schedule_failed',
				'alert_id'=> $post_id,
				'message' => $result->get_error_message(),
			)
		);
	}
}

/**
 * Creates the compact encrypted notification payload.
 *
 * @param WP_Post $alert Alert post.
 * @return array
 */
function parish_alerts_build_push_payload( $alert ) {
	$level = parish_alerts_sanitize_level( get_post_meta( $alert->ID, '_parish_alert_level', true ) );
	$title = sprintf(
		/* translators: 1: alert priority, 2: alert title. */
		__( '%1$s: %2$s', 'parish-alerts' ),
		parish_alerts_get_level_label( $level ),
		get_the_title( $alert )
	);

	return array(
		'id'    => $alert->ID,
		'level' => $level,
		'title' => $title,
		'body'  => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $alert ) ), 28, '…' ),
		'url'   => get_permalink( $alert ),
		'icon'  => get_site_icon_url( 192 ),
		'tag'   => 'parish-alert-' . $alert->ID,
	);
}

/**
 * Records a privacy-safe delivery summary for administrators.
 *
 * @param array $status Delivery details.
 */
function parish_alerts_record_delivery_status( $status ) {
	$status['recorded_at'] = current_time( 'mysql', true );
	update_option( 'parish_alerts_last_delivery', $status, false );
}

/**
 * Returns subscription rows for an initial attempt or targeted retry.
 *
 * @param int    $post_id Alert ID.
 * @param string $token Revision token.
 * @param int    $attempt Attempt number.
 * @return array
 */
function parish_alerts_get_delivery_subscriptions( $post_id, $token, $attempt ) {
	global $wpdb;

	$table = parish_alerts_subscriptions_table();

	if ( $attempt > 0 ) {
		$key = 'parish_alerts_retry_' . md5( $post_id . '|' . $token . '|' . $attempt );
		$ids = array_filter( array_map( 'absint', (array) get_transient( $key ) ) );
		delete_transient( $key );

		if ( ! $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'active' AND id IN ({$placeholders})", $ids ) );
	}

	return $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY id ASC" );
}

/**
 * Schedules targeted retry delivery for temporary failures.
 *
 * @param int    $post_id Alert ID.
 * @param string $token Revision token.
 * @param int    $attempt Next attempt number.
 * @param int[]  $subscription_ids Subscription IDs.
 */
function parish_alerts_schedule_retry( $post_id, $token, $attempt, $subscription_ids ) {
	$key  = 'parish_alerts_retry_' . md5( $post_id . '|' . $token . '|' . $attempt );
	$args = array( $post_id, $token, $attempt );

	set_transient( $key, array_values( array_unique( array_map( 'absint', $subscription_ids ) ) ), 2 * HOUR_IN_SECONDS );
	wp_schedule_single_event( time() + ( 15 * MINUTE_IN_SECONDS ), PARISH_ALERTS_PUSH_HOOK, $args );
	update_post_meta( $post_id, '_parish_alert_push_event_args', $args );
	update_post_meta( $post_id, '_parish_alert_push_scheduled_at', time() + ( 15 * MINUTE_IN_SECONDS ) );
}

/**
 * Sends one alert revision to active browser subscriptions.
 *
 * @param int    $post_id Alert ID.
 * @param string $token Revision token.
 * @param int    $attempt Attempt number, starting at zero.
 */
function parish_alerts_send_push_notification( $post_id, $token, $attempt = 0 ) {
	global $wpdb;

	$post_id = absint( $post_id );
	$attempt = absint( $attempt );
	$alert   = get_post( $post_id );

	delete_post_meta( $post_id, '_parish_alert_push_event_args' );
	delete_post_meta( $post_id, '_parish_alert_push_scheduled_at' );

	if ( ! $alert || 'parish_alert' !== $alert->post_type || 'publish' !== $alert->post_status || $token !== parish_alerts_get_revision_token( $alert ) ) {
		return;
	}

	if ( 0 === $attempt && ! get_post_meta( $post_id, '_parish_alert_push_requested', true ) ) {
		return;
	}

	$state = parish_alerts_get_state( $alert );
	if ( 'scheduled' === $state ) {
		parish_alerts_reschedule_push_for_alert( $post_id );
		return;
	}
	if ( 'active' !== $state || ! parish_alerts_push_is_configured() ) {
		return;
	}

	$subscriptions = parish_alerts_get_delivery_subscriptions( $post_id, $token, $attempt );
	$payload       = wp_json_encode( parish_alerts_build_push_payload( $alert ) );
	$table         = parish_alerts_subscriptions_table();
	$end           = absint( get_post_meta( $post_id, '_parish_alert_end', true ) );
	$ttl           = $end ? max( 60, min( DAY_IN_SECONDS, $end - time() ) ) : DAY_IN_SECONDS;
	$level         = parish_alerts_sanitize_level( get_post_meta( $post_id, '_parish_alert_level', true ) );
	$successes     = 0;
	$expired       = 0;
	$failures      = 0;
	$retry_ids     = array();
	$rows_by_hash  = array();

	if ( ! $subscriptions ) {
		update_post_meta( $post_id, '_parish_alert_last_sent_token', $token );
		update_post_meta( $post_id, '_parish_alert_push_requested', 0 );
		parish_alerts_record_delivery_status( array( 'status' => 'completed', 'alert_id' => $post_id, 'successes' => 0, 'failures' => 0, 'expired' => 0 ) );
		return;
	}

	try {
		$auth = array(
			'VAPID' => array(
				'subject'    => is_email( get_option( 'admin_email' ) ) ? 'mailto:' . get_option( 'admin_email' ) : home_url( '/' ),
				'publicKey'  => get_option( 'parish_alerts_vapid_public_key' ),
				'privateKey' => get_option( 'parish_alerts_vapid_private_key' ),
			),
		);
		$client  = new \GuzzleHttp\Client( array( 'timeout' => 15, 'allow_redirects' => false ) );
		$push    = new \Minishlink\WebPush\WebPush(
			$auth,
			array(
				'TTL'       => $ttl,
				'urgency'   => 'emergency' === $level ? 'high' : 'normal',
				'topic'     => 'alert-' . $post_id . '-' . substr( md5( $token ), 0, 12 ),
				'batchSize' => 50,
			),
			$client
		);
		$push->setReuseVAPIDHeaders( true );

		foreach ( $subscriptions as $row ) {
			try {
				if ( ! wp_http_validate_url( $row->endpoint ) ) {
					throw new RuntimeException( __( 'Unsafe push endpoint.', 'parish-alerts' ) );
				}

				$subscription = \Minishlink\WebPush\Subscription::create(
					array(
						'endpoint'        => $row->endpoint,
						'publicKey'       => $row->public_key,
						'authToken'       => $row->auth_token,
						'contentEncoding' => $row->content_encoding,
					)
				);
				$push->queueNotification( $subscription, $payload );
				$rows_by_hash[ hash( 'sha256', $row->endpoint ) ] = $row;
			} catch ( Throwable $error ) {
				$failures++;
				$wpdb->update( $table, array( 'status' => 'disabled', 'failure_count' => 5, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $row->id ), array( '%s', '%d', '%s' ), array( '%d' ) );
			}
		}

		foreach ( $push->flush( 50 ) as $report ) {
			$hash = hash( 'sha256', $report->getEndpoint() );
			$row  = isset( $rows_by_hash[ $hash ] ) ? $rows_by_hash[ $hash ] : null;
			if ( ! $row ) {
				continue;
			}

			if ( $report->isSuccess() ) {
				$successes++;
				$wpdb->update( $table, array( 'failure_count' => 0, 'last_success_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $row->id ), array( '%d', '%s', '%s' ), array( '%d' ) );
			} elseif ( $report->isSubscriptionExpired() ) {
				$expired++;
				$wpdb->delete( $table, array( 'id' => $row->id ), array( '%d' ) );
			} else {
				$failures++;
				$next_failure = absint( $row->failure_count ) + 1;
				$status       = $next_failure >= 5 ? 'disabled' : 'active';
				$wpdb->update( $table, array( 'failure_count' => $next_failure, 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $row->id ), array( '%d', '%s', '%s' ), array( '%d' ) );
				if ( 'active' === $status ) {
					$retry_ids[] = absint( $row->id );
				}
			}
		}
	} catch ( Throwable $error ) {
		parish_alerts_record_delivery_status( array( 'status' => 'failed', 'alert_id' => $post_id, 'message' => sanitize_text_field( $error->getMessage() ) ) );
		return;
	}

	if ( $retry_ids && $attempt < 3 ) {
		parish_alerts_schedule_retry( $post_id, $token, $attempt + 1, $retry_ids );
	}

	update_post_meta( $post_id, '_parish_alert_last_sent_token', $token );
	update_post_meta( $post_id, '_parish_alert_push_requested', 0 );
	parish_alerts_record_delivery_status(
		array(
			'status'    => $retry_ids && $attempt < 3 ? 'retry_scheduled' : 'completed',
			'alert_id'  => $post_id,
			'attempt'   => $attempt,
			'successes' => $successes,
			'failures'  => $failures,
			'expired'   => $expired,
		)
	);
}

add_action( 'template_redirect', 'parish_alerts_maybe_serve_service_worker', 0 );
add_action( PARISH_ALERTS_PUSH_HOOK, 'parish_alerts_send_push_notification', 10, 3 );

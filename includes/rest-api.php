<?php
/**
 * Anonymous browser subscription REST API.
 *
 * @package ParishAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers subscription endpoints.
 */
function parish_alerts_register_rest_routes() {
	register_rest_route(
		'parish-alerts/v1',
		'/subscriptions',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'parish_alerts_rest_subscribe',
				'permission_callback' => 'parish_alerts_rest_validate_origin',
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'parish_alerts_rest_unsubscribe',
				'permission_callback' => 'parish_alerts_rest_validate_origin',
			),
		)
	);
}

/**
 * Returns the normalized origin portion of a URL.
 *
 * @param string $url URL to inspect.
 * @return string
 */
function parish_alerts_normalize_origin( $url ) {
	$parts = wp_parse_url( $url );

	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return '';
	}

	$origin = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
	if ( ! empty( $parts['port'] ) ) {
		$origin .= ':' . absint( $parts['port'] );
	}

	return $origin;
}

/**
 * Restricts anonymous writes to same-origin browser requests and throttles abuse.
 *
 * @param WP_REST_Request $request Request object.
 * @return true|WP_Error
 */
function parish_alerts_rest_validate_origin( $request ) {
	$expected = parish_alerts_normalize_origin( home_url( '/' ) );
	$origin   = parish_alerts_normalize_origin( $request->get_header( 'origin' ) );
	$referer  = parish_alerts_normalize_origin( $request->get_header( 'referer' ) );

	if ( ! $expected || ( $expected !== $origin && $expected !== $referer ) ) {
		return new WP_Error( 'parish_alerts_invalid_origin', __( 'The subscription request did not come from this site.', 'parish-alerts' ), array( 'status' => 403 ) );
	}

	$remote_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$rate_key       = 'parish_alerts_rest_' . substr( hash_hmac( 'sha256', $remote_address, wp_salt( 'nonce' ) ), 0, 32 );
	$attempts       = absint( get_transient( $rate_key ) );

	if ( $attempts >= 30 ) {
		return new WP_Error( 'parish_alerts_rate_limited', __( 'Too many notification requests. Please try again shortly.', 'parish-alerts' ), array( 'status' => 429 ) );
	}

	set_transient( $rate_key, $attempts + 1, 5 * MINUTE_IN_SECONDS );

	return true;
}

/**
 * Validates and normalizes browser subscription JSON.
 *
 * @param WP_REST_Request $request Request object.
 * @return array|WP_Error
 */
function parish_alerts_rest_subscription_data( $request ) {
	$data     = $request->get_json_params();
	$endpoint = isset( $data['endpoint'] ) ? esc_url_raw( $data['endpoint'] ) : '';
	$p256dh   = isset( $data['keys']['p256dh'] ) ? sanitize_text_field( $data['keys']['p256dh'] ) : '';
	$auth     = isset( $data['keys']['auth'] ) ? sanitize_text_field( $data['keys']['auth'] ) : '';
	$encoding = isset( $data['contentEncoding'] ) ? sanitize_key( $data['contentEncoding'] ) : 'aes128gcm';

	if ( ! $endpoint || 'https' !== wp_parse_url( $endpoint, PHP_URL_SCHEME ) || strlen( $endpoint ) > 2048 || ! wp_http_validate_url( $endpoint ) ) {
		return new WP_Error( 'parish_alerts_invalid_endpoint', __( 'The browser supplied an invalid push endpoint.', 'parish-alerts' ), array( 'status' => 400 ) );
	}

	if ( ! preg_match( '/^[A-Za-z0-9_-]{20,255}$/', $p256dh ) || ! preg_match( '/^[A-Za-z0-9_-]{8,255}$/', $auth ) ) {
		return new WP_Error( 'parish_alerts_invalid_keys', __( 'The browser supplied invalid encryption keys.', 'parish-alerts' ), array( 'status' => 400 ) );
	}

	if ( ! in_array( $encoding, array( 'aes128gcm', 'aesgcm' ), true ) ) {
		$encoding = 'aes128gcm';
	}

	return array(
		'endpoint'         => $endpoint,
		'endpoint_hash'    => hash( 'sha256', $endpoint ),
		'public_key'       => $p256dh,
		'auth_token'       => $auth,
		'content_encoding' => $encoding,
	);
}

/**
 * Creates or refreshes an anonymous browser subscription.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function parish_alerts_rest_subscribe( $request ) {
	global $wpdb;

	$data = parish_alerts_rest_subscription_data( $request );
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	$table    = parish_alerts_subscriptions_table();
	$now      = current_time( 'mysql', true );
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE endpoint_hash = %s", $data['endpoint_hash'] ) );

	if ( $existing ) {
		$result = $wpdb->update(
			$table,
			array(
				'endpoint'         => $data['endpoint'],
				'public_key'       => $data['public_key'],
				'auth_token'       => $data['auth_token'],
				'content_encoding' => $data['content_encoding'],
				'status'           => 'active',
				'failure_count'    => 0,
				'updated_at'       => $now,
			),
			array( 'id' => absint( $existing ) ),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	} else {
		$result = $wpdb->insert(
			$table,
			array_merge(
				$data,
				array(
					'status'        => 'active',
					'failure_count' => 0,
					'created_at'    => $now,
					'updated_at'    => $now,
				)
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	if ( false === $result ) {
		return new WP_Error( 'parish_alerts_subscription_failed', __( 'The notification subscription could not be saved.', 'parish-alerts' ), array( 'status' => 500 ) );
	}

	return new WP_REST_Response( array( 'subscribed' => true ), $existing ? 200 : 201 );
}

/**
 * Removes an anonymous browser subscription.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function parish_alerts_rest_unsubscribe( $request ) {
	global $wpdb;

	$data     = $request->get_json_params();
	$endpoint = isset( $data['endpoint'] ) ? esc_url_raw( $data['endpoint'] ) : '';

	if ( ! $endpoint || 'https' !== wp_parse_url( $endpoint, PHP_URL_SCHEME ) || strlen( $endpoint ) > 2048 || ! wp_http_validate_url( $endpoint ) ) {
		return new WP_Error( 'parish_alerts_invalid_endpoint', __( 'The browser supplied an invalid push endpoint.', 'parish-alerts' ), array( 'status' => 400 ) );
	}

	$wpdb->delete(
		parish_alerts_subscriptions_table(),
		array( 'endpoint_hash' => hash( 'sha256', $endpoint ) ),
		array( '%s' )
	);

	return new WP_REST_Response( array( 'subscribed' => false ), 200 );
}

add_action( 'rest_api_init', 'parish_alerts_register_rest_routes' );

<?php
/**
 * Alert post type, metadata, and active-state queries.
 *
 * @package ParishAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Alert post type and its structured fields.
 */
function parish_alerts_register_post_type() {
	$labels = array(
		'name'                  => __( 'Alerts', 'parish-alerts' ),
		'singular_name'         => __( 'Alert', 'parish-alerts' ),
		'add_new'               => __( 'Add New', 'parish-alerts' ),
		'add_new_item'          => __( 'Add New Alert', 'parish-alerts' ),
		'edit_item'             => __( 'Edit Alert', 'parish-alerts' ),
		'new_item'              => __( 'New Alert', 'parish-alerts' ),
		'view_item'             => __( 'View Alert', 'parish-alerts' ),
		'view_items'            => __( 'View Alerts', 'parish-alerts' ),
		'search_items'          => __( 'Search Alerts', 'parish-alerts' ),
		'not_found'             => __( 'No alerts found.', 'parish-alerts' ),
		'not_found_in_trash'    => __( 'No alerts found in Trash.', 'parish-alerts' ),
		'all_items'             => __( 'All Alerts', 'parish-alerts' ),
		'archives'              => __( 'Active Alerts', 'parish-alerts' ),
		'attributes'            => __( 'Alert Attributes', 'parish-alerts' ),
		'item_published'        => __( 'Alert published.', 'parish-alerts' ),
		'item_reverted_to_draft'=> __( 'Alert reverted to draft.', 'parish-alerts' ),
		'item_scheduled'        => __( 'Alert publication scheduled.', 'parish-alerts' ),
		'item_updated'          => __( 'Alert updated.', 'parish-alerts' ),
		'menu_name'             => __( 'Alerts', 'parish-alerts' ),
		'name_admin_bar'        => __( 'Alert', 'parish-alerts' ),
	);

	register_post_type(
		'mc_alert',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-warning',
			'menu_position'       => 20,
			'has_archive'         => 'alerts',
			'rewrite'             => array(
				'slug'       => 'alerts',
				'with_front' => false,
			),
			'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
			'show_in_nav_menus'   => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'map_meta_cap'        => true,
		)
	);

	$auth_callback = static function () {
		return current_user_can( 'edit_posts' );
	};

	register_post_meta(
		'mc_alert',
		'_parish_alert_start',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
		)
	);

	register_post_meta(
		'mc_alert',
		'_parish_alert_end',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
		)
	);

	register_post_meta(
		'mc_alert',
		'_parish_alert_level',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => 'notice',
			'sanitize_callback' => 'parish_alerts_sanitize_level',
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
		)
	);
}

/**
 * Restricts alert levels to the supported presentation choices.
 *
 * @param mixed $value Candidate level.
 * @return string
 */
function parish_alerts_sanitize_level( $value ) {
	$value = sanitize_key( (string) $value );

	return in_array( $value, array( 'notice', 'important', 'emergency' ), true ) ? $value : 'notice';
}

/**
 * Returns the site-local timestamp used for alert schedules.
 *
 * @return int
 */
function parish_alerts_now() {
	return current_datetime()->getTimestamp();
}

/**
 * Returns the metadata conditions for active alerts.
 *
 * A published alert is active when its optional start has passed and its
 * optional end has not passed.
 *
 * @param int|null $timestamp Optional timestamp for deterministic calls.
 * @return array
 */
function parish_alerts_active_meta_query( $timestamp = null ) {
	$timestamp = null === $timestamp ? parish_alerts_now() : absint( $timestamp );

	return array(
		'relation' => 'AND',
		array(
			'relation' => 'OR',
			array(
				'key'     => '_parish_alert_start',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_parish_alert_start',
				'value'   => 0,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => '_parish_alert_start',
				'value'   => $timestamp,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			),
		),
		array(
			'relation' => 'OR',
			array(
				'key'     => '_parish_alert_end',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_parish_alert_end',
				'value'   => 0,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => '_parish_alert_end',
				'value'   => $timestamp,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			),
		),
	);
}

/**
 * Determines the operational state of an alert.
 *
 * @param int|WP_Post $post Alert post or ID.
 * @param int|null    $timestamp Optional timestamp for deterministic calls.
 * @return string active, scheduled, expired, or inactive.
 */
function parish_alerts_get_state( $post, $timestamp = null ) {
	$post = get_post( $post );

	if ( ! $post || 'mc_alert' !== $post->post_type || 'publish' !== $post->post_status ) {
		return 'inactive';
	}

	$timestamp = null === $timestamp ? parish_alerts_now() : absint( $timestamp );
	$start     = absint( get_post_meta( $post->ID, '_parish_alert_start', true ) );
	$end       = absint( get_post_meta( $post->ID, '_parish_alert_end', true ) );

	if ( $start && $start > $timestamp ) {
		return 'scheduled';
	}

	if ( $end && $end < $timestamp ) {
		return 'expired';
	}

	return 'active';
}

/**
 * Keeps alert discussions closed.
 *
 * @param array $data Sanitized post data.
 * @return array
 */
function parish_alerts_close_saved_discussion( $data ) {
	if ( isset( $data['post_type'] ) && 'mc_alert' === $data['post_type'] ) {
		$data['comment_status'] = 'closed';
		$data['ping_status']    = 'closed';
	}

	return $data;
}

add_action( 'init', 'parish_alerts_register_post_type' );
add_filter( 'wp_insert_post_data', 'parish_alerts_close_saved_discussion' );

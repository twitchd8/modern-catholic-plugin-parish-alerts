<?php
/**
 * Alert editor fields and list-table status.
 *
 * @package ParishAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the alert schedule panel.
 */
function parish_alerts_add_meta_boxes() {
	add_meta_box(
		'parish-alert-schedule',
		__( 'Alert Schedule and Priority', 'parish-alerts' ),
		'parish_alerts_render_schedule_meta_box',
		'parish_alert',
		'side',
		'high'
	);
}

/**
 * Formats a stored timestamp for a datetime-local input.
 *
 * @param int $timestamp Stored timestamp.
 * @return string
 */
function parish_alerts_datetime_input_value( $timestamp ) {
	return $timestamp ? wp_date( 'Y-m-d\TH:i', $timestamp, wp_timezone() ) : '';
}

/**
 * Renders the schedule and priority fields.
 *
 * @param WP_Post $post Current alert.
 */
function parish_alerts_render_schedule_meta_box( $post ) {
	$start = absint( get_post_meta( $post->ID, '_parish_alert_start', true ) );
	$end   = absint( get_post_meta( $post->ID, '_parish_alert_end', true ) );
	$level = parish_alerts_sanitize_level( get_post_meta( $post->ID, '_parish_alert_level', true ) );

	wp_nonce_field( 'parish_alerts_save_schedule', 'parish_alerts_schedule_nonce' );
	?>
	<p>
		<label for="parish-alert-level"><strong><?php esc_html_e( 'Priority', 'parish-alerts' ); ?></strong></label><br>
		<select id="parish-alert-level" name="parish_alert_level" class="widefat">
			<option value="notice" <?php selected( $level, 'notice' ); ?>><?php esc_html_e( 'Notice', 'parish-alerts' ); ?></option>
			<option value="important" <?php selected( $level, 'important' ); ?>><?php esc_html_e( 'Important', 'parish-alerts' ); ?></option>
			<option value="emergency" <?php selected( $level, 'emergency' ); ?>><?php esc_html_e( 'Emergency', 'parish-alerts' ); ?></option>
		</select>
	</p>
	<p>
		<label for="parish-alert-start"><strong><?php esc_html_e( 'Starts', 'parish-alerts' ); ?></strong></label><br>
		<input id="parish-alert-start" name="parish_alert_start" type="datetime-local" class="widefat" value="<?php echo esc_attr( parish_alerts_datetime_input_value( $start ) ); ?>">
		<span class="description"><?php esc_html_e( 'Leave blank to start immediately after publishing.', 'parish-alerts' ); ?></span>
	</p>
	<p>
		<label for="parish-alert-end"><strong><?php esc_html_e( 'Ends', 'parish-alerts' ); ?></strong></label><br>
		<input id="parish-alert-end" name="parish_alert_end" type="datetime-local" class="widefat" value="<?php echo esc_attr( parish_alerts_datetime_input_value( $end ) ); ?>">
		<span class="description"><?php esc_html_e( 'Leave blank to keep active until drafted or trashed.', 'parish-alerts' ); ?></span>
	</p>
	<p class="description">
		<?php esc_html_e( 'An alert is active only while it is published and within this optional time window.', 'parish-alerts' ); ?>
	</p>
	<?php
}

/**
 * Parses a datetime-local value in the WordPress site timezone.
 *
 * @param mixed $value Submitted value.
 * @return int
 */
function parish_alerts_parse_local_datetime( $value ) {
	$value = sanitize_text_field( wp_unslash( (string) $value ) );

	if ( '' === $value ) {
		return 0;
	}

	$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );

	return $date && $date->format( 'Y-m-d\TH:i' ) === $value ? $date->getTimestamp() : 0;
}

/**
 * Saves the alert schedule panel.
 *
 * @param int $post_id Alert ID.
 */
function parish_alerts_save_schedule( $post_id ) {
	if ( ! isset( $_POST['parish_alerts_schedule_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['parish_alerts_schedule_nonce'] ) ), 'parish_alerts_save_schedule' ) ||
		( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
		! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$start = isset( $_POST['parish_alert_start'] ) ? parish_alerts_parse_local_datetime( $_POST['parish_alert_start'] ) : 0;
	$end   = isset( $_POST['parish_alert_end'] ) ? parish_alerts_parse_local_datetime( $_POST['parish_alert_end'] ) : 0;
	$level = isset( $_POST['parish_alert_level'] ) ? parish_alerts_sanitize_level( wp_unslash( $_POST['parish_alert_level'] ) ) : 'notice';

	update_post_meta( $post_id, '_parish_alert_start', $start );
	update_post_meta( $post_id, '_parish_alert_end', $end );
	update_post_meta( $post_id, '_parish_alert_level', $level );
}

/**
 * Adds operational fields to the Alerts list.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function parish_alerts_admin_columns( $columns ) {
	$columns['parish_alert_state'] = __( 'Alert Status', 'parish-alerts' );
	$columns['parish_alert_end']   = __( 'Ends', 'parish-alerts' );

	return $columns;
}

/**
 * Renders operational list-table fields.
 *
 * @param string $column Column key.
 * @param int    $post_id Alert ID.
 */
function parish_alerts_render_admin_column( $column, $post_id ) {
	if ( 'parish_alert_state' === $column ) {
		$labels = array(
			'active'    => __( 'Active', 'parish-alerts' ),
			'scheduled' => __( 'Scheduled', 'parish-alerts' ),
			'expired'   => __( 'Expired', 'parish-alerts' ),
			'inactive'  => __( 'Inactive', 'parish-alerts' ),
		);
		$state = parish_alerts_get_state( $post_id );
		echo esc_html( $labels[ $state ] );
	}

	if ( 'parish_alert_end' === $column ) {
		$end = absint( get_post_meta( $post_id, '_parish_alert_end', true ) );
		echo $end ? esc_html( wp_date( 'M j, Y g:i a', $end, wp_timezone() ) ) : esc_html__( 'No expiration', 'parish-alerts' );
	}
}

add_action( 'add_meta_boxes_parish_alert', 'parish_alerts_add_meta_boxes' );
add_action( 'save_post_parish_alert', 'parish_alerts_save_schedule' );
add_filter( 'manage_parish_alert_posts_columns', 'parish_alerts_admin_columns' );
add_action( 'manage_parish_alert_posts_custom_column', 'parish_alerts_render_admin_column', 10, 2 );

<?php
/**
 * Active-alert archive, sitewide popover, and priority modal.
 *
 * @package ParishAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Limits the public Alerts archive to alerts that are active now.
 *
 * @param WP_Query $query Main query.
 */
function parish_alerts_filter_archive( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'parish_alert' ) ) {
		return;
	}

	$query->set( 'meta_query', parish_alerts_active_meta_query() );
	$query->set( 'orderby', 'date' );
	$query->set( 'order', 'DESC' );
}

/**
 * Gives the public archive a visitor-facing title.
 *
 * @param string $title Generated archive title.
 * @return string
 */
function parish_alerts_archive_title( $title ) {
	return is_post_type_archive( 'parish_alert' ) ? __( 'Active Alerts', 'parish-alerts' ) : $title;
}

/**
 * Returns the visitor-facing label for an alert level.
 *
 * @param string $level Alert level.
 * @return string
 */
function parish_alerts_get_level_label( $level ) {
	$labels = array(
		'notice'    => __( 'Notice', 'parish-alerts' ),
		'important' => __( 'Important', 'parish-alerts' ),
		'emergency' => __( 'Emergency', 'parish-alerts' ),
	);

	$level = parish_alerts_sanitize_level( $level );

	return $labels[ $level ];
}

/**
 * Builds the browser acknowledgment token for one exact alert revision.
 *
 * @param int|WP_Post $post Alert post or ID.
 * @return string
 */
function parish_alerts_get_revision_token( $post ) {
	$post = get_post( $post );

	if ( ! $post || 'parish_alert' !== $post->post_type ) {
		return '';
	}

	return $post->ID . ':' . get_post_modified_time( 'YmdHis', true, $post );
}

/**
 * Returns all active alerts, ordered by urgency and then recency.
 *
 * @return WP_Post[]
 */
function parish_alerts_get_active_posts() {
	static $alerts = null;

	if ( null !== $alerts ) {
		return $alerts;
	}

	$alerts = get_posts(
		array(
			'post_type'      => 'parish_alert',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => parish_alerts_active_meta_query(),
		)
	);

	$weights = array(
		'emergency' => 3,
		'important' => 2,
		'notice'    => 1,
	);

	usort(
		$alerts,
		static function ( $first, $second ) use ( $weights ) {
			$first_level  = parish_alerts_sanitize_level( get_post_meta( $first->ID, '_parish_alert_level', true ) );
			$second_level = parish_alerts_sanitize_level( get_post_meta( $second->ID, '_parish_alert_level', true ) );
			$priority     = $weights[ $second_level ] <=> $weights[ $first_level ];

			if ( 0 !== $priority ) {
				return $priority;
			}

			return strcmp( $second->post_date_gmt, $first->post_date_gmt );
		}
	);

	return $alerts;
}

/**
 * Returns whether any active alerts exist.
 *
 * @return bool
 */
function parish_alerts_has_active_alerts() {
	return ! empty( parish_alerts_get_active_posts() );
}

/**
 * Loads the alert interface on every visitor-facing page.
 *
 * The button intentionally remains available even when there are no active
 * alerts. JavaScript adds a badge only for unseen active alert revisions.
 */
function parish_alerts_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'parish-alerts',
		PARISH_ALERTS_URL . 'assets/public.css',
		array(),
		PARISH_ALERTS_VERSION
	);

	wp_enqueue_script(
		'parish-alerts',
		PARISH_ALERTS_URL . 'assets/public.js',
		array(),
		PARISH_ALERTS_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}

/**
 * Renders one alert summary for the popover or modal.
 *
 * @param WP_Post $alert Alert post.
 * @param string  $context popover or modal.
 */
function parish_alerts_render_alert_summary( $alert, $context ) {
	$level = parish_alerts_sanitize_level( get_post_meta( $alert->ID, '_parish_alert_level', true ) );
	$token = parish_alerts_get_revision_token( $alert );
	?>
	<article
		class="parish-alerts__item parish-alerts__item--<?php echo esc_attr( $level ); ?>"
		data-alert-token="<?php echo esc_attr( $token ); ?>"
		<?php echo 'modal' === $context ? 'data-alert-modal-item' : 'data-alert-item'; ?>
	>
		<p class="parish-alerts__level"><?php echo esc_html( parish_alerts_get_level_label( $level ) ); ?></p>
		<h3 class="parish-alerts__item-title"><a href="<?php echo esc_url( get_permalink( $alert ) ); ?>"><?php echo esc_html( get_the_title( $alert ) ); ?></a></h3>
		<div class="parish-alerts__summary"><?php echo wp_kses_post( wpautop( get_the_excerpt( $alert ) ) ); ?></div>
		<?php if ( 'popover' === $context ) : ?>
			<button class="parish-alerts__ack" type="button" data-alert-ack><?php esc_html_e( 'Mark as read', 'parish-alerts' ); ?></button>
		<?php endif; ?>
	</article>
	<?php
}

/**
 * Renders the always-available alert button, popover, and priority modal.
 */
function parish_alerts_render_interface() {
	if ( is_admin() ) {
		return;
	}

	$alerts          = parish_alerts_get_active_posts();
	$priority_alerts = array_filter(
		$alerts,
		static function ( $alert ) {
			$level = parish_alerts_sanitize_level( get_post_meta( $alert->ID, '_parish_alert_level', true ) );

			return in_array( $level, array( 'important', 'emergency' ), true );
		}
	);
	$archive_url     = get_post_type_archive_link( 'parish_alert' );
	?>
	<div
		class="parish-alerts"
		data-parish-alerts
		data-storage-key="parishAlertsAcknowledgedV1"
		data-label-mark-read="<?php esc_attr_e( 'Mark as read', 'parish-alerts' ); ?>"
		data-label-read="<?php esc_attr_e( 'Read', 'parish-alerts' ); ?>"
		data-label-button="<?php esc_attr_e( 'Alerts', 'parish-alerts' ); ?>"
		data-label-new-one="<?php esc_attr_e( '1 new alert', 'parish-alerts' ); ?>"
		data-label-new-many="<?php esc_attr_e( '%d new alerts', 'parish-alerts' ); ?>"
	>
		<div id="parish-alerts-panel" class="parish-alerts__panel" role="region" aria-labelledby="parish-alerts-heading" hidden>
			<div class="parish-alerts__panel-header">
				<h2 id="parish-alerts-heading" class="parish-alerts__heading"><?php esc_html_e( 'Parish Alerts', 'parish-alerts' ); ?></h2>
				<button class="parish-alerts__close" type="button" data-parish-alerts-close aria-label="<?php esc_attr_e( 'Close parish alerts', 'parish-alerts' ); ?>">&times;</button>
			</div>

			<div class="parish-alerts__items">
				<?php if ( $alerts ) : ?>
					<?php foreach ( $alerts as $alert ) : ?>
						<?php parish_alerts_render_alert_summary( $alert, 'popover' ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="parish-alerts__empty"><?php esc_html_e( 'There are no active parish alerts right now.', 'parish-alerts' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="parish-alerts__panel-actions">
				<?php if ( $alerts ) : ?>
					<button class="parish-alerts__mark-all" type="button" data-alert-mark-all><?php esc_html_e( 'Mark all as read', 'parish-alerts' ); ?></button>
				<?php endif; ?>
				<?php if ( $archive_url ) : ?>
					<a class="parish-alerts__all" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'View all active alerts', 'parish-alerts' ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<button class="parish-alerts__trigger" type="button" data-parish-alerts-trigger aria-controls="parish-alerts-panel" aria-expanded="false" aria-label="<?php esc_attr_e( 'Alerts', 'parish-alerts' ); ?>">
			<svg class="parish-alerts__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a7 7 0 0 0-7 7v3.59L3.29 14.3A1 1 0 0 0 4 16h16a1 1 0 0 0 .71-1.7L19 12.59V9a7 7 0 0 0-7-7Zm0 20a3 3 0 0 0 2.82-2h-5.64A3 3 0 0 0 12 22Z"/></svg>
			<span><?php esc_html_e( 'Alerts', 'parish-alerts' ); ?></span>
			<span class="parish-alerts__count" data-alert-count hidden></span>
		</button>

		<?php if ( $priority_alerts ) : ?>
			<div class="parish-alerts-modal" data-alert-modal hidden>
				<div class="parish-alerts-modal__backdrop" aria-hidden="true"></div>
				<div class="parish-alerts-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="parish-alerts-modal-heading" aria-describedby="parish-alerts-modal-description">
					<div class="parish-alerts-modal__header">
						<div>
							<p class="parish-alerts-modal__eyebrow"><?php esc_html_e( 'Please review', 'parish-alerts' ); ?></p>
							<h2 id="parish-alerts-modal-heading" class="parish-alerts-modal__heading"><?php esc_html_e( 'Important Parish Alerts', 'parish-alerts' ); ?></h2>
						</div>
						<button class="parish-alerts__close" type="button" data-alert-modal-dismiss aria-label="<?php esc_attr_e( 'Not now', 'parish-alerts' ); ?>">&times;</button>
					</div>
					<p id="parish-alerts-modal-description" class="parish-alerts-modal__description"><?php esc_html_e( 'Please acknowledge these new or updated alerts. They will remain available from the Alerts button.', 'parish-alerts' ); ?></p>
					<div class="parish-alerts-modal__items">
						<?php foreach ( $priority_alerts as $alert ) : ?>
							<?php parish_alerts_render_alert_summary( $alert, 'modal' ); ?>
						<?php endforeach; ?>
					</div>
					<div class="parish-alerts-modal__actions">
						<button class="parish-alerts-modal__secondary" type="button" data-alert-modal-dismiss><?php esc_html_e( 'Not now', 'parish-alerts' ); ?></button>
						<button class="parish-alerts-modal__primary" type="button" data-alert-modal-ack><?php esc_html_e( 'Acknowledge all shown', 'parish-alerts' ); ?></button>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'pre_get_posts', 'parish_alerts_filter_archive' );
add_filter( 'get_the_archive_title', 'parish_alerts_archive_title' );
add_action( 'wp_enqueue_scripts', 'parish_alerts_enqueue_assets' );
add_action( 'wp_footer', 'parish_alerts_render_interface' );

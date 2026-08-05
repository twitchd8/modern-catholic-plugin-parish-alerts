<?php
/**
 * Active-alert archive and sitewide popover.
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
 * Loads popover assets only when active alerts will be rendered.
 */
function parish_alerts_enqueue_popover_assets() {
	if ( is_admin() || ! parish_alerts_has_active_alerts() ) {
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
 * Returns whether any active alerts exist, memoized for this request.
 *
 * @return bool
 */
function parish_alerts_has_active_alerts() {
	static $has_alerts = null;

	if ( null === $has_alerts ) {
		$alert_ids = get_posts(
			array(
				'post_type'              => 'parish_alert',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => parish_alerts_active_meta_query(),
			)
		);
		$has_alerts = ! empty( $alert_ids );
	}

	return $has_alerts;
}

/**
 * Renders the sitewide alert button and popover.
 */
function parish_alerts_render_popover() {
	if ( is_admin() || ! parish_alerts_has_active_alerts() ) {
		return;
	}

	$alerts = new WP_Query(
		array(
			'post_type'      => 'parish_alert',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => parish_alerts_active_meta_query(),
		)
	);

	if ( ! $alerts->have_posts() ) {
		return;
	}

	$count       = absint( $alerts->found_posts );
	$archive_url = get_post_type_archive_link( 'parish_alert' );
	?>
	<div class="parish-alerts" data-parish-alerts>
		<div id="parish-alerts-panel" class="parish-alerts__panel" role="region" aria-labelledby="parish-alerts-heading" hidden>
			<div class="parish-alerts__panel-header">
				<h2 id="parish-alerts-heading" class="parish-alerts__heading"><?php esc_html_e( 'Parish Alerts', 'parish-alerts' ); ?></h2>
				<button class="parish-alerts__close" type="button" data-parish-alerts-close aria-label="<?php esc_attr_e( 'Close parish alerts', 'parish-alerts' ); ?>">&times;</button>
			</div>

			<div class="parish-alerts__items">
				<?php
				while ( $alerts->have_posts() ) {
					$alerts->the_post();
					$level = parish_alerts_sanitize_level( get_post_meta( get_the_ID(), '_parish_alert_level', true ) );
					?>
					<article class="parish-alerts__item parish-alerts__item--<?php echo esc_attr( $level ); ?>">
						<p class="parish-alerts__level"><?php echo esc_html( ucfirst( $level ) ); ?></p>
						<h3 class="parish-alerts__item-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<div class="parish-alerts__summary"><?php echo wp_kses_post( wpautop( get_the_excerpt() ) ); ?></div>
					</article>
					<?php
				}
				wp_reset_postdata();
				?>
			</div>

			<?php if ( $archive_url ) : ?>
				<a class="parish-alerts__all" href="<?php echo esc_url( $archive_url ); ?>">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of active alerts. */
							_n( 'View %d active alert', 'View all %d active alerts', $count, 'parish-alerts' ),
							$count
						)
					);
					?>
				</a>
			<?php endif; ?>
		</div>

		<button class="parish-alerts__trigger" type="button" data-parish-alerts-trigger aria-controls="parish-alerts-panel" aria-expanded="false">
			<svg class="parish-alerts__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a7 7 0 0 0-7 7v3.59L3.29 14.3A1 1 0 0 0 4 16h16a1 1 0 0 0 .71-1.7L19 12.59V9a7 7 0 0 0-7-7Zm0 20a3 3 0 0 0 2.82-2h-5.64A3 3 0 0 0 12 22Z"/></svg>
			<span><?php esc_html_e( 'Alerts', 'parish-alerts' ); ?></span>
			<span class="parish-alerts__count" aria-label="<?php echo esc_attr( sprintf( _n( '%d active alert', '%d active alerts', $count, 'parish-alerts' ), $count ) ); ?>"><?php echo esc_html( $count ); ?></span>
		</button>
	</div>
	<?php
}

add_action( 'pre_get_posts', 'parish_alerts_filter_archive' );
add_filter( 'get_the_archive_title', 'parish_alerts_archive_title' );
add_action( 'wp_enqueue_scripts', 'parish_alerts_enqueue_popover_assets' );
add_action( 'wp_footer', 'parish_alerts_render_popover' );

<?php
/**
 * Admin notices: the site timezone must be a named timezone.
 *
 * With a manual offset ("UTC−3") WordPress cannot follow timezone rule
 * changes, and new events fall back to the venue timezone. A named timezone
 * other than the venue's records wall times in the wrong zone.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Admin;

use CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show the warning on Event screens, the plugin settings, General
 * settings and the dashboard.
 */
function timezone_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! ( Core\POST_TYPE === $screen->post_type || in_array( $screen->id, array( 'dashboard', 'options-general' ), true ) ) ) {
		return;
	}

	$named = Core\site_named_timezone();
	$venue = Core\FALLBACK_TIMEZONE;
	$link  = '<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Ajustes → Generales', 'casa-eventos' ) . '</a>';

	if ( '' === $named ) {
		$message = sprintf(
			/* translators: 1: settings link, 2: timezone identifier. */
			__( 'Casa Eventos: la zona horaria del sitio es un desfase manual. Elegí una ciudad (Buenos Aires) en %1$s. Mientras tanto, los eventos nuevos usan %2$s.', 'casa-eventos' ),
			$link,
			esc_html( Core\timezone_for_new_event() )
		);
		$class = 'notice-error';
	} elseif ( $named !== $venue ) {
		$message = sprintf(
			/* translators: 1: site timezone, 2: venue timezone, 3: settings link. */
			__( 'Casa Eventos: la zona horaria del sitio es %1$s, distinta de la del lugar (%2$s). Los eventos nuevos se guardan en la zona del sitio. Revisá %3$s.', 'casa-eventos' ),
			esc_html( $named ),
			esc_html( $venue ),
			$link
		);
		$class = 'notice-warning';
	} else {
		return;
	}

	printf( '<div class="notice %1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ) );
}
add_action( 'admin_notices', __NAMESPACE__ . '\\timezone_notice' );

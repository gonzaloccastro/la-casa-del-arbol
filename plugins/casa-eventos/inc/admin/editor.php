<?php
/**
 * Block editor: the "Datos del evento" and "Publicación del evento"
 * document sidebar panels (assets/admin/event-panel.js, plain JavaScript,
 * no build step). Validation happens on the server (core/enforcement.php).
 *
 * @package CasaEventos
 */

namespace CasaEventos\Admin;

use CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the panel script on the Event editor only.
 */
function enqueue_editor_assets() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || Core\POST_TYPE !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script(
		'casa-eventos-panel',
		CASA_EVENTOS_URL . 'assets/admin/event-panel.js',
		array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ),
		CASA_EVENTOS_VERSION,
		true
	);
	wp_add_inline_script(
		'casa-eventos-panel',
		'window.casaEventosPanel = ' . wp_json_encode( Core\editor_panel_config() ) . ';',
		'before'
	);
	wp_set_script_translations( 'casa-eventos-panel', 'casa-eventos' );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );

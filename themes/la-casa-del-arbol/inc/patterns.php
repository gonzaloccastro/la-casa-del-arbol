<?php
/**
 * Block pattern categories and block style variations.
 *
 * Pattern files in patterns/ are registered automatically by WordPress from
 * their file headers.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pattern categories.
 *
 * "lcda-demo" holds static demo/prototype content (demo events). Those
 * patterns are temporary; casa-eventos replaces them with real data that
 * follows docs/implementation/event-markup-contract.md.
 */
function lcda_register_pattern_categories() {
	register_block_pattern_category(
		'lcda',
		array(
			'label' => __( 'La Casa del Árbol', 'la-casa-del-arbol' ),
		)
	);

	register_block_pattern_category(
		'lcda-demo',
		array(
			'label'       => __( 'La Casa del Árbol — Demo', 'la-casa-del-arbol' ),
			'description' => __( 'Contenido de demostración temporal. Se reemplaza por datos reales de eventos.', 'la-casa-del-arbol' ),
		)
	);
}
add_action( 'init', 'lcda_register_pattern_categories' );

/**
 * Block style variations editors pick from the block sidebar.
 *
 * Buttons (Final Design Handoff §1.12): the default (unstyled) core button is
 * the red Primary variant; visuals live in assets/css/base.css. Paragraph
 * "Sticker (estrella)": visuals in assets/css/components.css.
 */
function lcda_register_block_styles() {
	$button_styles = array(
		'lcda-dark'      => __( 'Oscuro', 'la-casa-del-arbol' ),
		'lcda-outline'   => __( 'Contorno', 'la-casa-del-arbol' ),
		'lcda-text-link' => __( 'Enlace de texto', 'la-casa-del-arbol' ),
	);

	foreach ( $button_styles as $name => $label ) {
		register_block_style(
			'core/button',
			array(
				'name'  => $name,
				'label' => $label,
			)
		);
	}

	// Star sticker for a short label such as "A la gorra" (Handoff §1.8).
	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'lcda-sticker',
			'label' => __( 'Sticker (estrella)', 'la-casa-del-arbol' ),
		)
	);
}
add_action( 'init', 'lcda_register_block_styles' );

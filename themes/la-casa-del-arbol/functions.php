<?php
/**
 * La Casa del Árbol child theme.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme assets.
 */
function lcda_enqueue_assets() {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'lcda-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( get_template() )->get( 'Version' )
	);

	wp_enqueue_style(
		'lcda-style',
		get_stylesheet_uri(),
		array( 'lcda-parent-style' ),
		$theme->get( 'Version' )
	);

	wp_enqueue_style(
		'lcda-base',
		get_stylesheet_directory_uri() . '/assets/css/base.css',
		array( 'lcda-style' ),
		$theme->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'lcda_enqueue_assets', 20 );

/**
 * Editor support.
 */
function lcda_theme_setup() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/base.css' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
}
add_action( 'after_setup_theme', 'lcda_theme_setup' );

/**
 * Custom block pattern category.
 */
function lcda_register_pattern_category() {
	register_block_pattern_category(
		'lcda',
		array(
			'label' => __( 'La Casa del Árbol', 'la-casa-del-arbol' ),
		)
	);
}
add_action( 'init', 'lcda_register_pattern_category' );

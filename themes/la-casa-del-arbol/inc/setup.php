<?php
/**
 * Theme supports and editor setup.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme supports.
 */
function lcda_theme_setup() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/base.css' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
}
add_action( 'after_setup_theme', 'lcda_theme_setup' );

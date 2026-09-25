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
	add_editor_style( array( 'assets/css/base.css', 'assets/css/components.css', 'assets/css/home.css' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
}
add_action( 'after_setup_theme', 'lcda_theme_setup' );

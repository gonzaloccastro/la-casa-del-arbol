<?php
/**
 * Front-end assets.
 *
 * Fonts: the approved families (Anton, Archivo, Jost, Work Sans) are declared
 * in theme.json with fallback stacks. No font files are loaded yet; once
 * licensed files are approved they are added as theme.json "fontFace"
 * entries, and WordPress prints the @font-face rules, so no code here changes.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache-busting version for a theme file: its modification time.
 *
 * @param string $relative_path Path relative to the theme root.
 * @return string
 */
function lcda_asset_version( $relative_path ) {
	$file = LCDA_DIR . '/' . ltrim( $relative_path, '/' );

	return file_exists( $file ) ? (string) filemtime( $file ) : wp_get_theme()->get( 'Version' );
}

/**
 * Enqueue theme styles.
 *
 * Neither Astra's style.css nor this theme's style.css holds any CSS (only
 * the theme header), so neither is enqueued. base.css loads after Astra's
 * main stylesheet so its resets win on equal specificity.
 */
function lcda_enqueue_assets() {
	$deps = wp_style_is( 'astra-theme-css', 'registered' ) ? array( 'astra-theme-css' ) : array();

	wp_enqueue_style(
		'lcda-base',
		LCDA_URI . '/assets/css/base.css',
		$deps,
		lcda_asset_version( 'assets/css/base.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'lcda_enqueue_assets', 20 );

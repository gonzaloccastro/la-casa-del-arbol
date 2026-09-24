<?php
/**
 * Front-end assets.
 *
 * Fonts: the approved families (Anton, Archivo, Jost, Work Sans) are
 * self-hosted WOFF2 files in assets/fonts/, declared as theme.json
 * "fontFace" entries. WordPress prints the @font-face rules itself, so
 * nothing is enqueued for them here. Sources and licenses: README.md.
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
 * Enqueue theme styles and scripts.
 *
 * Neither Astra's style.css nor this theme's style.css holds any CSS (only
 * the theme header), so neither is enqueued. base.css loads after Astra's
 * main stylesheet so its resets win on equal specificity. components.css
 * (section heading, burst, tag, event card...) loads on every page, because
 * editors can insert its patterns anywhere. The site chrome (header, mobile
 * menu, footer) is only rendered on Lienzo pages, so its CSS and JS load
 * only there.
 */
function lcda_enqueue_assets() {
	$deps = wp_style_is( 'astra-theme-css', 'registered' ) ? array( 'astra-theme-css' ) : array();

	wp_enqueue_style(
		'lcda-base',
		LCDA_URI . '/assets/css/base.css',
		$deps,
		lcda_asset_version( 'assets/css/base.css' )
	);

	wp_enqueue_style(
		'lcda-components',
		LCDA_URI . '/assets/css/components.css',
		array( 'lcda-base' ),
		lcda_asset_version( 'assets/css/components.css' )
	);

	if ( lcda_is_canvas() ) {
		wp_enqueue_style(
			'lcda-chrome',
			LCDA_URI . '/assets/css/chrome.css',
			array( 'lcda-base' ),
			lcda_asset_version( 'assets/css/chrome.css' )
		);

		wp_enqueue_script(
			'lcda-mobile-menu',
			LCDA_URI . '/assets/js/mobile-menu.js',
			array(),
			lcda_asset_version( 'assets/js/mobile-menu.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'lcda_enqueue_assets', 20 );

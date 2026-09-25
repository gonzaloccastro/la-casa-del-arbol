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
 * (section heading, burst, tag, event card, filter chips...), home.css (hero,
 * Festejá, Instagram, newsletter) and agenda.css (Agenda header and event
 * wall sections) load on every page, because editors can insert their
 * patterns anywhere. The site chrome (header, mobile menu, footer) is
 * only rendered on Lienzo pages, so its CSS and JS load only there.
 *
 * The hero carousel script is only registered here; inc/blocks.php enqueues
 * it when a page actually renders a hero (it prints in the footer).
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

	wp_enqueue_style(
		'lcda-home',
		LCDA_URI . '/assets/css/home.css',
		array( 'lcda-components' ),
		lcda_asset_version( 'assets/css/home.css' )
	);

	wp_enqueue_style(
		'lcda-agenda',
		LCDA_URI . '/assets/css/agenda.css',
		array( 'lcda-components' ),
		lcda_asset_version( 'assets/css/agenda.css' )
	);

	wp_register_script(
		'lcda-hero-carousel',
		LCDA_URI . '/assets/js/hero-carousel.js',
		array(),
		lcda_asset_version( 'assets/js/hero-carousel.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	// Interface strings of the carousel controls (%1$d = slide, %2$d = total).
	$hero_strings = array(
		'label'      => __( 'Imágenes de La Casa', 'la-casa-del-arbol' ),
		'carousel'   => __( 'carrusel', 'la-casa-del-arbol' ),
		'slide'      => __( 'imagen', 'la-casa-del-arbol' ),
		/* translators: 1: slide number, 2: number of slides. */
		'slideLabel' => __( '%1$d de %2$d', 'la-casa-del-arbol' ),
		'prev'       => __( 'Imagen anterior', 'la-casa-del-arbol' ),
		'next'       => __( 'Imagen siguiente', 'la-casa-del-arbol' ),
		'dots'       => __( 'Elegir imagen', 'la-casa-del-arbol' ),
		/* translators: 1: slide number, 2: number of slides. */
		'goTo'       => __( 'Imagen %1$d de %2$d', 'la-casa-del-arbol' ),
	);
	wp_add_inline_script(
		'lcda-hero-carousel',
		'window.lcdaHeroCarousel = ' . wp_json_encode( $hero_strings ) . ';',
		'before'
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

<?php
/**
 * Astra integration layer.
 *
 * Every adjustment to Astra's behaviour goes through Astra's public filters
 * and actions, collected in this file. Astra itself is never edited.
 * Verified against Astra 4.13.x.
 *
 * On Lienzo pages the theme owns the page: Astra's layout is forced to full
 * width, and Astra's header and footer are replaced by the V1 site chrome
 * (template-parts/site/). Other pages keep Astra's defaults for now.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current request renders the full-bleed Lienzo template.
 *
 * @return bool
 */
function lcda_is_canvas() {
	return is_page_template( 'page-templates/canvas.php' );
}

/**
 * Lienzo: Astra "full width / stretched" content layout.
 *
 * Astra still uses the legacy value 'page-builder' internally for this
 * layout; it adds the ast-page-builder-template body class and removes the
 * container width and padding.
 *
 * @param string $layout Astra content layout.
 * @return string
 */
function lcda_astra_content_layout( $layout ) {
	return lcda_is_canvas() ? 'page-builder' : $layout;
}
add_filter( 'astra_get_content_layout', 'lcda_astra_content_layout', 20 );

/**
 * Lienzo: no sidebar.
 *
 * @param string $layout Astra sidebar layout.
 * @return string
 */
function lcda_astra_page_layout( $layout ) {
	return lcda_is_canvas() ? 'no-sidebar' : $layout;
}
add_filter( 'astra_page_layout', 'lcda_astra_page_layout', 20 );

/**
 * Lienzo: no Astra title or featured image. The template never calls Astra's
 * content loop; these filters also cover Astra modules that render them from
 * other hooks.
 *
 * @param bool $enabled Whether Astra renders the element.
 * @return bool
 */
function lcda_astra_disable_on_canvas( $enabled ) {
	return lcda_is_canvas() ? false : $enabled;
}
add_filter( 'astra_the_title_enabled', 'lcda_astra_disable_on_canvas', 20 );
add_filter( 'astra_featured_image_enabled', 'lcda_astra_disable_on_canvas', 20 );

/**
 * Lienzo: replace Astra's header and footer with the V1 site chrome.
 *
 * Everything Astra (or its add-ons) attaches to astra_header/astra_footer is
 * Astra chrome: the legacy or builder header, the mobile header, the
 * off-canvas popup, the cart flyout, and the legacy or builder footer. On
 * Lienzo pages those regions belong to the theme, so every callback is
 * removed and ours is added. Runs on template_redirect, after Astra has
 * registered its hooks and once the page template is known. Astra's own
 * header.php/footer.php still provide the document wrapper, including the
 * skip link.
 */
function lcda_replace_astra_chrome() {
	if ( ! lcda_is_canvas() ) {
		return;
	}

	remove_all_actions( 'astra_header' );
	remove_all_actions( 'astra_footer' );

	add_action( 'astra_header', 'lcda_render_site_header' );
	add_action( 'astra_footer', 'lcda_render_site_footer' );
}
add_action( 'template_redirect', 'lcda_replace_astra_chrome', 99 );

/**
 * Output the V1 header and mobile menu.
 */
function lcda_render_site_header() {
	get_template_part( 'template-parts/site/header' );
}

/**
 * Output the V1 footer.
 */
function lcda_render_site_footer() {
	get_template_part( 'template-parts/site/footer' );
}

/**
 * Body class hook for Lienzo-specific CSS.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function lcda_canvas_body_class( $classes ) {
	if ( lcda_is_canvas() ) {
		$classes[] = 'lcda-canvas-page';
	}

	return $classes;
}
add_filter( 'body_class', 'lcda_canvas_body_class' );

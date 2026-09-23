<?php
/**
 * Presentation helpers used by the theme templates.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical logo markup.
 *
 * The SVG files in assets/images/ are the approved artwork taken unchanged
 * from docs/design/Website Direction.dc.html; only the fill was switched to
 * currentColor so one file serves every color context.
 *
 * @param string $variant 'symbol' (header) or 'full' (symbol + wordmark).
 * @param array  $attrs   Attributes added to the <svg> element.
 * @return string SVG markup, or an empty string if the file is missing.
 */
function lcda_get_logo( $variant = 'symbol', $attrs = array() ) {
	static $cache = array();

	$file = 'full' === $variant ? 'logo-full.svg' : 'logo-symbol.svg';

	if ( ! isset( $cache[ $file ] ) ) {
		$path           = LCDA_DIR . '/assets/images/' . $file;
		$cache[ $file ] = is_readable( $path ) ? trim( (string) file_get_contents( $path ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	}

	if ( '' === $cache[ $file ] ) {
		return '';
	}

	$attr_html = '';
	foreach ( $attrs as $name => $value ) {
		$attr_html .= ' ' . sanitize_key( $name ) . '="' . esc_attr( $value ) . '"';
	}

	return (string) preg_replace( '/^<svg\b/', '<svg' . $attr_html, $cache[ $file ], 1 );
}

/**
 * Link data of the first top-level item of the menu in a location.
 *
 * Used for single-link locations (the WhatsApp CTA). The visible wording is
 * decided by each template; only the destination comes from the menu.
 *
 * @param string $location Menu location slug.
 * @return array{url: string, target: string, rel: string}|null
 */
function lcda_get_location_link( $location ) {
	$locations = get_nav_menu_locations();

	if ( empty( $locations[ $location ] ) ) {
		return null;
	}

	$items = wp_get_nav_menu_items( $locations[ $location ] );

	if ( empty( $items ) ) {
		return null;
	}

	foreach ( $items as $item ) {
		if ( 0 !== (int) $item->menu_item_parent || ! empty( $item->_invalid ) || empty( $item->url ) ) {
			continue;
		}

		$rel = trim( (string) $item->xfn );
		if ( '_blank' === $item->target ) {
			$rel = trim( $rel . ' noopener' );
		}

		return array(
			'url'    => $item->url,
			'target' => (string) $item->target,
			'rel'    => $rel,
		);
	}

	return null;
}

/**
 * href/target/rel attributes for a link returned by lcda_get_location_link().
 *
 * @param array $link Link data.
 * @return string Escaped attribute string.
 */
function lcda_link_attributes( $link ) {
	$html = 'href="' . esc_url( $link['url'] ) . '"';

	if ( '' !== $link['target'] ) {
		$html .= ' target="' . esc_attr( $link['target'] ) . '"';
	}

	if ( '' !== $link['rel'] ) {
		$html .= ' rel="' . esc_attr( $link['rel'] ) . '"';
	}

	return $html;
}

/**
 * Render a theme menu location as a flat list.
 *
 * Renders nothing when no menu is assigned (no fallback page list).
 *
 * @param string $location Menu location slug.
 * @param array  $args     Extra wp_nav_menu() arguments (menu_id, menu_class...).
 */
function lcda_nav_menu( $location, $args = array() ) {
	if ( ! has_nav_menu( $location ) ) {
		return;
	}

	wp_nav_menu(
		array_merge(
			array(
				'theme_location' => $location,
				'container'      => false,
				'depth'          => 1,
				'fallback_cb'    => false,
				'link_before'    => '<span class="lcda-menu-label">',
				'link_after'     => '</span>',
				'lcda_menu'      => true,
			),
			$args
		)
	);
}

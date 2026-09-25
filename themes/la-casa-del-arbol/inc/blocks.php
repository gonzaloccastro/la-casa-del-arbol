<?php
/**
 * Render-time presentation behavior for theme patterns.
 *
 * - Hero carousel: its script is enqueued only when a page renders a hero
 *   (group block with the class "lcda-hero"), so other pages ship no JS.
 * - WhatsApp buttons: a core/button with the class "lcda-whatsapp-link" (the
 *   Festejá band) takes its destination from the "La Casa — CTA WhatsApp"
 *   menu location, the same source as the header CTA. The number is kept in
 *   one place and never written into pattern markup. With no menu assigned
 *   the button keeps whatever link the editor gave it (none by default).
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a parsed block carries a class in its "Additional CSS class(es)".
 *
 * @param array  $block      Parsed block.
 * @param string $class_name Class to look for.
 * @return bool
 */
function lcda_block_has_class( $block, $class_name ) {
	if ( empty( $block['attrs']['className'] ) ) {
		return false;
	}

	return in_array( $class_name, preg_split( '/\s+/', (string) $block['attrs']['className'] ), true );
}

/**
 * Enqueue the hero carousel script when a hero is rendered.
 *
 * @param string $block_content Rendered block.
 * @param array  $block         Parsed block.
 * @return string Unchanged content.
 */
function lcda_enqueue_hero_script( $block_content, $block ) {
	if ( lcda_block_has_class( $block, 'lcda-hero' ) ) {
		wp_enqueue_script( 'lcda-hero-carousel' );
	}

	return $block_content;
}
add_filter( 'render_block_core/group', 'lcda_enqueue_hero_script', 10, 2 );

/**
 * WhatsApp button destination from the CTA menu location.
 *
 * @param string $block_content Rendered block.
 * @param array  $block         Parsed block.
 * @return string
 */
function lcda_whatsapp_button_link( $block_content, $block ) {
	if ( ! lcda_block_has_class( $block, 'lcda-whatsapp-link' ) ) {
		return $block_content;
	}

	$link = lcda_get_location_link( 'lcda-cta' );
	$url  = null === $link ? '' : sanitize_url( $link['url'] );
	if ( '' === $url ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $tags->next_tag(
		array(
			'tag_name'   => 'a',
			'class_name' => 'wp-block-button__link',
		)
	) ) {
		return $block_content;
	}

	// The tag processor escapes attribute values itself.
	$tags->set_attribute( 'href', $url );

	if ( '' !== $link['target'] ) {
		$tags->set_attribute( 'target', $link['target'] );
	} else {
		$tags->remove_attribute( 'target' );
	}

	if ( '' !== $link['rel'] ) {
		$tags->set_attribute( 'rel', $link['rel'] );
	} else {
		$tags->remove_attribute( 'rel' );
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/button', 'lcda_whatsapp_button_link', 10, 2 );

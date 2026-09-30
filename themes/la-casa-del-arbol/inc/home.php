<?php
/**
 * Dynamic Home (E2.3): the "Eventos destacados" section.
 *
 * The Home stays an ordinary Gutenberg page (home-contract.md). Two groups
 * of its featured section are recognized at render time:
 *
 * - the grid   .lcda-event-grid--featured: its content becomes the featured
 *   event cards (the shared card, featured variant, <h3> titles);
 * - the section .lcda-section whose blocks contain that grid: removed as a
 *   whole (heading, links, grid) when there is nothing to show.
 *
 * Whatever the grid holds in the page (the editor-only note of the current
 * pattern, or the 4 demo cards of a page built from Home 0.4.x) is
 * replaced, so no demo card is ever shown. Without casa-eventos, or with no
 * eligible event, the whole section is left out.
 *
 * Eligibility, states, order and limit are the plugin's
 * (Queries::featured_events()); this file only marks up the result. The
 * section's heading copy is page content and is never rewritten here.
 * Plugin API only (rule 9).
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Home featured events, queried once per request.
 *
 * @return \CasaEventos\Core\Event[]|null Null when casa-eventos is unavailable.
 */
function lcda_home_featured_events() {
	static $events = false;
	if ( false !== $events ) {
		return $events;
	}
	$events = lcda_events_available() ? \CasaEventos\Core\Queries::featured_events( array( 'limit' => 4 ) ) : null;
	return $events;
}

/**
 * Whether a parsed block's inner blocks (at any depth) include a block with
 * the given class.
 *
 * @param array  $block      Parsed block.
 * @param string $class_name Class to look for.
 * @return bool
 */
function lcda_block_contains_class( $block, $class_name ) {
	foreach ( $block['innerBlocks'] ?? array() as $inner ) {
		if ( lcda_block_has_class( $inner, $class_name ) || lcda_block_contains_class( $inner, $class_name ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Slot: the featured cards (nothing when there are none).
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function lcda_home_featured_grid_slot( $content, $block ) {
	if ( ! lcda_block_has_class( $block, 'lcda-event-grid--featured' ) ) {
		return $content;
	}
	$events = lcda_home_featured_events();
	if ( ! $events ) {
		return '';
	}
	ob_start();
	foreach ( $events as $event ) {
		get_template_part(
			'template-parts/event/card',
			null,
			array(
				'event'   => $event,
				'variant' => 'featured',
				'heading' => 'h3',
			)
		);
	}
	$grid = lcda_replace_block_inner( $content, (string) ob_get_clean() );
	return null === $grid ? '' : $grid;
}
add_filter( 'render_block_core/group', 'lcda_home_featured_grid_slot', 10, 2 );

/**
 * Slot: the section around the featured grid, left out as a whole when
 * there are no featured events. Detected on the parsed blocks, because the
 * grid's rendered HTML is gone by then when it is empty.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function lcda_home_featured_section_slot( $content, $block ) {
	if ( ! lcda_block_has_class( $block, 'lcda-section' ) || ! lcda_block_contains_class( $block, 'lcda-event-grid--featured' ) ) {
		return $content;
	}
	return lcda_home_featured_events() ? $content : '';
}
add_filter( 'render_block_core/group', 'lcda_home_featured_section_slot', 10, 2 );

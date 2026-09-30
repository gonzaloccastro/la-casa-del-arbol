<?php
/**
 * Dynamic Agenda (E2.2): render slots on the Agenda page's blocks.
 *
 * The Agenda stays an ordinary Gutenberg page (agenda-contract.md). Three of
 * its blocks, recognized by their Agenda-only classes, are filled at render
 * time from casa-eventos:
 *
 * - heading  .lcda-agenda-header__title: its text becomes the selected
 *   month; the month navigation is printed right after it;
 * - list     .lcda-filter-chips: the category chips (nothing for a month
 *   without events);
 * - group    .lcda-event-grid--agenda: the month's event cards, or the
 *   empty-state message.
 *
 * Whatever those blocks hold in the page (the neutral fallbacks of the
 * current patterns, or the September heading, static chips and 9 demo
 * cards of a page built from Agenda 0.5.0) is replaced, so no demo card or
 * inert filter is ever shown. Without casa-eventos the chips and the grid
 * render nothing and the heading keeps its authored text.
 *
 * Request: ?mes=YYYY-MM and ?categoria={slug}, read from $_GET (no rewrite,
 * no query var). Selection, states, month membership and order are the
 * plugin's (Queries::month_events(), adjacent_event_month()); this file
 * only picks the selected category's cards out of the month's events, and
 * words and marks up the result. Plugin API only (rule 9).
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A string request parameter, unslashed; '' when absent or not a string
 * (e.g. ?mes[]=…).
 *
 * @param string $name Parameter.
 * @return string
 */
function lcda_agenda_param( $name ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- public, read-only view parameters; validated by the callers.
	return isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ? (string) wp_unslash( $_GET[ $name ] ) : '';
}

/**
 * The Agenda view of this request, computed once.
 *
 * @return array|null Null when casa-eventos is unavailable. Keys:
 *   current  'YYYY-MM' current venue month;
 *   month    'YYYY-MM' selected month (?mes when valid, else current);
 *   category WP_Term|null selected category (?categoria when it exists);
 *   events   Event[] the month's events (plugin selection and order);
 *   shown    Event[] the cards to show (events of the selected category);
 *   chips    WP_Term[] chip categories, in plugin order ([] = no chip list);
 *   prev     'YYYY-MM'|null, next 'YYYY-MM'|null event-bearing months.
 */
function lcda_agenda_view() {
	static $view = false;
	if ( false !== $view ) {
		return $view;
	}
	if ( ! lcda_events_available() ) {
		$view = null;
		return $view;
	}

	$current = \CasaEventos\Core\Queries::current_month();
	$month   = \CasaEventos\Core\Queries::parse_month( lcda_agenda_param( 'mes' ) );
	$month   = null === $month ? $current : $month;

	$slug       = sanitize_title( lcda_agenda_param( 'categoria' ) );
	$categories = \CasaEventos\Core\Queries::categories();
	$category   = null;
	foreach ( $categories as $term ) {
		if ( '' !== $slug && $term->slug === $slug ) {
			$category = $term;
			break;
		}
	}

	$events      = \CasaEventos\Core\Queries::month_events( $month );
	$represented = array();
	$shown       = array();
	foreach ( $events as $event ) {
		$term = $event->category();
		if ( $term ) {
			$represented[ $term->term_id ] = true;
		}
		if ( ! $category || ( $term && $term->term_id === $category->term_id ) ) {
			$shown[] = $event;
		}
	}

	// "Todos" + the month's categories; a selected category without events
	// this month keeps its (active) chip so its empty state has context.
	$chips = array();
	if ( $events ) {
		foreach ( $categories as $term ) {
			if ( isset( $represented[ $term->term_id ] ) || ( $category && $term->term_id === $category->term_id ) ) {
				$chips[] = $term;
			}
		}
	}

	$adjacent = $category ? array( 'category' => $category->slug ) : array();
	$view     = array(
		'current'  => $current,
		'month'    => $month,
		'category' => $category,
		'events'   => $events,
		'shown'    => $shown,
		'chips'    => $chips,
		'prev'     => \CasaEventos\Core\Queries::adjacent_event_month( $month, -1, $adjacent ),
		'next'     => \CasaEventos\Core\Queries::adjacent_event_month( $month, 1, $adjacent ),
	);
	return $view;
}

/**
 * Agenda URL for a month and category, on the page being rendered:
 * ?mes only for a month other than the current one, then ?categoria.
 *
 * @param string       $month    'YYYY-MM'.
 * @param WP_Term|null $category Category, null for all.
 * @return string Unescaped URL.
 */
function lcda_agenda_url( $month, $category = null ) {
	$view = lcda_agenda_view();
	$id   = is_singular() ? get_queried_object_id() : get_the_ID();
	$args = array();
	if ( $view && $month !== $view['current'] ) {
		$args['mes'] = $month;
	}
	if ( $category ) {
		$args['categoria'] = $category->slug;
	}
	return add_query_arg( $args, (string) get_permalink( $id ) );
}

/**
 * Month label in the site locale: "Septiembre" in the current year,
 * "Diciembre 2025" otherwise. Formatting only: the month key is shown in
 * UTC so no timezone can move it.
 *
 * @param string $month   'YYYY-MM'.
 * @param string $current Current 'YYYY-MM'.
 * @return string
 */
function lcda_agenda_month_label( $month, $current ) {
	$utc   = new DateTimeZone( 'UTC' );
	$date  = DateTimeImmutable::createFromFormat( '!Y-m', $month, $utc );
	$label = $date ? ucfirst( (string) wp_date( 'F', $date->getTimestamp(), $utc ) ) : $month;
	$year  = substr( $month, 0, 4 );
	return substr( $current, 0, 4 ) === $year ? $label : $label . ' ' . $year;
}

/**
 * Render an Agenda template part to a string.
 *
 * @param string $slug template-parts/agenda/{slug}.php.
 * @param array  $args Arguments.
 * @return string
 */
function lcda_agenda_part( $slug, array $args ) {
	ob_start();
	get_template_part( 'template-parts/agenda/' . $slug, null, $args );
	return (string) ob_get_clean();
}

/**
 * Slot: month heading + month navigation.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function lcda_agenda_heading_slot( $content, $block ) {
	if ( ! lcda_block_has_class( $block, 'lcda-agenda-header__title' ) ) {
		return $content;
	}
	$view = lcda_agenda_view();
	if ( ! $view ) {
		return $content;
	}
	$heading = lcda_replace_block_inner( $content, esc_html( lcda_agenda_month_label( $view['month'], $view['current'] ) ) );
	return ( null === $heading ? $content : $heading ) . lcda_agenda_part( 'nav', array( 'view' => $view ) );
}
add_filter( 'render_block_core/heading', 'lcda_agenda_heading_slot', 10, 2 );

/**
 * Slot: category chips (nothing without casa-eventos or without events).
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function lcda_agenda_chips_slot( $content, $block ) {
	if ( ! lcda_block_has_class( $block, 'lcda-filter-chips' ) ) {
		return $content;
	}
	$view = lcda_agenda_view();
	if ( ! $view || ! $view['chips'] ) {
		return '';
	}
	$chips = lcda_replace_block_inner( $content, lcda_agenda_part( 'chips', array( 'view' => $view ) ) );
	return null === $chips ? '' : $chips;
}
add_filter( 'render_block_core/list', 'lcda_agenda_chips_slot', 10, 2 );

/**
 * Slot: the event wall (cards, or the empty state in place of the grid;
 * nothing without casa-eventos).
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function lcda_agenda_wall_slot( $content, $block ) {
	if ( ! lcda_block_has_class( $block, 'lcda-event-grid--agenda' ) ) {
		return $content;
	}
	$view = lcda_agenda_view();
	if ( ! $view ) {
		return '';
	}
	if ( ! $view['shown'] ) {
		return lcda_agenda_part( 'empty', array( 'view' => $view ) );
	}
	$wall = lcda_replace_block_inner( $content, lcda_agenda_part( 'cards', array( 'view' => $view ) ) );
	return null === $wall ? '' : $wall;
}
add_filter( 'render_block_core/group', 'lcda_agenda_wall_slot', 10, 2 );

/**
 * Month-aware canonical of the Agenda page: its permalink, plus ?mes for a
 * valid month other than the current one. ?categoria is never part of it.
 * Applies only to the page being viewed when it holds the Agenda month
 * heading or event wall.
 *
 * @param string  $canonical Canonical URL.
 * @param WP_Post $post      Post.
 * @return string
 */
function lcda_agenda_canonical( $canonical, $post ) {
	if ( ! is_page() || ! $post instanceof WP_Post || get_queried_object_id() !== $post->ID ) {
		return $canonical;
	}
	if ( false === strpos( $post->post_content, 'lcda-event-grid--agenda' ) && false === strpos( $post->post_content, 'lcda-agenda-header__title' ) ) {
		return $canonical;
	}
	$view = lcda_agenda_view();
	if ( ! $view ) {
		return $canonical;
	}
	$url = (string) get_permalink( $post );
	return $view['month'] === $view['current'] ? $url : add_query_arg( 'mes', $view['month'], $url );
}
add_filter( 'get_canonical_url', 'lcda_agenda_canonical', 10, 2 );

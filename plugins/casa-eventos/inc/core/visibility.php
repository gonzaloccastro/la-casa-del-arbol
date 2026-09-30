<?php
/**
 * Public visibility of unlisted events.
 *
 * `listed` decides eligibility for Agenda / Home / Related. An unlisted
 * event stays public and reachable by its URL (never private, never 404),
 * but it is not advertised: noindex on its page, absent from the core
 * sitemap and from the normal site search. Listed events keep WordPress's
 * default behavior.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta query clause: listed events, and every post without the flag (other
 * post types; events always store it).
 *
 * @return array
 */
function listed_or_not_event_clause() {
	return array(
		'relation' => 'OR',
		array(
			'key'     => META_LISTED,
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'   => META_LISTED,
			'value' => '1',
		),
	);
}

/**
 * Add a clause to a query's meta_query (AND with what is already there).
 *
 * @param mixed $meta_query Existing meta_query.
 * @param array $clause     Clause.
 * @return array
 */
function and_meta_clause( $meta_query, array $clause ) {
	return empty( $meta_query ) ? array( $clause ) : array( 'relation' => 'AND', $meta_query, $clause );
}

/**
 * noindex on the page of an unlisted event.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function robots_for_unlisted( $robots ) {
	if ( ! is_singular( POST_TYPE ) ) {
		return $robots;
	}
	$event = Event::get( get_queried_object() );
	if ( $event && ! $event->is_listed() ) {
		$robots['noindex'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', __NAMESPACE__ . '\\robots_for_unlisted', 20 );

/**
 * Only listed events in the core sitemap of the Event post type (the page
 * count uses the same arguments).
 *
 * @param array  $args      WP_Query arguments.
 * @param string $post_type Post type.
 * @return array
 */
function sitemap_listed_only( $args, $post_type ) {
	if ( POST_TYPE !== $post_type ) {
		return $args;
	}
	$args['meta_query'] = and_meta_clause( $args['meta_query'] ?? array(), array( 'key' => META_LISTED, 'value' => '1' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', __NAMESPACE__ . '\\sitemap_listed_only', 10, 2 );

/**
 * Keep unlisted events out of the normal front-end search. Other post
 * types are unaffected (they have no listed flag).
 *
 * @param WP_Query $query Query.
 */
function exclude_unlisted_from_search( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	$query->set( 'meta_query', and_meta_clause( $query->get( 'meta_query' ), listed_or_not_event_clause() ) );
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\exclude_unlisted_from_search' );

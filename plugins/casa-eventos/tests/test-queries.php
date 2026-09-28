<?php
/**
 * Query API selection rules, evaluated by the bootstrap's WP_Query
 * stand-in over the in-memory store (the real SQL is verified in the
 * LocalWP runtime QA).
 *
 * E1.1 closed decision: Home featured excludes paused AND cancelled; the
 * Agenda month keeps both (and historical events).
 *
 * @package CasaEventos
 */

use CasaEventos\Core\Queries;
use function CasaEventos\Core\sync_event;

t_reset_store();

$now = ( new DateTimeImmutable( '2026-10-15 20:00:00', new DateTimeZone( 'America/Argentina/Buenos_Aires' ) ) )->getTimestamp();

/**
 * Published, listed event in October 2026.
 *
 * @param string $name  Title.
 * @param string $start Local start.
 * @param array  $meta  Extra meta.
 * @return int
 */
function t_query_event( $name, $start, array $meta = array() ) {
	$id   = t_add_post( array( 'post_status' => $meta['post_status'] ?? 'publish', 'post_title' => $name ) );
	$meta = array_merge( array( '_casa_start' => $start, '_casa_access_mode' => 'whatsapp' ), $meta );
	unset( $meta['post_status'] );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	sync_event( $id );
	return $id;
}

$active    = t_query_event( 'active', '2026-10-20 21:00:00', array( '_casa_featured' => '1' ) );
$paused    = t_query_event( 'paused', '2026-10-21 21:00:00', array( '_casa_featured' => '1', '_casa_status' => 'paused' ) );
$cancelled = t_query_event( 'cancelled', '2026-10-22 21:00:00', array( '_casa_featured' => '1', '_casa_status' => 'cancelled' ) );
$past      = t_query_event( 'past', '2026-10-02 21:00:00', array( '_casa_featured' => '1' ) );
$unlisted  = t_query_event( 'unlisted', '2026-10-23 21:00:00', array( '_casa_featured' => '1', '_casa_listed' => '0' ) );
$plain     = t_query_event( 'not featured', '2026-10-24 21:00:00' );
$draft     = t_query_event( 'draft', '2026-10-25 21:00:00', array( '_casa_featured' => '1', 'post_status' => 'draft' ) );
$later     = t_query_event( 'active later', '2026-11-05 21:00:00', array( '_casa_featured' => '1' ) );

$ids = static fn( array $events ) => array_map( static fn( $e ) => $e->id(), $events );

t_group( 'queries/featured_events (E1.1: paused and cancelled excluded)' );
$featured = $ids( Queries::featured_events( array( 'timestamp' => $now ) ) );
t_eq( array( $active, $later ), $featured, 'only active, listed, featured, published, not finished; nearest first; not bounded to the month' );
t_ok( ! in_array( $paused, $featured, true ), 'paused excluded' );
t_ok( ! in_array( $cancelled, $featured, true ), 'cancelled excluded' );
t_eq( array( $active ), $ids( Queries::featured_events( array( 'timestamp' => $now, 'limit' => 1 ) ) ), 'limit' );
$clause = array_values( array_filter( $GLOBALS['t_last_query']['meta_query'], static fn( $c ) => is_array( $c ) && '_casa_status' === ( $c['key'] ?? '' ) ) );
t_eq( array( array( 'key' => '_casa_status', 'value' => array( 'paused', 'cancelled' ), 'compare' => 'NOT IN' ) ), $clause, 'one status clause: NOT IN (paused, cancelled)' );
update_post_meta( $paused, '_casa_status', 'active' );
t_eq( array( $active, $paused, $later ), $ids( Queries::featured_events( array( 'timestamp' => $now ) ) ), 'a resumed (active again) event is eligible again' );
update_post_meta( $paused, '_casa_status', 'paused' );

t_group( 'queries/month_events keeps paused, cancelled and historical' );
$month = $ids( Queries::month_events( '2026-10' ) );
t_eq( array( $past, $active, $paused, $cancelled, $plain ), $month, 'October: past, active, paused, cancelled, not featured (no unlisted, no draft)' );
$status_clauses = array_filter( $GLOBALS['t_last_query']['meta_query'], static fn( $c ) => is_array( $c ) && '_casa_status' === ( $c['key'] ?? '' ) );
t_eq( array(), array_values( $status_clauses ), 'no status clause in the month query' );

t_group( 'queries/unchanged rules' );
t_eq( array( $active, $paused, $cancelled, $plain, $later ), $ids( Queries::upcoming_events( array( 'timestamp' => $now ) ) ), 'upcoming: includes paused and cancelled by default' );
t_eq( array( $active, $paused, $plain, $later ), $ids( Queries::upcoming_events( array( 'timestamp' => $now, 'include_cancelled' => false ) ) ), 'upcoming without cancelled keeps paused' );
t_eq( array( $paused, $plain, $later ), $ids( Queries::related_events( $active, array( 'timestamp' => $now ) ) ), 'related (provisional): excludes itself and cancelled only' );

t_reset_store();

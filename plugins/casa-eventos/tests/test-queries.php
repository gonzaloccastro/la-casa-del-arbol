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
t_eq( array( $plain, $later ), $ids( Queries::related_events( $active, array( 'timestamp' => $now, 'limit' => 10 ) ) ), 'related (E2.1): excludes itself, cancelled, paused, finished, unlisted and drafts; nearest first' );
t_eq( array( $plain ), $ids( Queries::related_events( $active, array( 'timestamp' => $now, 'limit' => 1 ) ) ), 'related limit' );
t_eq( array( $active, $later ), $ids( Queries::related_events( $plain, array( 'timestamp' => $now ) ) ), 'related from another event: the active one is eligible, the current one excluded' );
t_eq( array(), $ids( Queries::related_events( $active, array( 'timestamp' => strtotime( '2026-12-01 00:00:00 UTC' ) ) ) ), 'related: none left → []' );
$clause = array_values( array_filter( $GLOBALS['t_last_query']['meta_query'], static fn( $c ) => is_array( $c ) && '_casa_status' === ( $c['key'] ?? '' ) ) );
t_eq( array( array( 'key' => '_casa_status', 'value' => array( 'paused', 'cancelled' ), 'compare' => 'NOT IN' ) ), $clause, 'related: one status clause NOT IN (paused, cancelled)' );
t_eq( array( $active, $paused, $plain, $later ), $ids( Queries::upcoming_events( array( 'timestamp' => $now, 'include_cancelled' => false ) ) ), 'upcoming keeps paused by default (Agenda-style semantics untouched)' );

t_group( 'queries/poster cache priming (no N+1)' );
$GLOBALS['t_thumb_primed'] = array();
Queries::related_events( $active, array( 'timestamp' => $now ) );
Queries::month_events( '2026-10' );
Queries::featured_events( array( 'timestamp' => $now ) );
t_eq( array( array( $plain, $later ), array( $past, $active, $paused, $cancelled, $plain ), array( $active, $later ) ), $GLOBALS['t_thumb_primed'], 'every public listing primes the featured images of exactly its results, once' );

// ----- E2.2: Agenda facades, month boundaries, adjacent months --------------------------

t_reset_store();

t_group( 'queries/parse_month facade (E2.2, R1)' );
t_eq( '2026-10', Queries::parse_month( '2026-10' ), 'valid month' );
t_eq( null, Queries::parse_month( '2026-13' ), 'impossible month' );
t_eq( null, Queries::parse_month( '2026-10x' ), 'malformed month' );
t_eq( null, Queries::parse_month( array( '2026-10' ) ), 'array' );
t_eq( null, Queries::parse_month( null ), 'absent' );
t_eq( null, Queries::parse_month( '9999-12' ), '9999-12 (R0)' );

t_group( 'queries/categories facade (E2.2, R2)' );
$GLOBALS['t_term_objects'] = array(
	51 => new WP_Term( 51, 'Música en vivo', 'm%c3%basica-en-vivo' ),
	52 => new WP_Term( 52, 'Cine', 'cine' ),
	53 => new WP_Term( 53, 'Fiesta', 'fiesta' ),
	54 => new WP_Term( 54, 'Arte', 'arte' ),
);
$GLOBALS['t_term_meta'][51]['_casa_order'] = 2;
$GLOBALS['t_term_meta'][52]['_casa_order'] = 1;
$GLOBALS['t_term_meta'][53]['_casa_order'] = 2;
$GLOBALS['t_term_meta'][54]['_casa_order'] = 3;
$slugs = static fn( array $terms ) => array_map( static fn( $t ) => $t->slug, $terms );
t_eq( array( 'cine', 'fiesta', 'm%c3%basica-en-vivo', 'arte' ), $slugs( Queries::categories() ), 'chip order: order meta, then name' );
t_ok( Queries::categories()[0] instanceof WP_Term, 'returns WP_Term objects' );

/**
 * Published, listed event with one category (term ID, 0 = none).
 *
 * @param string $start Local start.
 * @param int    $term  Term ID.
 * @param array  $meta  Extra meta (post_status allowed).
 * @return int
 */
function t_agenda_event( $start, $term, array $meta = array() ) {
	$id = t_query_event( 'e ' . $start, $start, $meta );
	if ( $term ) {
		t_set_terms( $id, array( $term ) );
	}
	return $id;
}

$dec25   = t_agenda_event( '2025-12-12 21:00:00', 52 );                                        // Cine, other year (historical).
$aug     = t_agenda_event( '2026-08-14 21:00:00', 53 );                                        // Fiesta.
$sep_a   = t_agenda_event( '2026-09-05 21:00:00', 52 );                                        // Cine.
$sep_p   = t_agenda_event( '2026-09-12 21:00:00', 51, array( '_casa_status' => 'paused' ) );    // Música, paused.
$sep_x   = t_agenda_event( '2026-09-19 21:00:00', 52, array( '_casa_status' => 'cancelled' ) ); // Cine, cancelled.
$sep_u   = t_agenda_event( '2026-09-20 21:00:00', 53, array( '_casa_listed' => '0' ) );         // unlisted.
$sep_d   = t_agenda_event( '2026-09-21 21:00:00', 53, array( 'post_status' => 'draft' ) );      // draft.
$sep_s   = t_agenda_event( '2026-09-22 21:00:00', 53, array( 'post_status' => 'future' ) );     // scheduled.
$sep_end = t_agenda_event( '2026-09-30 23:59:00', 51, array( '_casa_end' => '2026-10-01 03:00:00' ) ); // last minute, runs into October.
$oct_0   = t_agenda_event( '2026-10-01 00:00:00', 52 );                                        // first minute of October.
$dec_end = t_agenda_event( '2026-12-31 23:59:00', 51 );                                        // Música.
$jan_0   = t_agenda_event( '2027-01-01 00:00:00', 53 );                                        // Fiesta.
$mar_u   = t_agenda_event( '2027-03-10 21:00:00', 51, array( '_casa_listed' => '0' ) );         // only an unlisted event in March 2027.

t_group( 'queries/month_events boundaries and category (E2.2)' );
t_eq( array( $sep_a, $sep_p, $sep_x, $sep_end ), $ids( Queries::month_events( '2026-09' ) ), 'September: 23:59 on the 30th included (even running into October); unlisted, draft and scheduled excluded; chronological' );
t_eq( array( $oct_0 ), $ids( Queries::month_events( '2026-10' ) ), 'October: 00:00 on the 1st belongs to October' );
t_eq( array( $dec_end ), $ids( Queries::month_events( '2026-12' ) ), 'December 23:59 on the 31st stays in December' );
t_eq( array( $jan_0 ), $ids( Queries::month_events( '2027-01' ) ), 'January 00:00 on the 1st belongs to January' );
t_eq( array( $sep_a, $sep_x ), $ids( Queries::month_events( '2026-09', array( 'category' => 'cine' ) ) ), 'category: only that category, chronological' );
t_eq( array(), $ids( Queries::month_events( '2026-09', array( 'category' => 'nope' ) ) ), 'unknown category: none' );
t_eq( array(), $ids( Queries::month_events( '2026-11' ) ), 'gap month: none' );
t_eq( array(), $ids( Queries::month_events( '2027-03' ) ), 'a month with only unlisted events is empty' );

t_group( 'queries/adjacent_event_month (E2.2)' );
t_eq( '2026-10', Queries::adjacent_event_month( '2026-09', 1 ), 'next' );
t_eq( '2026-08', Queries::adjacent_event_month( '2026-09', -1 ), 'previous' );
t_eq( '2026-12', Queries::adjacent_event_month( '2026-10', 1 ), 'next skips the empty November' );
t_eq( '2026-10', Queries::adjacent_event_month( '2026-12', -1 ), 'previous skips the empty November (nearest first, not the oldest)' );
t_eq( '2025-12', Queries::adjacent_event_month( '2026-08', -1 ), 'previous reaches another year (history)' );
t_eq( null, Queries::adjacent_event_month( '2025-12', -1 ), 'nothing before the first month' );
t_eq( null, Queries::adjacent_event_month( '2027-01', 1 ), 'nothing after the last month (unlisted-only March ignored)' );
t_eq( '2026-12', Queries::adjacent_event_month( '2026-11', 1 ), 'next from an empty month' );
t_eq( '2026-10', Queries::adjacent_event_month( '2026-11', -1 ), 'previous from an empty month' );
t_eq( null, Queries::adjacent_event_month( '2026-13', 1 ), 'invalid month' );
t_eq( null, Queries::adjacent_event_month( '9999-12', 1 ), '9999-12: invalid, no wraparound (R0)' );
t_eq( null, Queries::adjacent_event_month( '9999-11', 1 ), 'from the last valid month: nothing after' );
t_eq( '2027-01', Queries::adjacent_event_month( '9999-11', -1 ), 'from the last valid month: the latest month backwards' );
t_eq( '2026-09', Queries::adjacent_event_month( '2026-08', 1, array() ), 'empty args = previous behavior' );
t_eq( '2026-09', Queries::adjacent_event_month( '2026-08', 1, array( 'category' => '' ) ), "category '' = all" );

t_group( 'queries/adjacent_event_month by category (E2.2, R3)' );
t_eq( '2026-12', Queries::adjacent_event_month( '2026-09', 1, array( 'category' => 'm%c3%basica-en-vivo' ) ), 'next Música skips October (Cine only) and the empty November' );
t_eq( '2026-10', Queries::adjacent_event_month( '2026-09', 1, array( 'category' => 'cine' ) ), 'next Cine' );
t_eq( '2025-12', Queries::adjacent_event_month( '2026-09', -1, array( 'category' => 'cine' ) ), 'previous Cine skips August (Fiesta only), into another year' );
t_eq( null, Queries::adjacent_event_month( '2026-10', 1, array( 'category' => 'cine' ) ), 'no later Cine' );
t_eq( '2026-08', Queries::adjacent_event_month( '2027-01', -1, array( 'category' => 'fiesta' ) ), 'previous Fiesta skips the unlisted/draft/scheduled September ones' );
t_eq( null, Queries::adjacent_event_month( '2026-09', 1, array( 'category' => 'arte' ) ), 'a category with no events: none' );
t_eq( null, Queries::adjacent_event_month( '2026-09', -1, array( 'category' => 'nope' ) ), 'unknown category: none' );
t_eq( array( array( 'taxonomy' => 'casa_categoria', 'field' => 'slug', 'terms' => 'nope' ) ), $GLOBALS['t_last_query']['tax_query'], 'same category rule as month_events (tax_query by slug)' );
Queries::adjacent_event_month( '2026-09', -1 );
t_ok( ! isset( $GLOBALS['t_last_query']['tax_query'] ), 'no tax_query without a category' );
t_eq( array( 'start_local' => 'DESC', 'ID' => 'DESC' ), $GLOBALS['t_last_query']['orderby'], 'previous orders by local start, newest first' );
t_eq( 1, $GLOBALS['t_last_query']['posts_per_page'], 'one event per lookup (never a month-by-month loop)' );

unset( $GLOBALS['t_term_objects'], $GLOBALS['t_term_meta'] );
t_reset_store();

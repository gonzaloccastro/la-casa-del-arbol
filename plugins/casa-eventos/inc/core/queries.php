<?php
/**
 * Event query API. Returns Event read models; carries no presentation
 * decisions (counts, fallbacks and empty states belong to the caller, E2).
 *
 * Every public listing is ordered by start (GMT) then ID, so same-minute
 * events keep a stable order. "Now" is rounded down to the minute so
 * identical queries within a minute share object-cache entries.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query API.
 */
final class Queries {

	/**
	 * Featured events for the Home: published, featured, listed, active
	 * (neither paused nor cancelled), not finished; nearest first. Not
	 * bounded to a month. Paused and cancelled events stay in the Agenda
	 * (month_events) but are never promoted.
	 *
	 * @param array $args {
	 *     @type int      $limit     Maximum events; 0 = no limit.
	 *     @type int|null $timestamp Now (unix); defaults to time().
	 * }
	 * @return Event[]
	 */
	public static function featured_events( array $args = array() ) {
		$args = array_merge( array( 'limit' => 0, 'timestamp' => null ), $args );
		return self::run(
			array(
				self::listed_clause(),
				array(
					'key'   => META_FEATURED,
					'value' => '1',
				),
				self::status_not_in_clause( array( STATUS_PAUSED, STATUS_CANCELLED ) ),
				self::not_finished_clause( $args['timestamp'] ),
			),
			self::order_by_start_gmt(),
			(int) $args['limit']
		);
	}

	/**
	 * Upcoming (current or future) listed events, nearest first.
	 *
	 * @param array $args {
	 *     @type int      $limit             Maximum events; 0 = no limit.
	 *     @type int[]    $exclude           Event IDs to leave out.
	 *     @type bool     $include_cancelled Include cancelled events (they stay public).
	 *     @type bool     $include_paused    Include paused events (they stay public).
	 *     @type string   $category          Category slug; '' = all.
	 *     @type int|null $timestamp         Now (unix); defaults to time().
	 * }
	 * @return Event[]
	 */
	public static function upcoming_events( array $args = array() ) {
		$args = array_merge(
			array(
				'limit'             => 0,
				'exclude'           => array(),
				'include_cancelled' => true,
				'include_paused'    => true,
				'category'          => '',
				'timestamp'         => null,
			),
			$args
		);

		$meta     = array( self::listed_clause(), self::not_finished_clause( $args['timestamp'] ) );
		$excluded = array();
		if ( ! $args['include_paused'] ) {
			$excluded[] = STATUS_PAUSED;
		}
		if ( ! $args['include_cancelled'] ) {
			$excluded[] = STATUS_CANCELLED;
		}
		if ( $excluded ) {
			$meta[] = self::status_not_in_clause( $excluded );
		}

		$extra = array();
		if ( $args['exclude'] ) {
			$extra['post__not_in'] = array_map( 'intval', (array) $args['exclude'] );
		}
		if ( '' !== (string) $args['category'] ) {
			$extra['tax_query'] = self::category_tax_query( $args['category'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		return self::run( $meta, self::order_by_start_gmt(), (int) $args['limit'], $extra );
	}

	/**
	 * Related events for an event ("También en la agenda"): other upcoming
	 * listed events that are neither paused nor cancelled, nearest first.
	 * Finished and unlisted events never qualify (upcoming_events).
	 *
	 * @param Event|int $event Event or ID.
	 * @param array     $args  { @type int $limit, @type int|null $timestamp }.
	 * @return Event[]
	 */
	public static function related_events( $event, array $args = array() ) {
		$id   = $event instanceof Event ? $event->id() : (int) $event;
		$args = array_merge( array( 'limit' => 3, 'timestamp' => null ), $args );
		return self::upcoming_events(
			array(
				'limit'             => (int) $args['limit'],
				'exclude'           => array( $id ),
				'include_cancelled' => false,
				'include_paused'    => false,
				'timestamp'         => $args['timestamp'],
			)
		);
	}

	/**
	 * Listed published events that start in a local calendar month,
	 * including historical (finished) and cancelled events. An event
	 * belongs to the month in which it starts.
	 *
	 * @param string $ym   'YYYY-MM'.
	 * @param array  $args { @type string $category Category slug; '' = all. }.
	 * @return Event[] Empty for an invalid month.
	 */
	public static function month_events( $ym, array $args = array() ) {
		$bounds = month_bounds( $ym );
		if ( null === $bounds ) {
			return array();
		}
		$args  = array_merge( array( 'category' => '' ), $args );
		$extra = array();
		if ( '' !== (string) $args['category'] ) {
			$extra['tax_query'] = self::category_tax_query( $args['category'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		return self::run(
			array(
				self::listed_clause(),
				self::start_local_range_clause( $bounds[0], $bounds[1] ),
			),
			self::order_by_start_gmt(),
			0,
			$extra
		);
	}

	/**
	 * The nearest month before or after a month that has listed published
	 * events (historical months included; empty months are skipped). With a
	 * category, only events of that category count (same category rule as
	 * month_events()).
	 *
	 * @param string $ym        'YYYY-MM'.
	 * @param int    $direction 1 = next, -1 = previous.
	 * @param array  $args      { @type string $category Category slug; '' = all. }.
	 * @return string|null 'YYYY-MM', or null when there is none.
	 */
	public static function adjacent_event_month( $ym, $direction, array $args = array() ) {
		$bounds = month_bounds( $ym );
		if ( null === $bounds ) {
			return null;
		}
		$args   = array_merge( array( 'category' => '' ), $args );
		$next   = (int) $direction >= 0;
		$clause = array(
			'key'     => META_START,
			'value'   => $next ? $bounds[1] : $bounds[0],
			'compare' => $next ? '>=' : '<',
			'type'    => 'CHAR',
		);
		$extra  = array();
		if ( '' !== (string) $args['category'] ) {
			$extra['tax_query'] = self::category_tax_query( $args['category'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$events = self::run(
			array( self::listed_clause(), 'start_local' => $clause ),
			array(
				'start_local' => $next ? 'ASC' : 'DESC',
				'ID'          => $next ? 'ASC' : 'DESC',
			),
			1,
			$extra
		);
		return $events ? $events[0]->month() : null;
	}

	/**
	 * Validate a 'YYYY-MM' month key (the rule of every month argument here).
	 *
	 * @param mixed $raw Month key, e.g. from a request.
	 * @return string|null Canonical 'YYYY-MM', or null when invalid.
	 */
	public static function parse_month( $raw ) {
		return parse_month( $raw );
	}

	/**
	 * Every event category, in chip order. Terms only: which of them a view
	 * shows is the caller's decision.
	 *
	 * @return \WP_Term[]
	 */
	public static function categories() {
		return categories( false );
	}

	/**
	 * The current month in the venue timezone.
	 *
	 * @param int|null $timestamp Now (unix); defaults to time().
	 * @return string 'YYYY-MM'.
	 */
	public static function current_month( $timestamp = null ) {
		return current_month_in( timezone_for_new_event(), $timestamp );
	}

	/**
	 * The event that carries a UUID (any post status).
	 *
	 * @param string $uuid UUID.
	 * @return Event|null
	 */
	public static function find_by_uuid( $uuid ) {
		$ids = uuid_holders( sanitize_uuid( $uuid ) );
		return $ids ? Event::get( $ids[0] ) : null;
	}

	// ----- Admin list support (used by inc/admin/list-table.php) ------------

	/**
	 * Admin list views and their labels.
	 *
	 * @return array<string,string>
	 */
	public static function admin_views() {
		return array(
			'upcoming'  => __( 'Próximos', 'casa-eventos' ),
			'past'      => __( 'Pasados', 'casa-eventos' ),
			'paused'    => __( 'Pausados', 'casa-eventos' ),
			'cancelled' => __( 'Cancelados', 'casa-eventos' ),
		);
	}

	/**
	 * Meta query of an admin view, matching the effective state:
	 * upcoming = not finished and not cancelled; past = finished and not
	 * cancelled; paused = paused and not finished; cancelled = cancelled.
	 *
	 * @param string   $view      View key.
	 * @param int|null $timestamp Now (unix).
	 * @return array Meta query clauses (AND), empty for an unknown view.
	 */
	public static function admin_view_meta_query( $view, $timestamp = null ) {
		$now = now_gmt( $timestamp, true );
		switch ( $view ) {
			case 'upcoming':
				return array( self::not_finished_clause( $timestamp ), self::not_cancelled_clause() );
			case 'past':
				return array(
					array(
						'key'     => META_ENDS_GMT,
						'value'   => array( '1970-01-01 00:00:00', $now ),
						'compare' => 'BETWEEN',
						'type'    => 'CHAR',
					),
					self::not_cancelled_clause(),
				);
			case 'paused':
				return array(
					array(
						'key'   => META_STATUS,
						'value' => STATUS_PAUSED,
					),
					self::not_finished_clause( $timestamp ),
				);
			case 'cancelled':
				return array(
					array(
						'key'   => META_STATUS,
						'value' => STATUS_CANCELLED,
					),
				);
		}
		return array();
	}

	/**
	 * Query vars for the admin list: view, month filter and ordering.
	 *
	 * @param array $request {
	 *     @type string $view    View key ('' = all).
	 *     @type string $month   'YYYY-MM' of the local start ('' = all).
	 *     @type string $orderby 'start' or '' (default ordering).
	 *     @type string $order   ASC | DESC ('' = default).
	 * }
	 * @return array Query vars to set on the list query.
	 */
	public static function admin_list_query_vars( array $request ) {
		$request = array_merge(
			array(
				'view'    => '',
				'month'   => '',
				'orderby' => '',
				'order'   => '',
			),
			$request
		);

		$meta = self::admin_view_meta_query( $request['view'] );
		$vars = array();

		$bounds = '' !== $request['month'] ? month_bounds( $request['month'] ) : null;
		if ( $bounds ) {
			$meta[] = self::start_local_range_clause( $bounds[0], $bounds[1] );
		}

		if ( in_array( $request['orderby'], array( '', 'start' ), true ) ) {
			$order = strtoupper( (string) $request['order'] );
			if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
				// Upcoming reads nearest first; everything else newest first.
				$order = 'upcoming' === $request['view'] ? 'ASC' : 'DESC';
			}
			// Every synced event stores start_gmt (possibly ''), so none is dropped.
			$meta['start_order'] = array(
				'key'     => META_START_GMT,
				'compare' => 'EXISTS',
			);
			$vars['orderby']     = array(
				'start_order' => $order,
				'ID'          => $order,
			);
		}

		if ( $meta ) {
			$vars['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		return $vars;
	}

	/**
	 * Number of events in an admin view (all non-trash statuses).
	 *
	 * @param string $view View key.
	 * @return int
	 */
	public static function admin_view_count( $view ) {
		$meta = self::admin_view_meta_query( $view );
		if ( ! $meta ) {
			return 0;
		}
		$query = new WP_Query(
			array(
				'post_type'              => POST_TYPE,
				'post_status'            => array_values( array_diff( get_post_stati( array( 'show_in_admin_all_list' => true ) ), array( 'trash', 'auto-draft' ) ) ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'meta_query'             => array_merge( array( 'relation' => 'AND' ), $meta ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Months ('YYYY-MM') that have events, by local start, newest first.
	 *
	 * @return string[]
	 */
	public static function event_months() {
		global $wpdb;
		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT LEFT(pm.meta_value, 7) AS ym
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft')
				ORDER BY ym DESC",
				META_START,
				POST_TYPE
			)
		);
		return array_values( array_filter( array_map( __NAMESPACE__ . '\\parse_month', (array) $rows ) ) );
	}

	// ----- Internals --------------------------------------------------------

	/**
	 * Run a published-events query.
	 *
	 * @param array $meta    Meta clauses (AND).
	 * @param array $orderby Orderby (named clauses / ID).
	 * @param int   $limit   0 = no limit.
	 * @param array $extra   Extra WP_Query vars.
	 * @return Event[]
	 */
	private static function run( array $meta, array $orderby, $limit, array $extra = array() ) {
		if ( ! isset( $meta['start_order'] ) ) {
			$meta['start_order'] = array(
				'key'     => META_START_GMT,
				'value'   => '',
				'compare' => '!=',
			);
		}

		$query = new WP_Query(
			array_merge(
				array(
					'post_type'           => POST_TYPE,
					'post_status'         => 'publish',
					'posts_per_page'      => $limit > 0 ? $limit : -1,
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'meta_query'          => array_merge( array( 'relation' => 'AND' ), $meta ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'orderby'             => $orderby,
				),
				$extra
			)
		);

		// Posters: one query for every listed event's featured image, instead
		// of one per card when the frontend renders them.
		update_post_thumbnail_cache( $query );

		$events = array();
		foreach ( $query->posts as $post ) {
			$event = Event::get( $post );
			if ( $event ) {
				$events[] = $event;
			}
		}
		return $events;
	}

	/** @return array Order by start (GMT), then ID. */
	private static function order_by_start_gmt() {
		return array(
			'start_order' => 'ASC',
			'ID'          => 'ASC',
		);
	}

	/** @return array listed = '1'. */
	private static function listed_clause() {
		return array(
			'key'   => META_LISTED,
			'value' => '1',
		);
	}

	/** @return array status != cancelled (status is always stored). */
	private static function not_cancelled_clause() {
		return array(
			'key'     => META_STATUS,
			'value'   => STATUS_CANCELLED,
			'compare' => '!=',
		);
	}

	/**
	 * Status not in a list (status is always stored). Unknown stored values
	 * read as active (normalize_status), so they are not excluded here.
	 *
	 * @param string[] $statuses Excluded operational statuses.
	 * @return array
	 */
	private static function status_not_in_clause( array $statuses ) {
		return array(
			'key'     => META_STATUS,
			'value'   => $statuses,
			'compare' => 'NOT IN',
		);
	}

	/**
	 * Effective end after now.
	 *
	 * @param int|null $timestamp Now (unix).
	 * @return array
	 */
	private static function not_finished_clause( $timestamp ) {
		return array(
			'key'     => META_ENDS_GMT,
			'value'   => now_gmt( $timestamp, true ),
			'compare' => '>',
			'type'    => 'CHAR',
		);
	}

	/**
	 * Local start in [from, to).
	 *
	 * @param string $from Local lower bound (inclusive).
	 * @param string $to   Local upper bound (exclusive).
	 * @return array
	 */
	private static function start_local_range_clause( $from, $to ) {
		return array(
			'relation' => 'AND',
			array(
				'key'     => META_START,
				'value'   => $from,
				'compare' => '>=',
				'type'    => 'CHAR',
			),
			array(
				'key'     => META_START,
				'value'   => $to,
				'compare' => '<',
				'type'    => 'CHAR',
			),
		);
	}

	/**
	 * Tax query for one category slug.
	 *
	 * @param string $slug Category slug.
	 * @return array
	 */
	private static function category_tax_query( $slug ) {
		return array(
			array(
				'taxonomy' => TAXONOMY,
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $slug ),
			),
		);
	}
}

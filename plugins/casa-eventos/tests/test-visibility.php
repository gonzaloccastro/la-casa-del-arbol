<?php
/**
 * Unlisted events (E2.1, N13): noindex, out of the core sitemap and out of
 * the normal search; listed events and other post types untouched. The
 * real SQL, sitemap and HTTP output are verified in the LocalWP runtime QA.
 *
 * @package CasaEventos
 */

use function CasaEventos\Core\exclude_unlisted_from_search;
use function CasaEventos\Core\robots_for_unlisted;
use function CasaEventos\Core\sitemap_listed_only;
use function CasaEventos\Core\sync_event;

t_reset_store();
$listed   = t_add_post( array( 'post_status' => 'publish', 'post_title' => 'Listado' ) );
$unlisted = t_add_post( array( 'post_status' => 'publish', 'post_title' => 'Oculto' ) );
$page     = t_add_post( array( 'post_status' => 'publish', 'post_title' => 'Página', 'post_type' => 'page' ) );
update_post_meta( $unlisted, '_casa_listed', '0' );
sync_event( $listed );
sync_event( $unlisted );

t_group( 'visibility/robots' );
$GLOBALS['t_singular'] = 'casa_evento';
$GLOBALS['t_queried']  = $unlisted;
t_eq( array( 'max-image-preview' => 'large', 'noindex' => true ), robots_for_unlisted( array( 'max-image-preview' => 'large' ) ), 'unlisted event single → noindex (other directives kept)' );
$GLOBALS['t_queried'] = $listed;
t_eq( array( 'max-image-preview' => 'large' ), robots_for_unlisted( array( 'max-image-preview' => 'large' ) ), 'listed event single → unchanged (indexable)' );
$GLOBALS['t_singular'] = 'page';
$GLOBALS['t_queried']  = $page;
t_eq( array(), robots_for_unlisted( array() ), 'other singles → unchanged' );
unset( $GLOBALS['t_singular'], $GLOBALS['t_queried'] );
t_eq( array(), robots_for_unlisted( array() ), 'non-singular views → unchanged' );

t_group( 'visibility/sitemap' );
$args = sitemap_listed_only( array( 'post_type' => 'casa_evento' ), 'casa_evento' );
t_eq( array( array( 'key' => '_casa_listed', 'value' => '1' ) ), $args['meta_query'], 'event sitemap: listed only' );
$args = sitemap_listed_only( array( 'meta_query' => array( array( 'key' => 'x' ) ) ), 'casa_evento' );
t_eq( 'AND', $args['meta_query']['relation'], 'an existing meta_query is kept (AND)' );
t_eq( array( 'post_type' => 'page' ), sitemap_listed_only( array( 'post_type' => 'page' ), 'page' ), 'other sitemaps untouched' );
// Evaluate the clause with the WP_Query stand-in.
$q = new WP_Query( array_merge( sitemap_listed_only( array( 'post_type' => 'casa_evento' ), 'casa_evento' ), array( 'post_status' => 'publish' ) ) );
t_eq( array( $listed ), array_map( static fn( $p ) => $p->ID, $q->posts ), 'evaluated: the unlisted event is not in the sitemap query' );

t_group( 'visibility/search' );
/** Minimal main-query double for pre_get_posts. */
class T_Search_Query {
	public $vars;
	public $main;
	public $search;
	public function __construct( array $vars, $main = true, $search = true ) {
		$this->vars   = $vars;
		$this->main   = $main;
		$this->search = $search;
	}
	public function is_main_query() {
		return $this->main;
	}
	public function is_search() {
		return $this->search;
	}
	public function get( $key ) {
		return $this->vars[ $key ] ?? '';
	}
	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}
}
$search = new T_Search_Query( array( 's' => 'x' ) );
exclude_unlisted_from_search( $search );
$found = array_map( static fn( $p ) => $p->ID, array_filter( $GLOBALS['t_posts'], static fn( $p ) => t_meta_query_matches( $p->ID, $search->vars['meta_query'] ) ) );
sort( $found );
t_eq( array( $listed, $page ), array_values( $found ), 'search: listed event and the page match; the unlisted event does not' );
foreach ( array( array( false, true, 'secondary query' ), array( true, false, 'non-search main query' ) ) as list( $main, $is_search, $label ) ) {
	$other = new T_Search_Query( array( 's' => 'x' ), $main, $is_search );
	exclude_unlisted_from_search( $other );
	t_ok( ! isset( $other->vars['meta_query'] ), "$label untouched" );
}
$GLOBALS['t_is_admin'] = true;
$admin_search          = new T_Search_Query( array( 's' => 'x' ) );
exclude_unlisted_from_search( $admin_search );
t_ok( ! isset( $admin_search->vars['meta_query'] ), 'wp-admin search untouched (admins find unlisted events)' );
$GLOBALS['t_is_admin'] = false;
$with_meta = new T_Search_Query( array( 's' => 'x', 'meta_query' => array( array( 'key' => 'y' ) ) ) );
exclude_unlisted_from_search( $with_meta );
t_eq( 'AND', $with_meta->vars['meta_query']['relation'], 'search: an existing meta_query is kept (AND)' );
t_reset_store();

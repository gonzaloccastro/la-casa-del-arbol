<?php
/**
 * Server-side enforcement: REST pre-insert and admin (Quick Edit) saves.
 *
 * @package CasaEventos
 */

use CasaEventos\Core\Queries;
use function CasaEventos\Core\guard_admin_publish;
use function CasaEventos\Core\sync_event;
use function CasaEventos\Core\validate_rest_insert;

t_reset_store();

/**
 * Run the REST filter for an event.
 *
 * @param int   $id       Event ID.
 * @param array $prepared Prepared post fields.
 * @param array $params   Request params (meta, casa_categoria).
 * @return mixed
 */
function t_rest( $id, array $prepared, array $params ) {
	$post = (object) array_merge( array( 'ID' => $id ), $prepared );
	return validate_rest_insert( $post, new WP_REST_Request( $params ) );
}

$draft = t_add_post( array( 'post_status' => 'draft', 'post_title' => '' ) );
sync_event( $draft );

t_group( 'rest/draft saves' );
t_ok( ! is_wp_error( t_rest( $draft, array( 'post_status' => 'draft' ), array() ) ), 'an incomplete draft saves' );
$r = t_rest( $draft, array( 'post_status' => 'draft' ), array( 'meta' => array( '_casa_start' => '2026-02-30 21:00:00' ) ) );
t_ok( is_wp_error( $r ), 'an impossible date is refused' );
t_ok( is_wp_error( $r ) && 0 === strpos( $r->get_error_message(), 'No se puede guardar el evento:' ), 'with a save message' );

t_group( 'rest/publish' );
$r = t_rest( $draft, array( 'post_status' => 'publish' ), array() );
t_ok( is_wp_error( $r ), 'publishing an empty event is refused' );
t_eq( 400, is_wp_error( $r ) ? $r->get_error_data()['status'] : 0, 'HTTP 400' );
t_ok( is_wp_error( $r ) && 0 === strpos( $r->get_error_message(), 'No se puede publicar el evento:' ), 'with a publish message' );

$complete = array(
	'meta'           => array(
		'_casa_start'       => '2026-09-05 21:00:00',
		'_casa_access_mode' => 'whatsapp',
		'_casa_capacity'    => null,
	),
	'casa_categoria' => array( 4 ),
);
$r = t_rest( $draft, array( 'post_status' => 'publish', 'post_title' => 'Lucernaria' ), $complete );
t_ok( ! is_wp_error( $r ), 'meta, terms and title from the same request make it publishable' );
t_ok( ! is_wp_error( t_rest( $draft, array( 'post_status' => 'future', 'post_title' => 'Lucernaria' ), $complete ) ), 'and schedulable' );
$r = t_rest( $draft, array( 'post_status' => 'publish', 'post_title' => 'Lucernaria' ), array_merge( $complete, array( 'casa_categoria' => array() ) ) );
t_ok( is_wp_error( $r ) && isset( $r->get_error_data()['errors']['category_count'] ), 'removing the category blocks publishing' );
$r = t_rest( $draft, array( 'post_status' => 'publish', 'post_title' => 'Lucernaria' ), array( 'meta' => array_merge( $complete['meta'], array( '_casa_capacity' => 0 ) ), 'casa_categoria' => array( 4 ) ) );
t_ok( is_wp_error( $r ) && isset( $r->get_error_data()['errors']['capacity_invalid'] ), 'capacity 0 is refused' );

t_group( 'rest/no partial persistence (runtime QA regression, report 18)' );
// Core validates meta against its schema only AFTER writing the post row;
// these must be refused up front, before anything is written.
foreach ( array(
	'label over maxLength'  => array( '_casa_entry_label' => str_repeat( 'x', 121 ) ),
	'non-boolean listed'    => array( '_casa_listed' => 'quizas' ),
	'start with spaces'     => array( '_casa_start' => ' 2026-11-20 21:00 ' ),
	'unknown access mode'   => array( '_casa_access_mode' => 'gratis' ),
	'capacity not integer'  => array( '_casa_capacity' => 'cien' ),
) as $label => $bad ) {
	$r = t_rest( $draft, array( 'post_status' => 'draft' ), array( 'meta' => $bad ) );
	t_ok( is_wp_error( $r ) && 400 === $r->get_error_data()['status'], "schema-invalid meta refused before the write: $label" );
}
t_ok( ! is_wp_error( t_rest( $draft, array( 'post_status' => 'draft' ), array( 'meta' => array( '_casa_entry_label' => str_repeat( 'x', 120 ), '_casa_capacity' => null ) ) ) ), 'maxLength boundary and null (delete) pass' );
$GLOBALS['t_missing_terms'] = array( 999999 );
$r = t_rest( $draft, array( 'post_status' => 'publish', 'post_title' => 'Lucernaria' ), array_merge( $complete, array( 'casa_categoria' => array( 999999 ) ) ) );
t_ok( is_wp_error( $r ) && isset( $r->get_error_data()['errors']['category_count'] ), 'a nonexistent category ID does not count as a category' );
$r = t_rest( $draft, array( 'post_status' => 'publish', 'post_title' => 'Lucernaria' ), array_merge( $complete, array( 'casa_categoria' => array( 999999, 4 ) ) ) );
t_ok( ! is_wp_error( $r ), 'one real category plus an unknown ID counts as one' );
$GLOBALS['t_missing_terms'] = array();

t_group( 'rest/published event stays valid' );
$pub = t_add_post( array( 'post_status' => 'publish', 'post_title' => 'Cine' ) );
update_post_meta( $pub, '_casa_start', '2026-09-05 21:00:00' );
update_post_meta( $pub, '_casa_access_mode', 'external' );
update_post_meta( $pub, '_casa_external_url', 'https://www.passline.com/cine' );
t_set_terms( $pub, array( 4 ) );
sync_event( $pub );
t_ok( ! is_wp_error( t_rest( $pub, array(), array( 'meta' => array( '_casa_featured' => true ) ) ) ), 'an update without status is validated as published and passes' );
$r = t_rest( $pub, array(), array( 'meta' => array( '_casa_external_url' => '' ) ) );
t_ok( is_wp_error( $r ) && isset( $r->get_error_data()['errors']['external_url_required'] ), 'clearing the external URL of a published external event is refused' );
t_ok( ! is_wp_error( t_rest( $pub, array( 'post_status' => 'draft' ), array( 'meta' => array( '_casa_external_url' => '' ) ) ) ), 'switching to draft allows incomplete data' );

t_group( 'rest/cancel and reactivate' );
t_ok( ! is_wp_error( t_rest( $pub, array(), array( 'meta' => array( '_casa_status' => 'cancelled' ) ) ) ), 'an editor can cancel' );
update_post_meta( $pub, '_casa_status', 'cancelled' );
sync_event( $pub );
$r = t_rest( $pub, array(), array( 'meta' => array( '_casa_status' => 'active' ) ) );
t_ok( is_wp_error( $r ) && isset( $r->get_error_data()['errors']['reactivate_forbidden'] ), 'an editor cannot reactivate' );
t_ok( ! is_wp_error( t_rest( $pub, array(), array( 'meta' => array( '_casa_featured' => false ) ) ) ), 'an editor can still edit a cancelled event' );
$GLOBALS['t_caps']['manage_options'] = true;
t_ok( ! is_wp_error( t_rest( $pub, array(), array( 'meta' => array( '_casa_status' => 'active' ) ) ) ), 'an administrator can reactivate' );
$GLOBALS['t_caps']['manage_options'] = false;
add_filter( 'casa_eventos/reactivate_capability', static fn() => 'edit_post' );
t_ok( ! is_wp_error( t_rest( $pub, array(), array( 'meta' => array( '_casa_status' => 'paused' ) ) ) ), 'the capability is filterable' );
remove_all_filters( 'casa_eventos/reactivate_capability' );

t_group( 'admin/quick edit' );
$GLOBALS['t_is_admin'] = true;
$quick                 = t_add_post( array( 'post_status' => 'draft', 'post_title' => 'Borrador' ) );
sync_event( $quick );
$data = array(
	'post_type'   => 'casa_evento',
	'post_status' => 'publish',
	'post_title'  => 'Borrador',
);
$blocked = false;
try {
	guard_admin_publish( $data, array( 'ID' => $quick ) );
} catch ( RuntimeException $e ) {
	$blocked = 0 === strpos( $e->getMessage(), 'No se puede publicar el evento:' );
}
t_ok( $blocked, 'Quick Edit cannot publish an incomplete event' );
update_post_meta( $quick, '_casa_start', '2026-09-05 21:00:00' );
update_post_meta( $quick, '_casa_access_mode', 'whatsapp' );
t_eq( $data, guard_admin_publish( $data, array( 'ID' => $quick, 'tax_input' => array( 'casa_categoria' => array( 0, 4 ) ) ) ), 'with complete data and one category it passes' );
$data['post_status'] = 'draft';
t_eq( $data, guard_admin_publish( $data, array( 'ID' => $quick ) ), 'drafts are not checked' );
$GLOBALS['t_is_admin'] = false;
$data['post_status']   = 'publish';
t_eq( $data, guard_admin_publish( $data, array( 'ID' => $quick ) ), 'programmatic saves are not blocked' );

t_group( 'queries/admin list vars' );
$vars = Queries::admin_list_query_vars( array( 'view' => 'upcoming' ) );
t_eq( array( 'start_order' => 'ASC', 'ID' => 'ASC' ), $vars['orderby'], 'upcoming: nearest first' );
t_eq( '_casa_ends_gmt', $vars['meta_query'][0]['key'], 'upcoming: effective end after now' );
t_eq( '>', $vars['meta_query'][0]['compare'], 'strictly after now' );
t_eq( 'cancelled', $vars['meta_query'][1]['value'], 'upcoming excludes cancelled' );
$vars = Queries::admin_list_query_vars( array() );
t_eq( array( 'start_order' => 'DESC', 'ID' => 'DESC' ), $vars['orderby'], 'all events: newest first' );
t_eq( 'EXISTS', $vars['meta_query']['start_order']['compare'], 'ordering keeps events without a date' );
$vars = Queries::admin_list_query_vars( array( 'orderby' => 'other' ) );
t_ok( ! isset( $vars['orderby'] ), 'other orderings are left to WordPress' );
$vars  = Queries::admin_list_query_vars( array( 'month' => '2026-12' ) );
$range = $vars['meta_query'][0];
t_eq( array( '2026-12-01 00:00:00', '2027-01-01 00:00:00' ), array( $range[0]['value'], $range[1]['value'] ), 'month filter uses the local start' );
$vars = Queries::admin_list_query_vars( array( 'month' => '2026-13' ) );
t_ok( ! isset( $vars['meta_query'][0] ), 'invalid month ignored' );
t_eq( array(), Queries::admin_view_meta_query( 'unknown' ), 'unknown view' );
t_eq( 'paused', Queries::admin_view_meta_query( 'paused' )[0]['value'], 'paused view' );
t_eq( 'BETWEEN', Queries::admin_view_meta_query( 'past' )[0]['compare'], 'past view: ended, date present' );

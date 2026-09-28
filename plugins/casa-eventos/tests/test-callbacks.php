<?php
/**
 * Registered callbacks must survive WordPress's calling convention.
 *
 * Regression (runtime QA, report 18): term meta _casa_order used 'intval'
 * as sanitize_callback. sanitize_meta() passes four arguments, and PHP 8
 * internal functions throw ArgumentCountError on extra arguments, so
 * creating any category was a fatal error.
 *
 * @package CasaEventos
 */

use function CasaEventos\Core\editorial_meta_definitions;
use function CasaEventos\Core\system_meta_definitions;
use function CasaEventos\Core\term_meta_definitions;

t_group( 'callbacks/sanitize' );

$callbacks = array();
foreach ( editorial_meta_definitions() as $key => $def ) {
	$callbacks[ $key ] = array( $def['sanitize'], 'post', 'casa_evento' );
}
foreach ( system_meta_definitions() as $key => $def ) {
	$callbacks[ $key ] = array( $def[1], 'post', 'casa_evento' );
}
foreach ( term_meta_definitions() as $key => $def ) {
	$callbacks[ $key ] = array( $def['sanitize'], 'term', 'casa_categoria' );
}

foreach ( $callbacks as $key => list( $callback, $type, $subtype ) ) {
	t_ok( is_callable( $callback ), "$key: callable" );
	t_ok( ! ( new ReflectionFunction( $callback ) )->isInternal(), "$key: not a PHP internal function" );
	foreach ( array( '', '7', 7, true, null, '2026-09-05 21:00' ) as $value ) {
		try {
			// Exactly how sanitize_meta() calls it: value, key, object type, subtype.
			call_user_func_array( $callback, array( $value, $key, $type, $subtype ) );
			$ok = true;
		} catch ( Throwable $e ) {
			$ok = false;
		}
		t_ok( $ok, "$key: accepts sanitize_meta()'s four arguments (" . var_export( $value, true ) . ')' );
	}
}

t_eq( 3, CasaEventos\Core\sanitize_order( '3' ), 'order from a numeric string' );
t_eq( 0, CasaEventos\Core\sanitize_order( 'x' ), 'non-numeric order → 0' );
t_eq( -2, CasaEventos\Core\sanitize_order( -2 ), 'negative order kept' );

t_group( 'meta/revisions (runtime QA regression, report 18)' );
// Restoring a revision must never change the operational status: an editor
// could otherwise reactivate a cancelled event (admin-only) through
// Revisions → Restore, which bypasses the REST validation.
$revisioned = array();
foreach ( editorial_meta_definitions() as $key => $def ) {
	if ( $def['revisions'] ?? true ) {
		$revisioned[] = $key;
	}
}
sort( $revisioned );
t_eq(
	array( '_casa_access_mode', '_casa_capacity', '_casa_end', '_casa_entry_kind', '_casa_entry_label', '_casa_external_url', '_casa_featured', '_casa_listed', '_casa_sales_close', '_casa_start' ),
	$revisioned,
	'revisioned keys: editorial content only (no _casa_status, no identity/derived keys)'
);

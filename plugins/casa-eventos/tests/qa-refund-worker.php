<?php
/** Child of qa-refunds.php; clone-only synthetic evidence. */
$load = getenv( 'LCDA_QA_WP_LOAD' );
$barrier = getenv( 'LCDA_T6_BARRIER' );
if ( ! $load || false === strpos( str_replace( '\\', '/', $load ), 'lcda-e32-qa-20261009/' )
	|| ! $barrier || ! getenv( 'LCDA_T6_ORDER' ) || ! getenv( 'LCDA_T6_KEY' ) ) { exit( 2 ); }
require $load;
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
if ( ! $admin ) { exit( 3 ); }
wp_set_current_user( $admin->ID );
add_filter( 'pre_wp_mail', static fn() => true );
add_filter( 'casa_eventos/verify_refund_evidence', static function ( $pre, $evidence, $context ) {
	return 'T6-QA-ONLY' === ( $evidence['fixture'] ?? '' )
		? array_merge( $context, array( 'verified' => true, 'reference' => (string) $evidence['reference'] ) ) : null;
}, 10, 3 );
$deadline = microtime( true ) + 15;
while ( ! is_file( $barrier ) && microtime( true ) < $deadline ) { usleep( 10000 ); }
if ( ! is_file( $barrier ) ) { exit( 4 ); }
$result = \CasaEventos\Commerce\confirm_ticket_refund( (int) getenv( 'LCDA_T6_ORDER' ), getenv( 'LCDA_T6_KEY' ),
	array( 'fixture' => 'T6-QA-ONLY', 'reference' => getenv( 'LCDA_T6_REFERENCE' ) ) );
echo wp_json_encode( is_wp_error( $result )
	? array( 'error' => $result->get_error_code() )
	: array( 'status' => $result['status'], 'replayed' => $result['replayed'] ) );

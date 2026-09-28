<?php
/**
 * Static test runner (no WordPress, no database, no Composer).
 *
 *     php plugins/casa-eventos/tests/run.php
 *
 * @package CasaEventos
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require __DIR__ . '/bootstrap.php';

$files = glob( __DIR__ . '/test-*.php' );
sort( $files );
foreach ( $files as $file ) {
	require $file;
}

$fail = $GLOBALS['t_fail'];
foreach ( $fail as $message ) {
	fwrite( STDERR, "FAIL  {$message}\n" );
}
printf( "%d passed, %d failed (%d files)\n", $GLOBALS['t_pass'], count( $fail ), count( $files ) );
exit( $fail ? 1 : 0 );

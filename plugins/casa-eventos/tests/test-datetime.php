<?php
/**
 * Datetime rules.
 *
 * @package CasaEventos
 */

use function CasaEventos\Core\add_minutes_gmt;
use function CasaEventos\Core\current_month_in;
use function CasaEventos\Core\derive_gmt;
use function CasaEventos\Core\gmt_to_local;
use function CasaEventos\Core\local_to_gmt;
use function CasaEventos\Core\month_bounds;
use function CasaEventos\Core\now_gmt;
use function CasaEventos\Core\parse_local;
use function CasaEventos\Core\parse_month;
use function CasaEventos\Core\sales_close_gmt;
use function CasaEventos\Core\shift_month;
use function CasaEventos\Core\timezone_for_new_event;

$ba = 'America/Argentina/Buenos_Aires';

t_group( 'datetime/parse_local' );
t_eq( '2026-09-05 21:00:00', parse_local( '2026-09-05 21:00' ), 'minutes precision' );
t_eq( '2026-09-05 21:00:00', parse_local( '2026-09-05T21:00' ), 'datetime-local input' );
t_eq( '2026-09-05 21:00:30', parse_local( '2026-09-05 21:00:30' ), 'seconds kept' );
t_eq( '2026-09-05 21:00:00', parse_local( ' 2026-09-05T21:00 ' ), 'trimmed' );
t_eq( '', parse_local( '' ), 'empty' );
t_eq( '', parse_local( null ), 'null' );
t_eq( null, parse_local( '2026-02-30 10:00' ), 'impossible date rejected (no rollover)' );
t_eq( null, parse_local( '2026-09-05 24:00' ), 'hour 24 rejected' );
t_eq( null, parse_local( '2026-09-05' ), 'date without time rejected' );
t_eq( null, parse_local( 'mañana' ), 'text rejected' );
t_eq( null, parse_local( array( '2026-09-05 21:00' ) ), 'array rejected' );
t_eq( '2028-02-29 20:00:00', parse_local( '2028-02-29 20:00' ), 'leap day accepted' );

t_group( 'datetime/conversion' );
t_eq( '2026-09-06 00:00:00', local_to_gmt( '2026-09-05 21:00:00', $ba ), 'Buenos Aires is UTC−3' );
t_eq( '2026-09-05 21:00:00', gmt_to_local( '2026-09-06 00:00:00', $ba ), 'round trip' );
t_eq( '2026-09-06 03:30:00', local_to_gmt( '2026-09-06 00:30:00', $ba ), 'Sunday 00:30 stays Sunday locally' );
t_eq( '2026-10-01 01:00:00', local_to_gmt( '2026-09-30 22:00:00', $ba ), 'late 30 Sept crosses the GMT month' );
t_eq( '2026-07-01 19:00:00', local_to_gmt( '2026-07-01 21:00:00', 'Europe/Madrid' ), 'DST zone in summer (+2)' );
t_eq( '2026-01-15 20:00:00', local_to_gmt( '2026-01-15 21:00:00', 'Europe/Madrid' ), 'DST zone in winter (+1)' );
t_eq( '', local_to_gmt( '', $ba ), 'empty stays empty' );
t_eq( '', local_to_gmt( 'garbage', $ba ), 'invalid gives empty' );
t_eq( '2026-09-06 00:00:00', local_to_gmt( '2026-09-05 21:00:00', 'Not/AZone' ), 'unknown zone falls back to the venue zone' );
t_eq( '2026-09-05 23:00:00', add_minutes_gmt( '2026-09-06 00:00:00', -60 ), 'subtract minutes' );
t_eq( '2026-09-06 03:00:00', add_minutes_gmt( '2026-09-06 00:00:00', 180 ), 'add minutes' );

t_group( 'datetime/derive_gmt' );
$d = derive_gmt( '2026-09-05 21:00:00', '', $ba, 180 );
t_eq( array( 'start_gmt' => '2026-09-06 00:00:00', 'end_gmt' => '', 'ends_gmt' => '2026-09-06 03:00:00' ), $d, 'no end: effective end = start + 3h' );
$d = derive_gmt( '2026-09-05 21:00:00', '2026-09-05 23:30:00', $ba, 180 );
t_eq( '2026-09-06 02:30:00', $d['ends_gmt'], 'declared end is the effective end' );
t_eq( '2026-09-06 02:30:00', $d['end_gmt'], 'declared end in GMT' );
$d = derive_gmt( '2026-09-05 21:00:00', '2026-09-05 21:00:00', $ba, 180 );
t_eq( '', $d['end_gmt'], 'end equal to start is ignored' );
t_eq( '2026-09-06 03:00:00', $d['ends_gmt'], 'and the default duration applies' );
$d = derive_gmt( '2026-09-05 21:00:00', '2026-09-05 20:00:00', $ba, 180 );
t_eq( '', $d['end_gmt'], 'end before start is ignored' );
$d = derive_gmt( '', '2026-09-05 23:00:00', $ba, 180 );
t_eq( array( 'start_gmt' => '', 'end_gmt' => '', 'ends_gmt' => '' ), $d, 'no start: nothing derived' );
$d = derive_gmt( '2026-09-30 20:00:00', '2026-10-02 02:00:00', $ba, 180 );
t_eq( '2026-10-02 05:00:00', $d['ends_gmt'], 'multi-day event ends on its declared end' );

t_group( 'datetime/sales_close' );
t_eq( '2026-09-05 23:00:00', sales_close_gmt( '', '2026-09-06 00:00:00', $ba, 60 ), 'default: start − 60 min' );
t_eq( '2026-09-06 01:00:00', sales_close_gmt( '2026-09-05 22:00:00', '2026-09-06 00:00:00', $ba, 60 ), 'explicit cutoff after start is kept' );
t_eq( '', sales_close_gmt( '', '', $ba, 60 ), 'no start: no cutoff' );

t_group( 'datetime/months' );
t_eq( '2026-09', parse_month( '2026-09' ), 'valid month' );
t_eq( null, parse_month( '2026-13' ), 'month 13 rejected' );
t_eq( null, parse_month( '2026-9' ), 'non-padded rejected' );
t_eq( null, parse_month( array() ), 'non-string rejected' );
t_eq( array( '2026-12-01 00:00:00', '2027-01-01 00:00:00' ), month_bounds( '2026-12' ), 'December bounds cross the year' );
t_eq( '2025-12', shift_month( '2026-01', -1 ), 'previous month across the year' );
t_eq( '2027-09', shift_month( '2026-09', 12 ), 'twelve months ahead' );
t_eq( '2026-09', current_month_in( $ba, gmmktime( 2, 0, 0, 10, 1, 2026 ) ), 'venue month when GMT is already October' );
$bounds = month_bounds( '2026-09' );
$start  = '2026-09-30 23:30:00';
t_ok( $start >= $bounds[0] && $start < $bounds[1], 'an event starting 30 Sept 23:30 local belongs to September' );
$start = '2026-10-01 00:30:00';
t_ok( ! ( $start >= $bounds[0] && $start < $bounds[1] ), 'an event starting 1 Oct 00:30 local belongs to October' );

t_group( 'datetime/now_and_timezone' );
t_eq( '1970-01-01 00:00:00', now_gmt( 0 ), 'now is UTC whatever the PHP default timezone' );
t_eq( '2026-09-05 21:13:00', now_gmt( gmmktime( 21, 13, 47, 9, 5, 2026 ), true ), 'rounded down to the minute' );
t_eq( 'America/Argentina/Buenos_Aires', timezone_for_new_event(), 'named site timezone is captured' );
$GLOBALS['t_options']['timezone_string'] = '';
t_eq( 'America/Argentina/Buenos_Aires', timezone_for_new_event(), 'manual offset: venue timezone fallback' );
$GLOBALS['t_options']['timezone_string'] = 'Europe/Madrid';
t_eq( 'Europe/Madrid', timezone_for_new_event(), 'another named timezone is respected' );
$GLOBALS['t_options']['timezone_string'] = 'America/Argentina/Buenos_Aires';

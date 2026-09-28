<?php
/**
 * Datetime rules.
 *
 * Authoritative values are local wall times ('Y-m-d H:i:s') in the event's
 * IANA timezone. GMT copies ('Y-m-d H:i:s', UTC) are derived from them for
 * comparisons and scheduling. Fixed-width strings sort chronologically, so
 * queries compare them as strings.
 *
 * Nothing here uses PHP's default timezone: every DateTimeImmutable gets an
 * explicit zone, "now" comes from time() and is formatted with gmdate().
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DATETIME_FORMAT = 'Y-m-d H:i:s';

/**
 * Normalize a local datetime from the editor or the database.
 *
 * Accepts 'Y-m-d H:i', 'Y-m-d H:i:s' and the same with a 'T' separator
 * (the datetime-local input format). Calendar validity is checked, so
 * 2026-02-30 is rejected instead of silently rolling over.
 *
 * @param mixed $value Raw value.
 * @return string|null Canonical 'Y-m-d H:i:s', '' for an empty value, null when invalid.
 */
function parse_local( $value ) {
	if ( null === $value ) {
		return '';
	}
	if ( ! is_string( $value ) ) {
		return null;
	}

	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}

	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $m ) ) {
		return null;
	}

	$year   = (int) $m[1];
	$month  = (int) $m[2];
	$day    = (int) $m[3];
	$hour   = (int) $m[4];
	$minute = (int) $m[5];
	$second = isset( $m[6] ) ? (int) $m[6] : 0;

	if ( $year < 1970 || ! checkdate( $month, $day, $year ) || $hour > 23 || $minute > 59 || $second > 59 ) {
		return null;
	}

	return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second );
}

/**
 * Whether a string is a known IANA timezone identifier.
 *
 * @param mixed $tz Timezone identifier.
 * @return bool
 */
function is_timezone_identifier( $tz ) {
	if ( ! is_string( $tz ) || '' === $tz ) {
		return false;
	}
	return in_array( $tz, timezone_identifiers_list( DateTimeZone::ALL_WITH_BC ), true );
}

/**
 * The site's named timezone, or '' when the site uses a manual UTC offset.
 *
 * @return string
 */
function site_named_timezone() {
	$tz = get_option( 'timezone_string' );
	return is_timezone_identifier( $tz ) ? $tz : '';
}

/**
 * Timezone captured for a new event: the site's named timezone, or the
 * venue timezone when the site only has an offset.
 *
 * @return string IANA identifier.
 */
function timezone_for_new_event() {
	$tz = site_named_timezone();
	if ( '' === $tz ) {
		$tz = (string) apply_filters( 'casa_eventos/fallback_timezone', FALLBACK_TIMEZONE );
	}
	return is_timezone_identifier( $tz ) ? $tz : FALLBACK_TIMEZONE;
}

/**
 * DateTimeZone for an identifier, falling back to the venue timezone.
 *
 * @param string $tz IANA identifier.
 * @return DateTimeZone
 */
function timezone_object( $tz ) {
	return new DateTimeZone( is_timezone_identifier( $tz ) ? $tz : FALLBACK_TIMEZONE );
}

/**
 * Local wall time as a DateTimeImmutable in its timezone.
 *
 * @param string $local Canonical local datetime.
 * @param string $tz    IANA identifier.
 * @return DateTimeImmutable|null
 */
function local_datetime( $local, $tz ) {
	$local = parse_local( $local );
	if ( null === $local || '' === $local ) {
		return null;
	}
	$dt = DateTimeImmutable::createFromFormat( '!' . DATETIME_FORMAT, $local, timezone_object( $tz ) );
	return $dt ? $dt : null;
}

/**
 * GMT datetime as a DateTimeImmutable in UTC.
 *
 * @param string $gmt 'Y-m-d H:i:s' in UTC.
 * @return DateTimeImmutable|null
 */
function gmt_datetime( $gmt ) {
	if ( ! is_string( $gmt ) || '' === $gmt ) {
		return null;
	}
	$dt = DateTimeImmutable::createFromFormat( '!' . DATETIME_FORMAT, $gmt, new DateTimeZone( 'UTC' ) );
	return $dt ? $dt : null;
}

/**
 * Convert a local wall time to GMT.
 *
 * A wall time inside a DST gap (not the case for Buenos Aires today) is
 * resolved by PHP to the next valid instant.
 *
 * @param string $local Local datetime.
 * @param string $tz    IANA identifier.
 * @return string GMT 'Y-m-d H:i:s', or '' when the input is empty/invalid.
 */
function local_to_gmt( $local, $tz ) {
	$dt = local_datetime( $local, $tz );
	return $dt ? $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( DATETIME_FORMAT ) : '';
}

/**
 * Convert a GMT datetime to local wall time.
 *
 * @param string $gmt GMT datetime.
 * @param string $tz  IANA identifier.
 * @return string Local 'Y-m-d H:i:s', or ''.
 */
function gmt_to_local( $gmt, $tz ) {
	$dt = gmt_datetime( $gmt );
	return $dt ? $dt->setTimezone( timezone_object( $tz ) )->format( DATETIME_FORMAT ) : '';
}

/**
 * Add minutes to a GMT datetime (negative to subtract).
 *
 * @param string $gmt     GMT datetime.
 * @param int    $minutes Minutes.
 * @return string GMT datetime, or ''.
 */
function add_minutes_gmt( $gmt, $minutes ) {
	$dt = gmt_datetime( $gmt );
	if ( ! $dt ) {
		return '';
	}
	$minutes = (int) $minutes;
	return $dt->modify( sprintf( '%+d minutes', $minutes ) )->format( DATETIME_FORMAT );
}

/**
 * Current instant in GMT.
 *
 * @param int|null $timestamp    Unix timestamp; defaults to time().
 * @param bool     $to_minute    Round down to the minute (for cacheable queries).
 * @return string
 */
function now_gmt( $timestamp = null, $to_minute = false ) {
	$timestamp = null === $timestamp ? time() : (int) $timestamp;
	return gmdate( $to_minute ? 'Y-m-d H:i:00' : DATETIME_FORMAT, $timestamp );
}

/**
 * Derived GMT fields from the editorial inputs.
 *
 * The effective end is the declared end when it is after the start,
 * otherwise start + default duration. Without a valid start everything is ''.
 *
 * @param string $start_local Local start.
 * @param string $end_local   Local end ('' when none).
 * @param string $tz          IANA identifier.
 * @param int    $duration    Default duration in minutes.
 * @return array{start_gmt:string,end_gmt:string,ends_gmt:string}
 */
function derive_gmt( $start_local, $end_local, $tz, $duration ) {
	$start_gmt = local_to_gmt( $start_local, $tz );
	$end_gmt   = '' === $start_gmt ? '' : local_to_gmt( $end_local, $tz );

	if ( '' !== $end_gmt && $end_gmt <= $start_gmt ) {
		$end_gmt = '';
	}

	if ( '' === $start_gmt ) {
		$ends_gmt = '';
	} elseif ( '' !== $end_gmt ) {
		$ends_gmt = $end_gmt;
	} else {
		$ends_gmt = add_minutes_gmt( $start_gmt, $duration );
	}

	return array(
		'start_gmt' => $start_gmt,
		'end_gmt'   => $end_gmt,
		'ends_gmt'  => $ends_gmt,
	);
}

/**
 * Effective sales cutoff in GMT.
 *
 * @param string $sales_close_local Explicit local cutoff ('' for the default).
 * @param string $start_gmt         Derived start.
 * @param string $tz                IANA identifier.
 * @param int    $offset            Default offset before the start, minutes.
 * @return string GMT datetime, or '' without a start.
 */
function sales_close_gmt( $sales_close_local, $start_gmt, $tz, $offset ) {
	if ( '' === (string) $start_gmt ) {
		return '';
	}
	$explicit = local_to_gmt( $sales_close_local, $tz );
	return '' !== $explicit ? $explicit : add_minutes_gmt( $start_gmt, -1 * (int) $offset );
}

/**
 * Validate a 'YYYY-MM' month key.
 *
 * @param mixed $ym Month key.
 * @return string|null Canonical 'YYYY-MM' or null.
 */
function parse_month( $ym ) {
	if ( ! is_string( $ym ) || ! preg_match( '/^(\d{4})-(\d{2})$/', $ym, $m ) ) {
		return null;
	}
	$month = (int) $m[2];
	if ( (int) $m[1] < 1970 || $month < 1 || $month > 12 ) {
		return null;
	}
	return sprintf( '%04d-%02d', (int) $m[1], $month );
}

/**
 * Local bounds of a month: [first day 00:00:00, first day of next month).
 *
 * @param string $ym 'YYYY-MM'.
 * @return array{0:string,1:string}|null
 */
function month_bounds( $ym ) {
	$ym = parse_month( $ym );
	if ( null === $ym ) {
		return null;
	}
	return array( $ym . '-01 00:00:00', shift_month( $ym, 1 ) . '-01 00:00:00' );
}

/**
 * Move a month key by N months.
 *
 * @param string $ym    'YYYY-MM'.
 * @param int    $delta Months.
 * @return string|null
 */
function shift_month( $ym, $delta ) {
	$ym = parse_month( $ym );
	if ( null === $ym ) {
		return null;
	}
	$index = (int) substr( $ym, 0, 4 ) * 12 + (int) substr( $ym, 5, 2 ) - 1 + (int) $delta;
	return sprintf( '%04d-%02d', intdiv( $index, 12 ), $index % 12 + 1 );
}

/**
 * The current month in a timezone.
 *
 * @param string   $tz        IANA identifier.
 * @param int|null $timestamp Unix timestamp; defaults to time().
 * @return string 'YYYY-MM'.
 */
function current_month_in( $tz, $timestamp = null ) {
	$timestamp = null === $timestamp ? time() : (int) $timestamp;
	return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( timezone_object( $tz ) )->format( 'Y-m' );
}

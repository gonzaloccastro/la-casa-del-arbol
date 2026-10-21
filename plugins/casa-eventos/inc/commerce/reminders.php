<?php
/** T8: one reminder per verified order, backed by an atomic outbox. */
namespace CasaEventos\Commerce;

use CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

const REMINDER_SCHEMA = 1;
const REMINDER_SCHEMA_OPTION = 'lcda_reminder_schema';
const REMINDER_HOOK = 'casa_eventos/send_ticket_reminder';
const REMINDER_MAX_ATTEMPTS = 3;

function reminder_table() { global $wpdb; return $wpdb->prefix . 'casa_ticket_reminders'; }

function install_reminder_table() {
	global $wpdb;
	if ( ! function_exists( 'dbDelta' ) ) { require_once ABSPATH . 'wp-admin/includes/upgrade.php'; }
	$table = reminder_table();
	$sql = "CREATE TABLE $table (
		order_id bigint(20) unsigned NOT NULL,
		event_id bigint(20) unsigned NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		attempts smallint(5) unsigned NOT NULL DEFAULT 0,
		due_at bigint(20) unsigned NOT NULL DEFAULT 0,
		lease_until bigint(20) unsigned NOT NULL DEFAULT 0,
		last_error varchar(100) NOT NULL DEFAULT '',
		updated_gmt datetime NOT NULL,
		PRIMARY KEY  (order_id),
		KEY event_status (event_id,status),
		KEY status_due (status,due_at)
	) " . $wpdb->get_charset_collate() . ';';
	dbDelta( $sql );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
	update_option( REMINDER_SCHEMA_OPTION, REMINDER_SCHEMA, false );
	return true;
}
function maybe_install_reminder_table() {
	if ( (int) get_option( REMINDER_SCHEMA_OPTION, 0 ) !== REMINDER_SCHEMA ) { install_reminder_table(); }
}
add_action( 'init', __NAMESPACE__ . '\maybe_install_reminder_table', 3 );

/** Unix seconds: subtract exactly 24 elapsed hours, across DST boundaries. */
function reminder_due_for_event( $event_id, $now = null ) {
	$now = null === $now ? time() : (int) $now;
	$event = Core\Event::get( (int) $event_id );
	if ( ! $event || ! $event->is_published() || $event->is_cancelled() || $event->is_paused() ) { return 0; }
	$start = Core\gmt_datetime( $event->start_gmt() );
	if ( ! $start || $start->getTimestamp() <= $now ) { return 0; }
	return max( $now + 5, $start->getTimestamp() - DAY_IN_SECONDS );
}

function clear_reminder_job( $order_id ) {
	wp_clear_scheduled_hook( REMINDER_HOOK, array( (int) $order_id ) );
}
function schedule_reminder_job( $order_id, $due ) {
	$order_id = (int) $order_id;
	$due = (int) $due;
	$existing = wp_next_scheduled( REMINDER_HOOK, array( $order_id ) );
	if ( $existing && (int) $existing === $due ) { return true; }
	clear_reminder_job( $order_id );
	$result = wp_schedule_single_event( $due, REMINDER_HOOK, array( $order_id ), true );
	return false !== $result && ! is_wp_error( $result );
}

/** Called only after T5 has verified payment and completed ticket issuance. */
function queue_ticket_reminder( $order_id, $event_id ) {
	global $wpdb;
	$order_id = (int) $order_id;
	$event_id = (int) $event_id;
	if ( ! $order_id || ! $event_id || (int) get_option( REMINDER_SCHEMA_OPTION, 0 ) !== REMINDER_SCHEMA ) { return false; }
	$due = reminder_due_for_event( $event_id );
	if ( ! $due ) { return false; }
	$table = reminder_table();
	$inserted = $wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO $table (order_id,event_id,status,attempts,due_at,lease_until,last_error,updated_gmt) VALUES (%d,%d,'pending',0,%d,0,'',%s)",
		$order_id, $event_id, $due, gmdate( 'Y-m-d H:i:s' )
	) );
	if ( false === $inserted ) { return false; }
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT event_id,status,due_at FROM $table WHERE order_id = %d", $order_id ) );
	if ( ! $row || (int) $row->event_id !== $event_id || ! in_array( $row->status, array( 'pending', 'retry' ), true ) ) { return false; }
	return schedule_reminder_job( $order_id, (int) $row->due_at );
}

/** Reconcile queued jobs after rescheduling, cancellation, or reactivation. */
function reconcile_event_reminders( $event_id ) {
	global $wpdb;
	if ( (int) get_option( REMINDER_SCHEMA_OPTION, 0 ) !== REMINDER_SCHEMA ) { return; }
	$table = reminder_table();
	$due = reminder_due_for_event( $event_id );
	$last_id = 0;
	do {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT order_id,status FROM $table WHERE event_id = %d AND order_id > %d ORDER BY order_id LIMIT 100",
			$event_id, $last_id
		) );
		foreach ( (array) $rows as $row ) {
			$order_id = (int) $row->order_id;
			$last_id = $order_id;
			if ( 'sent' === $row->status || 'sending' === $row->status || 'uncertain' === $row->status || 'failed' === $row->status || 'blocked' === $row->status ) { continue; }
			if ( ! $due ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'cancelled', due_at = 0, lease_until = 0, updated_gmt = %s WHERE order_id = %d AND status IN ('pending','retry','cancelled')", gmdate( 'Y-m-d H:i:s' ), $order_id ) );
				clear_reminder_job( $order_id );
				continue;
			}
			$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'pending', due_at = %d, lease_until = 0, last_error = '', updated_gmt = %s WHERE order_id = %d AND status IN ('pending','retry','cancelled')", $due, gmdate( 'Y-m-d H:i:s' ), $order_id ) );
			schedule_reminder_job( $order_id, $due );
		}
	} while ( is_array( $rows ) && count( $rows ) === 100 );
}
add_action( 'casa_eventos/schedule_changed', __NAMESPACE__ . '\reconcile_event_reminders', 10, 1 );
add_action( 'casa_eventos/event_cancelled', __NAMESPACE__ . '\reconcile_event_reminders', 10, 1 );
add_action( 'casa_eventos/event_reactivated', __NAMESPACE__ . '\reconcile_event_reminders', 10, 1 );
add_action( 'casa_eventos/event_synced', __NAMESPACE__ . '\reconcile_event_reminders', 10, 1 );

/** Recheck every issued unit and the T2 payment ledger immediately before mail. */
function reminder_data( $order_id, $now = null ) {
	global $wpdb;
	$now = null === $now ? time() : (int) $now;
	$source = paid_ticket_source( $order_id );
	if ( is_wp_error( $source ) ) { return $source; }
	$event = Core\Event::get( (int) $source['event_id'] );
	if ( ! $event || ! $event->is_published() || $event->is_cancelled() || $event->is_paused() ) { return error( 'reminder_event', 'El evento no admite recordatorios.' ); }
	$start = Core\gmt_datetime( $event->start_gmt() );
	if ( ! $start || $start->getTimestamp() <= $now ) { return error( 'reminder_time', 'El evento ya comenzo.' ); }
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT event_id,reservation_id,order_item_id,ticket_type_id,unit_number,status,ticket_code FROM ' . tickets_table() . ' WHERE order_id = %d',
		$order_id
	) );
	$expected = array();
	foreach ( $source['items'] as $item ) {
		for ( $unit = 1; $unit <= (int) $item->get_quantity(); ++$unit ) {
			$expected[ $item->get_id() . ':' . $unit ] = (int) $item->get_variation_id();
		}
	}
	if ( ! is_array( $rows ) || count( $rows ) !== count( $expected ) || ! $rows ) { return error( 'reminder_tickets', 'Las entradas no estan completas.' ); }
	$valid_units = 0;
	foreach ( $rows as $row ) {
		$key = $row->order_item_id . ':' . $row->unit_number;
		if ( ! isset( $expected[ $key ] ) || $expected[ $key ] !== (int) $row->ticket_type_id
			|| (int) $row->event_id !== (int) $source['event_id'] || (int) $row->reservation_id !== (int) $source['row']->id
			|| ! in_array( $row->status, array( 'issued', 'revoked' ), true )
			|| ! preg_match( '/^[a-f0-9]{64}$/D', (string) $row->ticket_code ) ) {
			return error( 'reminder_tickets', 'Una entrada ya no es valida.' );
		}
		if ( 'issued' === $row->status ) { ++$valid_units; }
		unset( $expected[ $key ] );
	}
	if ( $expected || ! $valid_units ) { return error( 'reminder_tickets', 'Faltan entradas vigentes.' ); }
	$data = ticket_confirmation_data( $order_id );
	if ( is_wp_error( $data ) ) { return $data; }
	$data['event'] = array(
		'title' => $event->title(), 'start_local' => $event->start_local(), 'timezone' => $event->timezone(),
		'venue' => $event->venue_name(), 'venue_address' => $event->venue_address(),
	);
	return $data;
}

function ticket_reminder_html( $data ) {
	ob_start();
	require __DIR__ . '/../../templates/emails/ticket-reminder.php';
	return ob_get_clean();
}

function reminder_finish( $order_id, $status, $error_code = '', $next = 0 ) {
	global $wpdb;
	$table = reminder_table();
	$updated = $wpdb->query( $wpdb->prepare(
		"UPDATE $table SET status = %s, due_at = %d, lease_until = 0, last_error = %s, updated_gmt = %s WHERE order_id = %d AND status = 'sending'",
		$status, $next, $error_code, gmdate( 'Y-m-d H:i:s' ), $order_id
	) );
	if ( 1 !== $updated ) { return; } // Keep the watchdog: an uncertain outcome must never be replayed blindly.
	clear_reminder_job( $order_id );
	if ( 'retry' === $status && ! schedule_reminder_job( $order_id, $next ) ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'blocked', last_error = 'scheduler_failed', updated_gmt = %s WHERE order_id = %d AND status = 'retry'", gmdate( 'Y-m-d H:i:s' ), $order_id ) );
	}
}

function send_ticket_reminder( $order_id ) {
	global $wpdb;
	$order_id = (int) $order_id;
	if ( $order_id <= 0 || (int) get_option( REMINDER_SCHEMA_OPTION, 0 ) !== REMINDER_SCHEMA ) { return false; }
	$table = reminder_table();
	$now = time();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT event_id,status,due_at,lease_until,attempts FROM $table WHERE order_id = %d", $order_id ) );
	if ( ! $row ) { return false; }
	if ( 'sending' === $row->status && (int) $row->lease_until <= $now ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'uncertain', last_error = 'interrupted', updated_gmt = %s WHERE order_id = %d AND status = 'sending' AND lease_until <= %d", gmdate( 'Y-m-d H:i:s' ), $order_id, $now ) );
		clear_reminder_job( $order_id );
		return false;
	}
	$due = reminder_due_for_event( (int) $row->event_id, $now );
	if ( ! $due ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'cancelled', due_at = 0, updated_gmt = %s WHERE order_id = %d AND status IN ('pending','retry')", gmdate( 'Y-m-d H:i:s' ), $order_id ) );
		clear_reminder_job( $order_id );
		return false;
	}
	if ( (int) $row->due_at !== $due && $due > $now + 5 ) {
		reconcile_event_reminders( (int) $row->event_id );
		return false;
	}
	$lease = $now + 300;
	$claimed = $wpdb->query( $wpdb->prepare(
		"UPDATE $table SET status = 'sending', attempts = attempts + 1, lease_until = %d, updated_gmt = %s WHERE order_id = %d AND status IN ('pending','retry') AND due_at <= %d AND attempts < %d",
		$lease, gmdate( 'Y-m-d H:i:s' ), $order_id, $now, REMINDER_MAX_ATTEMPTS
	) );
	if ( 1 !== $claimed ) { return false; }
	// A crashed worker is marked uncertain. Automatic replay could duplicate mail.
	if ( ! schedule_reminder_job( $order_id, $lease + 5 ) ) {
		reminder_finish( $order_id, 'blocked', 'scheduler_failed' );
		return false;
	}
	$data = reminder_data( $order_id, $now );
	if ( is_wp_error( $data ) ) { reminder_finish( $order_id, 'blocked', $data->get_error_code() ); return false; }
	$to = sanitize_email( (string) $data['order']->get_billing_email() );
	$subject = sprintf( 'Recordatorio: %s', sanitize_text_field( (string) $data['event']['title'] ) );
	$sent = $to && is_email( $to ) && wp_mail( $to, $subject, ticket_reminder_html( $data ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	if ( $sent ) { reminder_finish( $order_id, 'sent' ); return true; }
	$attempts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT attempts FROM $table WHERE order_id = %d", $order_id ) );
	$next = $now + min( 3600, 60 * ( 2 ** max( 0, $attempts - 1 ) ) );
	$event = Core\Event::get( (int) $row->event_id );
	$start = $event ? Core\gmt_datetime( $event->start_gmt() ) : null;
	if ( $attempts >= REMINDER_MAX_ATTEMPTS || ! $start || $next >= $start->getTimestamp() ) {
		reminder_finish( $order_id, 'failed', 'wp_mail_failed' );
	} else {
		reminder_finish( $order_id, 'retry', 'wp_mail_failed', $next );
	}
	return false;
}
add_action( REMINDER_HOOK, __NAMESPACE__ . '\send_ticket_reminder', 10, 1 );

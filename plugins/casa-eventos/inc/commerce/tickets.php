<?php
/** T5 ticket records and retryable confirmation delivery. */
namespace CasaEventos\Commerce;
if ( ! defined( 'ABSPATH' ) ) { exit; }

const TICKET_SCHEMA = 1;
const TICKET_SCHEMA_OPTION = 'lcda_ticket_schema';
const TICKET_EMAIL_HOOK = 'casa_eventos/send_ticket_confirmation_email';

function tickets_table() { global $wpdb; return $wpdb->prefix . 'casa_event_tickets'; }
function ticket_mail_table() { global $wpdb; return $wpdb->prefix . 'casa_ticket_mail'; }
function schedule_ticket_mail( $order_id, $timestamp ) {
	if ( function_exists( 'wp_schedule_single_event' ) ) { wp_schedule_single_event( max( time() + 1, (int) $timestamp ), TICKET_EMAIL_HOOK, array( (int) $order_id ) ); }
}
function clear_ticket_mail_schedule( $order_id ) {
	if ( function_exists( 'wp_clear_scheduled_hook' ) ) { wp_clear_scheduled_hook( TICKET_EMAIL_HOOK, array( (int) $order_id ) ); }
}

function install_ticket_tables() {
	global $wpdb;
	if ( ! function_exists( 'dbDelta' ) ) { require_once ABSPATH . 'wp-admin/includes/upgrade.php'; }
	$collate = $wpdb->get_charset_collate();
	$tickets = tickets_table();
	$mail = ticket_mail_table();
	$sql_tickets = "CREATE TABLE $tickets (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ticket_code char(64) NOT NULL,
		order_id bigint(20) unsigned NOT NULL,
		reservation_id bigint(20) unsigned NOT NULL,
		event_id bigint(20) unsigned NOT NULL,
		ticket_type_id bigint(20) unsigned NOT NULL,
		purchaser_id bigint(20) unsigned NOT NULL DEFAULT 0,
		order_item_id bigint(20) unsigned NOT NULL,
		unit_number smallint(5) unsigned NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'issued',
		created_gmt datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY ticket_code (ticket_code),
		UNIQUE KEY order_unit (order_id,order_item_id,unit_number),
		KEY event_type (event_id,ticket_type_id),
		KEY reservation_id (reservation_id)
	) $collate;";
	$sql_mail = "CREATE TABLE $mail (
		order_id bigint(20) unsigned NOT NULL,
		event_id bigint(20) unsigned NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		attempts smallint(5) unsigned NOT NULL DEFAULT 0,
		next_attempt bigint(20) unsigned NOT NULL DEFAULT 0,
		lease_until bigint(20) unsigned NOT NULL DEFAULT 0,
		last_error varchar(100) NOT NULL DEFAULT '',
		updated_gmt datetime NOT NULL,
		PRIMARY KEY  (order_id),
		KEY status_due (status,next_attempt)
	) $collate;";
	dbDelta( $sql_tickets );
	dbDelta( $sql_mail );
	$exists_tickets = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tickets ) );
	$exists_mail = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mail ) );
	if ( $exists_tickets !== $tickets || $exists_mail !== $mail ) { return false; }
	update_option( TICKET_SCHEMA_OPTION, TICKET_SCHEMA, false );
	return true;
}
function maybe_install_ticket_tables() {
	if ( (int) get_option( TICKET_SCHEMA_OPTION, 0 ) !== TICKET_SCHEMA ) { install_ticket_tables(); }
}
add_action( 'init', __NAMESPACE__ . '\\maybe_install_ticket_tables', 2 );

function ticket_identifier() { return bin2hex( random_bytes( 32 ) ); }

function paid_ticket_source( $order_id ) {
	global $wpdb;
	$order_id = integer_value( $order_id, 1 );
	if ( null === $order_id || (int) get_option( TICKET_SCHEMA_OPTION, 0 ) !== TICKET_SCHEMA ) { return error( 'ticket_schema', 'El registro de entradas no esta disponible.' ); }
	$order = wc_get_order( $order_id );
	if ( ! $order || 'lcda-ticket' !== $order->get_created_via() || ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) || ! $order->get_date_paid() ) {
		return error( 'ticket_unpaid', 'El pedido no tiene un pago confirmado.' );
	}
	$reservation_id = (int) $order->get_meta( '_lcda_ticket_reservation_id' );
	$event_id = (int) $order->get_meta( '_lcda_ticket_event_id' );
	if ( ! $reservation_id || ! $event_id ) { return error( 'ticket_owner', 'El pedido no tiene una reserva vinculada.' ); }
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . reservation_table() . ' WHERE event_id = %d AND id = %d LIMIT 1', $event_id, $reservation_id ) );
	if ( ! $row || 'paid' !== $row->status || (int) $row->order_id !== $order_id
		|| ! preg_match( '/^[a-f0-9]{64}$/D', (string) $row->payment_hash )
		|| ! hash_equals( (string) $row->request_hash, (string) $order->get_meta( '_lcda_ticket_request_hash' ) ) ) {
		return error( 'ticket_unverified', 'La reserva no tiene una transicion de pago verificada.' );
	}
	$expected = json_decode( (string) $row->quantities, true );
	$items = $order->get_items( 'line_item' );
	if ( ! is_array( $expected ) || ! $expected || ! is_array( $items ) || count( $items ) !== count( $expected ) ) {
		return error( 'ticket_lines', 'Las lineas del pedido no coinciden con la reserva.' );
	}
	$seen = array();
	$parent = parent_product( $event_id );
	if ( ! $parent || is_wp_error( $parent ) || (int) $parent->get_meta( '_lcda_ticket_event' ) !== $event_id ) {
		return error( 'ticket_event', 'No se puede verificar el evento del pedido.' );
	}
	foreach ( $items as $item ) {
		$type_id = (int) $item->get_variation_id();
		$type = wc_get_product( $type_id );
		if ( isset( $seen[ $type_id ] ) || ! isset( $expected[ $type_id ] ) || (int) $expected[ $type_id ] !== (int) $item->get_quantity()
			|| ! $type || (int) $type->get_parent_id() !== (int) $parent->get_id()
			|| ! $item->get_id() || ! $item->get_meta( '_lcda_ticket_unit_price' ) ) {
			return error( 'ticket_lines', 'Las lineas del pedido no coinciden con el snapshot.' );
		}
		$seen[ $type_id ] = true;
	}
	return array( 'order' => $order, 'row' => $row, 'items' => $items, 'event_id' => $event_id );
}

/** Insert missing order units idempotently; line/unit unique keys repair partial writes. */
function issue_paid_reservation_tickets( $event_id, $reservation_id, $order_id, $payment_hash = '' ) {
	global $wpdb;
	$source = paid_ticket_source( $order_id );
	if ( is_wp_error( $source ) ) { return $source; }
	if ( (int) $source['event_id'] !== (int) $event_id || (int) $source['row']->id !== (int) $reservation_id
		|| ! hash_equals( (string) $source['row']->payment_hash, (string) $payment_hash ) ) {
		return error( 'ticket_owner', 'La transicion no corresponde al pedido.' );
	}
	$now = gmdate( 'Y-m-d H:i:s' );
	$table = tickets_table();
	foreach ( $source['items'] as $item ) {
		$type_id = (int) $item->get_variation_id();
		for ( $unit = 1; $unit <= (int) $item->get_quantity(); ++$unit ) {
			$code = ticket_identifier();
			$inserted = $wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO $table (ticket_code,order_id,reservation_id,event_id,ticket_type_id,purchaser_id,order_item_id,unit_number,status,created_gmt) VALUES (%s,%d,%d,%d,%d,%d,%d,%d,'issued',%s)",
				$code, (int) $order_id, (int) $reservation_id, (int) $event_id, $type_id, (int) $source['order']->get_customer_id(), (int) $item->get_id(), $unit, $now
			) );
			if ( false === $inserted ) { return error( 'ticket_write', 'La emision de entradas requiere reintento.' ); }
		}
	}
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE order_id = %d ORDER BY ticket_type_id,id", $order_id ) );
	$expected_count = array_sum( array_map( 'intval', json_decode( (string) $source['row']->quantities, true ) ) );
	if ( ! is_array( $rows ) || count( $rows ) !== $expected_count ) { return error( 'ticket_write', 'La emision de entradas requiere conciliacion.' ); }
	$mail = ticket_mail_table();
	$queued = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $mail (order_id,event_id,status,attempts,next_attempt,lease_until,last_error,updated_gmt) VALUES (%d,%d,'pending',0,%d,0,'',%s)", $order_id, $event_id, time(), $now ) );
	if ( false === $queued ) { return error( 'ticket_mail_queue', 'La confirmacion requiere reintento.' ); }
	$mail_state = $wpdb->get_row( $wpdb->prepare( "SELECT status,next_attempt FROM $mail WHERE order_id = %d", $order_id ) );
	if ( $mail_state && in_array( $mail_state->status, array( 'pending', 'retry' ), true ) ) { schedule_ticket_mail( $order_id, max( time() + 5, (int) $mail_state->next_attempt ) ); }
	return array( 'order_id' => (int) $order_id, 'reservation_id' => (int) $reservation_id, 'count' => count( $rows ), 'replayed' => 0 === $queued );
}

function tickets_on_paid_transition( $event_id, $reservation_id, $order_id, $payment_hash ) {
	issue_paid_reservation_tickets( $event_id, $reservation_id, $order_id, $payment_hash );
}
add_action( 'casa_eventos/reservation_paid', __NAMESPACE__ . '\\tickets_on_paid_transition', 10, 4 );

/** A paid order status alone cannot mint tickets; the verified T2 ledger is required. */
function tickets_on_order_paid( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'lcda-ticket' !== $order->get_created_via() ) { return; }
	$reservation_id = (int) $order->get_meta( '_lcda_ticket_reservation_id' );
	$event_id = (int) $order->get_meta( '_lcda_ticket_event_id' );
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT payment_hash FROM ' . reservation_table() . ' WHERE event_id = %d AND id = %d LIMIT 1', $event_id, $reservation_id ) );
	$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . reservation_table() . ' WHERE event_id = %d AND id = %d LIMIT 1', $event_id, $reservation_id ) );
	if ( $row && 'paid' === (string) $status ) {
		issue_paid_reservation_tickets( $event_id, $reservation_id, (int) $order_id, (string) $row->payment_hash );
	}
}
add_action( 'woocommerce_order_status_processing', __NAMESPACE__ . '\\tickets_on_order_paid', 20, 1 );
add_action( 'woocommerce_order_status_completed', __NAMESPACE__ . '\\tickets_on_order_paid', 20, 1 );

/** Export-ready identifiers are visible only to the order owner or Woo managers. */
function ticket_records_for_order( $order_id ) {
	$order = wc_get_order( (int) $order_id );
	$user_id = get_current_user_id();
	if ( ! $order || ( ! current_user_can( 'manage_woocommerce' ) && ( ! $user_id || (int) $order->get_customer_id() !== (int) $user_id ) ) ) {
		return error( 'ticket_forbidden', 'No tenes permiso para consultar estas entradas.' );
	}
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT ticket_code,event_id,ticket_type_id,status,created_gmt FROM ' . tickets_table() . ' WHERE order_id = %d ORDER BY id', (int) $order_id ), ARRAY_A );
}

function ticket_confirmation_data( $order_id ) {
	$source = paid_ticket_source( $order_id );
	if ( is_wp_error( $source ) ) { return $source; }
	$event = $source['order']->get_meta( '_lcda_ticket_event_snapshot' );
	if ( ! is_array( $event ) || empty( $event['title'] ) || empty( $event['start_local'] ) ) { return error( 'ticket_event', 'Faltan datos del evento para la confirmacion.' ); }
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ticket_code,ticket_type_id FROM ' . tickets_table() . ' WHERE order_id = %d ORDER BY ticket_type_id,id', (int) $order_id ), ARRAY_A );
	$expected_count = array_sum( array_map( 'intval', json_decode( (string) $source['row']->quantities, true ) ) );
	if ( ! is_array( $rows ) || ! $rows || count( $rows ) !== $expected_count ) { return error( 'ticket_missing', 'Las entradas aun no estan disponibles.' ); }
	$groups = array();
	foreach ( $rows as $ticket ) {
		$type_id = (int) $ticket['ticket_type_id'];
		if ( ! isset( $groups[ $type_id ] ) ) { $groups[ $type_id ] = array( 'name' => '', 'codes' => array() ); }
		foreach ( $source['items'] as $item ) { if ( (int) $item->get_variation_id() === $type_id ) { $groups[ $type_id ]['name'] = $item->get_name(); break; } }
		$groups[ $type_id ]['codes'][] = (string) $ticket['ticket_code'];
	}
	foreach ( $groups as &$group ) { $group['quantity'] = count( $group['codes'] ); } unset( $group );
	return array( 'order' => $source['order'], 'event' => $event, 'groups' => array_values( $groups ), 'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
}

function ticket_confirmation_html( $data ) {
	ob_start();
	require __DIR__ . '/../../templates/emails/ticket-confirmation.php';
	return ob_get_clean();
}

/** Send one queued confirmation; failure changes only the outbox, never ticket rows. */
function send_ticket_confirmation_email( $order_id ) {
	global $wpdb;
	$order_id = integer_value( $order_id, 1 );
	if ( null === $order_id || (int) get_option( TICKET_SCHEMA_OPTION, 0 ) !== TICKET_SCHEMA ) { return error( 'ticket_mail', 'La confirmacion no esta disponible.' ); }
	$table = ticket_mail_table();
	$now = time();
	$lease = $now + 300;
	$claimed = $wpdb->query( $wpdb->prepare(
		"UPDATE $table SET status = 'sending', attempts = attempts + 1, lease_until = %d, updated_gmt = %s WHERE order_id = %d AND ((status IN ('pending','retry') AND next_attempt <= %d) OR (status = 'sending' AND lease_until < %d))",
		$lease, gmdate( 'Y-m-d H:i:s' ), $order_id, $now, $now
	) );
	if ( 1 !== $claimed ) { return error( 'ticket_mail_busy', 'La confirmacion no esta lista para enviar.' ); }
	// Watchdog makes a hard process interruption retryable after the lease expires.
	schedule_ticket_mail( $order_id, $lease + 5 );
	$data = ticket_confirmation_data( $order_id );
	if ( is_wp_error( $data ) ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'blocked', lease_until = 0, last_error = %s, updated_gmt = %s WHERE order_id = %d AND status = 'sending'", 'ticket_validation', gmdate( 'Y-m-d H:i:s' ), $order_id ) );
		clear_ticket_mail_schedule( $order_id );
		return $data;
	}
	$to = sanitize_email( (string) $data['order']->get_billing_email() );
	$subject = sprintf( 'Confirmacion de compra: %s', sanitize_text_field( (string) $data['event']['title'] ) );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$sent = $to && function_exists( 'wp_mail' ) ? wp_mail( $to, $subject, ticket_confirmation_html( $data ), $headers ) : false;
	if ( $sent ) {
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'sent', lease_until = 0, last_error = '', updated_gmt = %s WHERE order_id = %d AND status = 'sending'", gmdate( 'Y-m-d H:i:s' ), $order_id ) );
		if ( 1 === $updated ) { clear_ticket_mail_schedule( $order_id ); }
		return true;
	}
	$state = $wpdb->get_row( $wpdb->prepare( "SELECT attempts FROM $table WHERE order_id = %d", $order_id ) );
	$attempts = $state ? max( 1, (int) $state->attempts ) : 1;
	$delay = min( 3600, 60 * ( 2 ** min( 6, $attempts - 1 ) ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'retry', next_attempt = %d, lease_until = 0, last_error = %s, updated_gmt = %s WHERE order_id = %d AND status = 'sending'", $now + $delay, 'wp_mail_failed', gmdate( 'Y-m-d H:i:s' ), $order_id ) );
	clear_ticket_mail_schedule( $order_id );
	schedule_ticket_mail( $order_id, $now + $delay );
	return error( 'ticket_mail_failed', 'No se pudo enviar la confirmacion; quedo para reintento.' );
}
add_action( TICKET_EMAIL_HOOK, __NAMESPACE__ . '\\send_ticket_confirmation_email', 10, 1 );

<?php
/** T2 reservation coordinator. Internal API only; ticket sales remain closed. */
namespace CasaEventos\Commerce;
use CasaEventos\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }

const RESERVATION_SCHEMA = 3;
const RESERVATION_SCHEMA_OPTION = 'lcda_reservation_schema';

function reservation_table() {
	global $wpdb;
	return $wpdb->prefix . 'lcda_reservations';
}

/** One durable row is the entire admission effect. No Woo writes occur here. */
function install_reservations() {
	global $wpdb;
	if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_charset_collate' ) ) { return false; }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = reservation_table();
	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_id bigint(20) unsigned NOT NULL,
		request_hash char(64) NOT NULL,
		session_hash char(64) NOT NULL DEFAULT '',
		payload_hash char(64) NOT NULL,
		quantities longtext NOT NULL,
		snapshot longtext NOT NULL,
		status varchar(24) NOT NULL,
		created_at bigint(20) unsigned NOT NULL,
		expires_at bigint(20) unsigned NOT NULL,
		attempt_hash char(64) NOT NULL DEFAULT '',
		order_id bigint(20) unsigned DEFAULT NULL,
		payment_hash char(64) NOT NULL DEFAULT '',
		refund_hash char(64) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		UNIQUE KEY event_request (event_id,request_hash),
		UNIQUE KEY order_id (order_id),
		KEY event_status (event_id,status)
	) " . $wpdb->get_charset_collate() . ';';
	dbDelta( $sql );
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists !== $table ) { return false; }
	$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table );
	$required = array( 'id', 'event_id', 'request_hash', 'session_hash', 'payload_hash', 'quantities', 'snapshot', 'status', 'created_at', 'expires_at', 'attempt_hash', 'order_id', 'payment_hash', 'refund_hash' );
	if ( ! is_array( $columns ) || array_diff( $required, $columns ) ) { return false; }
	$index = $wpdb->get_results( "SHOW INDEX FROM $table WHERE Key_name = 'event_request'" );
	if ( ! is_array( $index ) || count( $index ) !== 2 || (int) $index[0]->Non_unique !== 0
		|| array( $index[0]->Column_name, $index[1]->Column_name ) !== array( 'event_id', 'request_hash' ) ) { return false; }
	$order_index = $wpdb->get_results( "SHOW INDEX FROM $table WHERE Key_name = 'order_id'" );
	if ( ! is_array( $order_index ) || count( $order_index ) !== 1 || (int) $order_index[0]->Non_unique !== 0
		|| 'order_id' !== $order_index[0]->Column_name ) { return false; }
	update_option( RESERVATION_SCHEMA_OPTION, RESERVATION_SCHEMA, false );
	return true;
}

function maybe_install_reservations() {
	if ( (int) get_option( RESERVATION_SCHEMA_OPTION, 0 ) !== RESERVATION_SCHEMA ) { install_reservations(); }
}
add_action( 'init', __NAMESPACE__ . '\maybe_install_reservations', 1 );

/** A missing or unreadable ledger is unknown, so configuration remains closed. */
function reservation_history( $event_id ) {
	global $wpdb;
	if ( (int) get_option( RESERVATION_SCHEMA_OPTION, 0 ) !== RESERVATION_SCHEMA ) { return true; }
	try {
	$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . reservation_table() . ' WHERE event_id = %d', $event_id ) );
	return null === $count || '' !== $wpdb->last_error || (int) $count > 0;
	} catch ( \Throwable $exception ) { return true; }
}

/** Canonical request, independent of current price or stock, for idempotent retries. */
function reservation_lines( $lines ) {
	if ( ! is_array( $lines ) || ! $lines ) { return error( 'request', 'Elegí al menos un tipo de entrada.' ); }
	$out = array();
	$total = 0;
	foreach ( $lines as $type_id => $quantity ) {
		$id = integer_value( $type_id, 1 );
		$qty = integer_value( $quantity, 1, 10 );
		if ( null === $id || null === $qty || isset( $out[ $id ] ) ) { return error( 'request', 'Los tipos o cantidades no son válidos.' ); }
		$total += $qty;
		if ( $total > 10 ) { return error( 'quantity', 'La compra admite como máximo 10 entradas.' ); }
		$out[ $id ] = $qty;
	}
	ksort( $out, SORT_NUMERIC );
	return $out;
}

/** Pure occupancy projection; malformed rows freeze admission. */
function reservation_held( $rows, $now, $refunds = array() ) {
	$by_type = array();
	$global = 0;
	if ( ! is_array( $rows ) ) { return error( 'ledger', 'No se puede verificar la ocupación.' ); }
	foreach ( $rows as $row ) {
		$status = (string) $row->status;
		if ( ! in_array( $status, array( 'checkout_active', 'order_creating', 'expired', 'payment_pending', 'submitting', 'paid', 'refunded', 'failed', 'cancelled' ), true ) ) {
			return error( 'ledger', 'La ocupación requiere conciliación.' );
		}
		$held = in_array( $status, array( 'order_creating', 'submitting', 'payment_pending', 'paid' ), true )
			|| ( 'checkout_active' === $status && (int) $row->expires_at > $now );
		if ( ! $held ) { continue; }
		$lines = json_decode( (string) $row->quantities, true );
		if ( ! is_array( $lines ) || ! $lines ) { return error( 'ledger', 'La reserva guardada es inválida.' ); }
		$released = $refunds[ (int) ( $row->id ?? 0 ) ] ?? array();
		foreach ( $lines as $type_id => $quantity ) {
			$id = integer_value( $type_id, 1 );
			$qty = integer_value( $quantity, 1, 10 );
			if ( null === $id || null === $qty ) { return error( 'ledger', 'La reserva guardada es inválida.' ); }
			$refund_qty = (int) ( $released[ $id ] ?? 0 );
			if ( $refund_qty < 0 || $refund_qty > $qty || ( $refund_qty && 'paid' !== $status ) ) {
				return error( 'ledger', 'La restitucion requiere conciliacion.' );
			}
			$by_type[ $id ] = ( $by_type[ $id ] ?? 0 ) + $qty - $refund_qty;
			$global += $qty - $refund_qty;
			unset( $released[ $id ] );
		}
		if ( $released ) { return error( 'ledger', 'La restitucion requiere conciliacion.' ); }
	}
	return array( 'global' => $global, 'types' => $by_type );
}

function reservation_row( $event_id, $request_hash ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . reservation_table() . ' WHERE event_id = %d AND request_hash = %s', $event_id, $request_hash ) );
	return $wpdb->last_error ? error( 'ledger', 'No se puede consultar la reserva.' ) : $row;
}

function reservation_result( $row, $now, $replayed ) {
	$status = 'checkout_active' === $row->status && (int) $row->expires_at <= $now ? 'expired' : $row->status;
	return array( 'id' => (int) $row->id, 'event_id' => (int) $row->event_id,
		'status' => $status, 'expires_at' => (int) $row->expires_at, 'replayed' => $replayed );
}

/** Every ledger mutation is fenced by the MySQL connection that owns the event lock. */
function reservation_update( $event_id, $reservation_id, $from, $to, $attempt_hash = null, $expiry = null, $extra = array() ) {
	global $wpdb;
	$set = 'status = %s';
	$args = array( $to );
	if ( null !== $attempt_hash ) { $set .= ', attempt_hash = %s'; $args[] = $attempt_hash; }
	foreach ( $extra as $column => $value ) {
		if ( ! in_array( $column, array( 'payment_hash', 'refund_hash', 'order_id' ), true ) ) { return false; }
		$set .= ', ' . $column . ' = ' . ( 'order_id' === $column ? '%d' : '%s' );
		$args[] = $value;
	}
	$sql = 'UPDATE ' . reservation_table() . ' SET ' . $set
		. ' WHERE event_id = %d AND id = %d AND status = %s';
	array_push( $args, $event_id, $reservation_id, $from );
	if ( null !== $expiry ) { $sql .= ' AND expires_at <= %d'; $args[] = $expiry; }
	$sql .= ' AND IS_USED_LOCK(%s) = CONNECTION_ID()';
	$args[] = lock_name( $event_id );
	return $wpdb->query( $wpdb->prepare( $sql, ...$args ) );
}

function reservation_by_id( $event_id, $reservation_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . reservation_table() . ' WHERE event_id = %d AND id = %d', $event_id, $reservation_id ) );
	return $wpdb->last_error ? error( 'ledger', 'No se puede consultar la reserva.' ) : $row;
}

/** Discard reads made before the event lock, including Woo product objects. */
function refresh_locked_reads( $event_id ) {
	if ( function_exists( 'wp_cache_flush_runtime' ) ) { wp_cache_flush_runtime(); }
	if ( function_exists( 'clean_post_cache' ) ) { clean_post_cache( $event_id ); }
}

/**
 * Internal admission only. An event lock shared with T1 serializes fresh reads
 * and the single SQL insert. A lost response is recovered by request key.
 */
function reserve( $event_id, $lines, $request_key, $now = null, $session_hash = '' ) {
	global $wpdb;
	$event_id = integer_value( $event_id, 1 );
	$normalized = reservation_lines( $lines );
	if ( null === $event_id || is_wp_error( $normalized ) ) { return is_wp_error( $normalized ) ? $normalized : error( 'request', 'El evento no es válido.' ); }
	if ( ! is_string( $request_key ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9-]{27,40}$/D', $request_key ) ) {
		return error( 'request', 'La clave de reserva no es válida.' );
	}
	if ( ! is_string( $session_hash ) || ( '' !== $session_hash && ! preg_match( '/^[a-f0-9]{64}$/D', $session_hash ) ) ) {
		return error( 'request', 'La sesion de compra no es valida.' );
	}
	if ( ! available() || (int) get_option( RESERVATION_SCHEMA_OPTION, 0 ) !== RESERVATION_SCHEMA ) {
		return error( 'unavailable', 'La coordinación de reservas no está disponible.' );
	}
	$now = null === $now ? time() : integer_value( $now, 0 );
	if ( null === $now ) { return error( 'request', 'El reloj no es válido.' ); }
	$request_hash = hash( 'sha256', $request_key );
	$payload_hash = hash( 'sha256', wp_json_encode( $normalized ) );
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$old = reservation_row( $event_id, $request_hash );
		if ( is_wp_error( $old ) ) { return $old; }
		if ( $old ) {
			if ( ! hash_equals( (string) $old->session_hash, $session_hash ) ) { return error( 'owner', 'La reserva pertenece a otra sesion.' ); }
			return hash_equals( (string) $old->payload_hash, $payload_hash )
				? reservation_result( $old, $now, true )
				: error( 'conflict', 'La clave de reserva ya se usó con otra selección.' );
		}
		$event = Core\Event::get( $event_id );
		$start = $event ? Core\gmt_datetime( $event->start_gmt() ) : null;
		if ( ! $event || ! $event->uuid() || ! $start || $start->getTimestamp() <= $now || ! $event->is_actionable( $now )
			|| Core\ACCESS_TICKETS !== $event->access_mode() || ! $event->is_before_sales_close( $now ) ) {
			return error( 'closed', 'Este evento no admite reservas.' );
		}
		$parent = parent_product( $event_id );
		if ( is_wp_error( $parent ) || ! $parent || ! zero_consumption( $parent, true ) ) {
			return error( 'unknown', 'El stock requiere conciliación antes de reservar.' );
		}
		$held_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,status,expires_at,quantities FROM ' . reservation_table() . ' WHERE event_id = %d', $event_id ) );
		if ( $wpdb->last_error ) { return error( 'ledger', 'No se puede consultar la ocupación.' ); }
		$has_paid_history = false;
		foreach ( (array) $held_rows as $held_row ) {
			if ( in_array( (string) $held_row->status, array( 'paid', 'refunded' ), true ) ) { $has_paid_history = true; break; }
		}
		$refunds = (int) get_option( REFUND_SCHEMA_OPTION, 0 ) !== REFUND_SCHEMA && ! $has_paid_history
			? array() : confirmed_refund_quantities( $event_id );
		if ( is_wp_error( $refunds ) ) { return $refunds; }
		$held = reservation_held( $held_rows, $now, $refunds );
		if ( is_wp_error( $held ) ) { return $held; }
		$quantities = array_values( $normalized );
		if ( array_sum( $quantities ) > $event->capacity() - $held['global'] ) {
			return error( 'capacity', 'No queda aforo suficiente.' );
		}
		$by_id = array();
		foreach ( variations( $parent ) as $type ) { $by_id[ $type->get_id() ] = $type; }
		$snapshot = array();
		foreach ( $normalized as $type_id => $quantity ) {
			$type = $by_id[ $type_id ] ?? null;
			if ( ! $type || '1' !== (string) $type->get_meta( '_lcda_ticket_request_complete' )
				|| '1' !== (string) $type->get_meta( '_lcda_ticket_active' )
				|| $quantity > (int) $type->get_meta( '_lcda_ticket_maximum' )
				|| $quantity > (int) $type->get_stock_quantity( 'edit' ) - ( $held['types'][ $type_id ] ?? 0 ) ) {
				return error( 'capacity', 'El tipo no está disponible para esa cantidad.' );
			}
			$cutoff = (string) $type->get_meta( '_lcda_ticket_cutoff_gmt' );
			if ( $cutoff ) {
				$cutoff_time = Core\gmt_datetime( $cutoff );
				if ( ! $cutoff_time || $cutoff_time->getTimestamp() <= $now ) { return error( 'closed', 'Cerró la venta de un tipo.' ); }
			}
			$snapshot[ $type_id ] = array( 'quantity' => $quantity, 'uuid' => (string) $type->get_meta( '_lcda_ticket_uuid' ),
				'name' => (string) $type->get_meta( '_lcda_ticket_name' ),
				'price' => (string) $type->get_regular_price( 'edit' ), 'currency' => (string) $type->get_meta( '_lcda_ticket_currency' ) );
		}
		$snapshot['_event'] = array( 'uuid' => $event->uuid(), 'title' => $event->title(), 'start_local' => $event->start_local(),
			'start_gmt' => $event->start_gmt(), 'timezone' => $event->timezone(),
			'venue' => $event->venue_name(), 'venue_address' => $event->venue_address() );
		// The current WP/Woo pricing context may not yet have an approved tax policy.
		// Snapshots remain internal and cannot be offered as a final buyer total.
		$inserted = $wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . reservation_table() . ' (event_id,request_hash,session_hash,payload_hash,quantities,snapshot,status,created_at,expires_at,attempt_hash) '
			. 'SELECT %d,%s,%s,%s,%s,%s,%s,%d,%d,%s WHERE IS_USED_LOCK(%s) = CONNECTION_ID()',
			$event_id, $request_hash, $session_hash, $payload_hash, wp_json_encode( $normalized ), wp_json_encode( $snapshot ),
			'checkout_active', $now, $now + 1200, '', lock_name( $event_id )
		) );
		if ( 1 !== $inserted ) {
			// Could be a lost insert response. A retry under the same key resolves it.
			return error( 'uncertain', 'No se pudo confirmar la reserva. Reintentá con la misma clave.' );
		}
		$row = reservation_row( $event_id, $request_hash );
		if ( is_wp_error( $row ) || ! $row ) { return error( 'uncertain', 'No se pudo confirmar la reserva. Reintentá con la misma clave.' ); }
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $now + 1200, 'casa_eventos/expire_reservation', array( $event_id, (int) $row->id ), 'casa-eventos' );
		}
		return reservation_result( $row, $now, false );
	} catch ( \Throwable $exception ) {
		return error( 'uncertain', 'La operacion se interrumpio. Reintenta con la misma clave.' );
	} finally {
		if ( ! $owned_before ) { release( $event_id ); }
	}
}

/** An unresolved payment attempt retains capacity after checkout expiry. No network call. */
function reservation_owner_row( $event_id, $reservation_id, $request_key ) {
	if ( ! is_string( $request_key ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9-]{27,40}$/D', $request_key ) ) {
		return error( 'request', 'La clave de reserva no es valida.' );
	}
	$row = reservation_by_id( $event_id, $reservation_id );
	if ( is_wp_error( $row ) ) { return $row; }
	if ( ! $row || ! hash_equals( (string) $row->request_hash, hash( 'sha256', $request_key ) ) ) {
		return error( 'owner', 'La reserva no pertenece a esta solicitud.' );
	}
	return $row;
}

function retain_unknown_payment( $event_id, $reservation_id, $request_key, $attempt_key, $now = null ) {
	global $wpdb;
	$event_id = integer_value( $event_id, 1 );
	$reservation_id = integer_value( $reservation_id, 1 );
	if ( null === $event_id || null === $reservation_id ) { return error( 'request', 'La reserva no es válida.' ); }
	if ( ! is_string( $attempt_key ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9-]{27,40}$/D', $attempt_key ) ) { return error( 'request', 'El intento no es válido.' ); }
	$now = null === $now ? time() : integer_value( $now, 0 );
	if ( null === $now ) { return error( 'request', 'El reloj no es válido.' ); }
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$row = reservation_owner_row( $event_id, $reservation_id, $request_key );
		if ( is_wp_error( $row ) ) { return $row; }
		if ( $wpdb->last_error || ! $row ) { return error( 'ledger', 'La reserva no está disponible.' ); }
		$hash = hash( 'sha256', $attempt_key );
		if ( 'payment_pending' === $row->status && hash_equals( (string) $row->attempt_hash, $hash ) ) { return reservation_result( $row, $now, true ); }
		if ( 'checkout_active' !== $row->status || (int) $row->expires_at <= $now ) { return error( 'expired', 'La reserva ya no admite un intento nuevo.' ); }
		$event = Core\Event::get( $event_id );
		$start = $event ? Core\gmt_datetime( $event->start_gmt() ) : null;
		if ( ! $event || ! $start || $start->getTimestamp() <= $now || ! $event->is_actionable( $now )
			|| Core\ACCESS_TICKETS !== $event->access_mode() || ! $event->is_before_sales_close( $now ) ) {
			return error( 'closed', 'El evento ya no admite un intento nuevo.' );
		}
		$parent = parent_product( $event_id );
		if ( is_wp_error( $parent ) || ! $parent ) { return error( 'unknown', 'Los tipos requieren conciliacion.' ); }
		$types = array();
		foreach ( variations( $parent ) as $type ) { $types[ $type->get_id() ] = $type; }
		$lines = json_decode( (string) $row->quantities, true );
		if ( ! is_array( $lines ) || ! $lines ) { return error( 'ledger', 'La reserva guardada es invalida.' ); }
		foreach ( $lines as $type_id => $quantity ) {
			$type = $types[ $type_id ] ?? null;
			if ( ! $type || '1' !== (string) $type->get_meta( '_lcda_ticket_active' ) ) { return error( 'closed', 'El tipo ya no admite pago.' ); }
			$cutoff = (string) $type->get_meta( '_lcda_ticket_cutoff_gmt' );
			if ( $cutoff ) {
				$cutoff_time = Core\gmt_datetime( $cutoff );
				if ( ! $cutoff_time || $cutoff_time->getTimestamp() <= $now ) { return error( 'closed', 'Cerro la venta del tipo.' ); }
			}
		}
		$updated = reservation_update( $event_id, $reservation_id, 'checkout_active', 'payment_pending', $hash );
		if ( 1 !== $updated ) { return error( 'uncertain', 'El estado del intento requiere conciliación.' ); }
		$row->status = 'payment_pending';
		$row->attempt_hash = $hash;
		return reservation_result( $row, $now, false );
	} catch ( \Throwable $exception ) {
		return error( 'uncertain', 'La operacion se interrumpio. Reintenta con la misma clave.' );
	} finally {
		if ( ! $owned_before ) { release( $event_id ); }
	}
}

/** Durable fence before any Woo order write; retries must reconcile first. */
function claim_order_creation( $event_id, $reservation_id, $request_key, $session_hash, $now = null ) {
	$event_id = integer_value( $event_id, 1 );
	$reservation_id = integer_value( $reservation_id, 1 );
	$now = null === $now ? time() : integer_value( $now, 0 );
	if ( null === $event_id || null === $reservation_id || null === $now
		|| ! is_string( $session_hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $session_hash ) ) {
		return error( 'request', 'La solicitud de pedido es invalida.' );
	}
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$row = reservation_owner_row( $event_id, $reservation_id, $request_key );
		if ( is_wp_error( $row ) ) { return $row; }
		if ( ! hash_equals( (string) $row->session_hash, $session_hash ) ) { return error( 'owner', 'La sesion no corresponde a la reserva.' ); }
		if ( 'order_creating' === $row->status ) { return reservation_result( $row, $now, true ); }
		if ( $row->order_id ) { return error( 'conflict', 'La reserva ya tiene pedido.' ); }
		if ( 'checkout_active' !== $row->status || (int) $row->expires_at <= $now ) { return error( 'expired', 'El checkout vencio.' ); }
		$updated = reservation_update( $event_id, $reservation_id, 'checkout_active', 'order_creating' );
		if ( 1 !== $updated ) { return error( 'uncertain', 'La creacion del pedido requiere conciliacion.' ); }
		$row->status = 'order_creating';
		return reservation_result( $row, $now, false );
	} catch ( \Throwable $exception ) { return error( 'uncertain', 'La creacion del pedido requiere conciliacion.' ); }
	finally { if ( ! $owned_before ) { release( $event_id ); } }
}

/** Bind one Woo order reference without creating or mutating an order. */
function bind_reservation_order( $event_id, $reservation_id, $request_key, $order_id ) {
	global $wpdb;
	$event_id = integer_value( $event_id, 1 );
	$reservation_id = integer_value( $reservation_id, 1 );
	$order_id = integer_value( $order_id, 1 );
	if ( null === $event_id || null === $reservation_id || null === $order_id ) { return error( 'request', 'La referencia de pedido es invalida.' ); }
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$row = reservation_owner_row( $event_id, $reservation_id, $request_key );
		if ( is_wp_error( $row ) ) { return $row; }
		if ( (int) $row->order_id === $order_id ) { return reservation_result( $row, time(), true ); }
		if ( $row->order_id || ! in_array( $row->status, array( 'checkout_active', 'order_creating' ), true ) ) { return error( 'conflict', 'La reserva ya tiene otro pedido o estado.' ); }
		$updated = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . reservation_table() . ' SET order_id = %d, status = %s WHERE event_id = %d AND id = %d AND order_id IS NULL AND status = %s AND IS_USED_LOCK(%s) = CONNECTION_ID()',
			$order_id, 'checkout_active', $event_id, $reservation_id, $row->status, lock_name( $event_id )
		) );
		if ( 1 !== $updated ) { return error( 'uncertain', 'El vinculo del pedido requiere conciliacion.' ); }
		$row->order_id = $order_id;
		$row->status = 'checkout_active';
		return reservation_result( $row, time(), false );
	} catch ( \Throwable $exception ) { return error( 'uncertain', 'El vinculo requiere conciliacion.' ); }
	finally { if ( ! $owned_before ) { release( $event_id ); } }
}

/** Internal adapter entry point. Evidence must already be authenticated externally. */
function record_payment_outcome( $event_id, $reservation_id, $request_key, $attempt_key, $outcome, $evidence_key, $now = null ) {
	$event_id = integer_value( $event_id, 1 );
	$reservation_id = integer_value( $reservation_id, 1 );
	if ( null === $event_id || null === $reservation_id || ! in_array( $outcome, array( 'paid', 'failed' ), true )
		|| ! is_string( $attempt_key ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9-]{27,40}$/D', $attempt_key )
		|| ! is_string( $evidence_key ) || '' === $evidence_key || strlen( $evidence_key ) > 200 ) {
		return error( 'request', 'El resultado de pago es invalido.' );
	}
	$now = null === $now ? time() : integer_value( $now, 0 );
	if ( null === $now ) { return error( 'request', 'El reloj no es valido.' ); }
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$row = reservation_owner_row( $event_id, $reservation_id, $request_key );
		if ( is_wp_error( $row ) ) { return $row; }
		$attempt_hash = hash( 'sha256', $attempt_key );
		$evidence_hash = hash( 'sha256', $evidence_key );
		if ( ! hash_equals( (string) $row->attempt_hash, $attempt_hash ) ) { return error( 'conflict', 'El intento no corresponde a la reserva.' ); }
		if ( 'paid' === $outcome && 'paid' === $row->status && hash_equals( (string) $row->payment_hash, $evidence_hash ) ) {
			do_action( 'casa_eventos/reservation_paid', $event_id, $reservation_id, (int) $row->order_id, (string) $row->payment_hash );
			return reservation_result( $row, $now, true );
		}
		if ( 'failed' === $outcome && in_array( $row->status, array( 'checkout_active', 'failed' ), true )
			&& hash_equals( (string) $row->payment_hash, $evidence_hash ) ) { return reservation_result( $row, $now, true ); }
		if ( 'payment_pending' !== $row->status ) { return error( 'conflict', 'El resultado exige conciliacion manual.' ); }
		$target = 'paid' === $outcome ? 'paid' : ( (int) $row->expires_at > $now ? 'checkout_active' : 'failed' );
		$updated = reservation_update( $event_id, $reservation_id, 'payment_pending', $target, null, null, array( 'payment_hash' => $evidence_hash ) );
		if ( 1 !== $updated ) { return error( 'uncertain', 'El resultado requiere conciliacion.' ); }
		$row->status = $target;
		$row->payment_hash = $evidence_hash;
		if ( 'paid' === $target ) { do_action( 'casa_eventos/reservation_paid', $event_id, $reservation_id, (int) $row->order_id, $evidence_hash ); }
		return reservation_result( $row, $now, false );
	} catch ( \Throwable $exception ) { return error( 'uncertain', 'El pago requiere conciliacion.' ); }
	finally { if ( ! $owned_before ) { release( $event_id ); } }
}

/** Legacy entry point deliberately closed: references are not gateway proof. */
function record_confirmed_refund( $event_id, $reservation_id, $request_key, $refund_reference, $now = null ) {
	return error( 'refund_evidence', 'Usa la conciliacion T6 con evidencia verificada.' );
}

/** Cleanup is optional for correctness: admission excludes expired active rows by time. */
function expire_reservation( $event_id, $reservation_id, $now = null ) {
	global $wpdb;
	$event_id = integer_value( $event_id, 1 );
	$reservation_id = integer_value( $reservation_id, 1 );
	if ( null === $event_id || null === $reservation_id ) { return error( 'request', 'La reserva no es válida.' ); }
	$now = null === $now ? time() : integer_value( $now, 0 );
	if ( null === $now ) { return error( 'request', 'El reloj no es válido.' ); }
	$owned_before = ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] );
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$updated = reservation_update( $event_id, $reservation_id, 'checkout_active', 'expired', null, $now );
		return false === $updated ? error( 'uncertain', 'La expiracion requiere conciliacion.' ) : $updated;
	} catch ( \Throwable $exception ) {
		return error( 'uncertain', 'La expiracion requiere conciliacion.' );
	} finally {
		if ( ! $owned_before ) { release( $event_id ); }
	}
}
add_action( 'casa_eventos/expire_reservation', __NAMESPACE__ . '\expire_reservation', 10, 2 );

/** Hold the event lock through Core and native WordPress event writes. */
function lock_event_write( $result, $event_id ) {
	if ( is_wp_error( $result ) || false === $result || ! $event_id ) { return $result; }
	if ( (int) get_option( RESERVATION_SCHEMA_OPTION, 0 ) !== RESERVATION_SCHEMA ) { return $result; }
	if ( Core\POST_TYPE !== get_post_type( $event_id ) ) { return $result; }
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	$GLOBALS['lcda_reservation_event_writes'][ $event_id ] = true;
	return $result;
}
function lock_core_event_write( $result, $event_id, $fields ) {
	return lock_event_write( $result, $event_id );
}
add_filter( 'casa_eventos/before_event_write', __NAMESPACE__ . '\lock_core_event_write', 5, 3 );

function lock_native_event_write( $data, $postarr ) {
	if ( Core\POST_TYPE !== ( $data['post_type'] ?? '' ) || empty( $postarr['ID'] ) ) { return $data; }
	$lock = lock_event_write( true, (int) $postarr['ID'] );
	if ( is_wp_error( $lock ) ) { wp_die( esc_html( $lock->get_error_message() ), 'Evento ocupado', array( 'response' => 503 ) ); }
	return $data;
}
add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\lock_native_event_write', 5, 2 );

function lock_event_metadata( $check, $object_id, $meta_key ) {
	if ( null !== $check || ! Core\is_event_meta_key( $meta_key ) ) { return $check; }
	$lock = lock_event_write( true, (int) $object_id );
	if ( is_wp_error( $lock ) ) { wp_die( esc_html( $lock->get_error_message() ), 'Evento ocupado', array( 'response' => 503 ) ); }
	return $check;
}
add_filter( 'casa_eventos/before_event_metadata', __NAMESPACE__ . '\lock_event_metadata', 5, 3 );

/** Cancelled events release checkout holds; sent or uncertain payments remain retained. */
function cancel_unsubmitted_reservations( $event_id ) {
	global $wpdb;
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
	$updated = $wpdb->query( $wpdb->prepare(
		'UPDATE ' . reservation_table() . " SET status = 'cancelled' WHERE event_id = %d AND status = 'checkout_active' AND IS_USED_LOCK(%s) = CONNECTION_ID()",
		$event_id, lock_name( $event_id )
	) );
	return false === $updated ? error( 'uncertain', 'La cancelacion requiere conciliacion.' ) : $updated;
	} catch ( \Throwable $exception ) { return error( 'uncertain', 'La cancelacion requiere conciliacion.' ); }
}
add_action( 'casa_eventos/event_cancelled', __NAMESPACE__ . '\cancel_unsubmitted_reservations' );

<?php
/** T6 refund reconciliation. The gateway adapter is deliberately absent. */
namespace CasaEventos\Commerce;

if ( ! defined( 'ABSPATH' ) ) { exit; }

const REFUND_SCHEMA = 1;
const REFUND_SCHEMA_OPTION = 'lcda_refund_schema';

function refunds_table() { global $wpdb; return $wpdb->prefix . 'casa_ticket_refunds'; }

function install_refunds() {
	global $wpdb;
	if ( ! function_exists( 'dbDelta' ) ) { require_once ABSPATH . 'wp-admin/includes/upgrade.php'; }
	$table = refunds_table();
	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		order_id bigint(20) unsigned NOT NULL,
		event_id bigint(20) unsigned NOT NULL,
		reservation_id bigint(20) unsigned NOT NULL,
		request_hash char(64) NOT NULL,
		ticket_ids longtext NOT NULL,
		line_quantities longtext NOT NULL,
		amount varchar(32) NOT NULL,
		currency varchar(8) NOT NULL,
		status varchar(20) NOT NULL,
		evidence_hash char(64) NOT NULL DEFAULT '',
		reference_hash char(64) DEFAULT NULL,
		woo_refund_id bigint(20) unsigned DEFAULT NULL,
		created_gmt datetime NOT NULL,
		updated_gmt datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY order_request (order_id,request_hash),
		UNIQUE KEY reference_hash (reference_hash),
		UNIQUE KEY woo_refund_id (woo_refund_id),
		KEY reservation_status (reservation_id,status)
	) " . $wpdb->get_charset_collate() . ';';
	dbDelta( $sql );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
	update_option( REFUND_SCHEMA_OPTION, REFUND_SCHEMA, false );
	return true;
}
function maybe_install_refunds() {
	if ( (int) get_option( REFUND_SCHEMA_OPTION, 0 ) !== REFUND_SCHEMA ) { install_refunds(); }
}
add_action( 'init', __NAMESPACE__ . '\\maybe_install_refunds', 4 );

function refund_authorized( $event_id ) {
	return $event_id > 0 && 'casa_evento' === get_post_type( $event_id )
		&& current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' )
		&& current_user_can( 'edit_post', $event_id );
}

function refund_request_row( $order_id, $request_hash ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . refunds_table() . ' WHERE order_id = %d AND request_hash = %s', $order_id, $request_hash ) );
	return $wpdb->last_error ? error( 'refund_ledger', 'No se puede consultar el reembolso.' ) : $row;
}

function refund_result( $row, $replayed ) {
	return array( 'id' => (int) $row->id, 'order_id' => (int) $row->order_id,
		'status' => (string) $row->status, 'amount' => (string) $row->amount,
		'currency' => (string) $row->currency, 'replayed' => $replayed );
}

/** All validation and writes happen under T2's existing event lock. */
function refund_order_source( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'lcda-ticket' !== $order->get_created_via() || ! $order->get_date_paid()
		|| ! in_array( $order->get_status(), array( 'processing', 'completed', 'refunded' ), true ) ) {
		return error( 'refund_order', 'El pedido no es una compra de entradas pagada.' );
	}
	$event_id = (int) $order->get_meta( '_lcda_ticket_event_id' );
	$reservation_id = (int) $order->get_meta( '_lcda_ticket_reservation_id' );
	if ( ! refund_authorized( $event_id ) || ! $reservation_id ) { return error( 'refund_forbidden', 'No tenes permiso para reembolsar este pedido.' ); }
	$row = reservation_by_id( $event_id, $reservation_id );
	if ( is_wp_error( $row ) || ! $row || (int) $row->order_id !== (int) $order_id
		|| ! in_array( $row->status, array( 'paid', 'refunded' ), true )
		|| ! preg_match( '/^[a-f0-9]{64}$/D', (string) $row->payment_hash )
		|| ! hash_equals( (string) $row->request_hash, (string) $order->get_meta( '_lcda_ticket_request_hash' ) ) ) {
		return error( 'refund_payment', 'El pago requiere conciliacion antes del reembolso.' );
	}
	return array( 'order' => $order, 'row' => $row, 'event_id' => $event_id );
}

/** Amount is derived from Woo order lines in minor units, including deterministic rounding per unit. */
function refund_selection( $source, $ticket_ids ) {
	global $wpdb;
	$order = $source['order'];
	if ( ! is_array( $ticket_ids ) || ! $ticket_ids ) { return error( 'refund_selection', 'Selecciona entradas para reembolsar.' ); }
	$ids = array();
	foreach ( $ticket_ids as $id ) {
		$id = integer_value( $id, 1 );
		if ( null === $id || isset( $ids[ $id ] ) ) { return error( 'refund_selection', 'La seleccion contiene entradas invalidas.' ); }
		$ids[ $id ] = true;
	}
	ksort( $ids, SORT_NUMERIC );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,order_item_id,ticket_type_id,unit_number,status FROM ' . tickets_table() . ' WHERE order_id = %d ORDER BY id', $order->get_id() ) );
	if ( $wpdb->last_error || ! is_array( $rows ) ) { return error( 'refund_tickets', 'No se pueden verificar las entradas.' ); }
	$items = $order->get_items( 'line_item' );
	$expected = 0;
	$by_item = array();
	foreach ( $items as $item ) {
		$quantity = (int) $item->get_quantity();
		if ( $quantity < 1 || ! $item->get_id() || ! $item->get_variation_id()
			|| wc_format_decimal( $item->get_total_tax(), wc_get_price_decimals() ) !== wc_format_decimal( 0, wc_get_price_decimals() ) ) {
			return error( 'refund_lines', 'Las lineas requieren conciliacion.' );
		}
		$expected += $quantity;
		$by_item[ $item->get_id() ] = $item;
	}
	if ( count( $rows ) !== $expected ) { return error( 'refund_tickets', 'La emision no esta completa.' ); }
	$selected = array();
	$line_quantities = array();
	$line_amounts = array();
	foreach ( $rows as $ticket ) {
		$item = $by_item[ (int) $ticket->order_item_id ] ?? null;
		if ( ! $item || (int) $item->get_variation_id() !== (int) $ticket->ticket_type_id
			|| (int) $ticket->unit_number < 1 || (int) $ticket->unit_number > (int) $item->get_quantity() ) {
			return error( 'refund_tickets', 'Las entradas no coinciden con el pedido.' );
		}
		if ( ! isset( $ids[ (int) $ticket->id ] ) ) { continue; }
		if ( 'issued' !== $ticket->status ) { return error( 'refund_tickets', 'Una entrada ya fue invalidada.' ); }
		$minor = (int) wc_add_number_precision( $item->get_total() );
		$base = intdiv( $minor, (int) $item->get_quantity() );
		$unit_minor = $base + ( (int) $ticket->unit_number <= $minor % (int) $item->get_quantity() ? 1 : 0 );
		$line_amounts[ $item->get_id() ] = ( $line_amounts[ $item->get_id() ] ?? 0 ) + $unit_minor;
		$type_id = (int) $ticket->ticket_type_id;
		$line_quantities[ $type_id ] = ( $line_quantities[ $type_id ] ?? 0 ) + 1;
		$selected[] = (int) $ticket->id;
	}
	if ( count( $selected ) !== count( $ids ) ) { return error( 'refund_tickets', 'Una entrada no pertenece al pedido.' ); }
	$line_items = array();
	foreach ( $items as $item ) {
		$item_id = $item->get_id();
		if ( ! isset( $line_amounts[ $item_id ] ) ) { continue; }
		$line_items[ $item_id ] = array( 'qty' => $line_quantities[ (int) $item->get_variation_id() ],
			'refund_total' => wc_format_decimal( wc_remove_number_precision( $line_amounts[ $item_id ] ), wc_get_price_decimals() ),
			'refund_tax' => array() );
	}
	ksort( $line_quantities, SORT_NUMERIC );
	return array( 'ticket_ids' => $selected, 'line_quantities' => $line_quantities,
		'line_items' => $line_items, 'amount' => wc_format_decimal( wc_remove_number_precision( array_sum( $line_amounts ) ), wc_get_price_decimals() ) );
}

function request_ticket_refund( $order_id, $ticket_ids, $request_key ) {
	global $wpdb;
	$order_id = integer_value( $order_id, 1 );
	if ( null === $order_id || ! is_string( $request_key ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9-]{27,40}$/D', $request_key ) ) {
		return error( 'refund_request', 'La solicitud de reembolso es invalida.' );
	}
	if ( ! available() || (int) get_option( REFUND_SCHEMA_OPTION, 0 ) !== REFUND_SCHEMA ) { return error( 'refund_schema', 'La conciliacion no esta disponible.' ); }
	$order = wc_get_order( $order_id );
	$event_id = $order ? (int) $order->get_meta( '_lcda_ticket_event_id' ) : 0;
	if ( ! refund_authorized( $event_id ) ) { return error( 'refund_forbidden', 'No tenes permiso para reembolsar este pedido.' ); }
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$source = refund_order_source( $order_id );
		if ( is_wp_error( $source ) ) { return $source; }
		$hash = hash( 'sha256', $request_key );
		$old = refund_request_row( $order_id, $hash );
		if ( is_wp_error( $old ) ) { return $old; }
		if ( $old ) {
			$input = array_map( 'intval', (array) $ticket_ids );
			sort( $input, SORT_NUMERIC );
			return $input === json_decode( (string) $old->ticket_ids, true )
				? refund_result( $old, true ) : error( 'refund_conflict', 'La clave se uso para otras entradas.' );
		}
		if ( 'paid' !== $source['row']->status ) { return error( 'refund_complete', 'La compra ya esta totalmente reembolsada.' ); }
		$selection = refund_selection( $source, $ticket_ids );
		if ( is_wp_error( $selection ) ) { return $selection; }
		$pending = $wpdb->get_results( $wpdb->prepare( "SELECT ticket_ids FROM " . refunds_table() . " WHERE order_id = %d AND status IN ('requested','unknown','verified')", $order_id ) );
		if ( $wpdb->last_error ) { return error( 'refund_ledger', 'No se puede verificar otras solicitudes.' ); }
		foreach ( (array) $pending as $entry ) {
			if ( array_intersect( $selection['ticket_ids'], (array) json_decode( (string) $entry->ticket_ids, true ) ) ) {
				return error( 'refund_pending', 'Estas entradas ya tienen un reembolso sin resolver.' );
			}
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$inserted = $wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . refunds_table() . ' (order_id,event_id,reservation_id,request_hash,ticket_ids,line_quantities,amount,currency,status,created_gmt,updated_gmt) VALUES (%d,%d,%d,%s,%s,%s,%s,%s,%s,%s,%s)',
			$order_id, $event_id, (int) $source['row']->id, $hash, wp_json_encode( $selection['ticket_ids'] ),
			wp_json_encode( $selection['line_quantities'] ), $selection['amount'], $order->get_currency(), 'requested', $now, $now
		) );
		if ( 1 !== $inserted ) { return error( 'refund_uncertain', 'La solicitud requiere conciliacion.' ); }
		$row = refund_request_row( $order_id, $hash );
		return is_wp_error( $row ) || ! $row ? error( 'refund_uncertain', 'La solicitud requiere conciliacion.' ) : refund_result( $row, false );
	} catch ( \Throwable $exception ) { return error( 'refund_uncertain', 'La solicitud requiere conciliacion.' ); }
	finally { release( $event_id ); }
}

/** A timeout or ambiguous gateway response is an audit event, never a release. */
function mark_refund_outcome_unknown( $order_id, $request_key ) {
	global $wpdb;
	$order_id = integer_value( $order_id, 1 );
	if ( null === $order_id || ! is_string( $request_key ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9-]{27,40}$/D', $request_key ) ) {
		return error( 'refund_request', 'La solicitud es invalida.' );
	}
	$order = wc_get_order( $order_id );
	$event_id = $order ? (int) $order->get_meta( '_lcda_ticket_event_id' ) : 0;
	if ( ! refund_authorized( $event_id ) ) { return error( 'refund_forbidden', 'No tenes permiso para conciliar reembolsos.' ); }
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$row = refund_request_row( $order_id, hash( 'sha256', $request_key ) );
		if ( is_wp_error( $row ) || ! $row ) { return error( 'refund_request', 'La solicitud no existe.' ); }
		if ( in_array( $row->status, array( 'confirmed', 'unknown' ), true ) ) { return refund_result( $row, true ); }
		if ( 'requested' !== $row->status ) { return error( 'refund_state', 'El resultado requiere conciliacion.' ); }
		$updated = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . refunds_table() . " SET status = 'unknown', updated_gmt = %s WHERE id = %d AND status = 'requested' AND IS_USED_LOCK(%s) = CONNECTION_ID()",
			gmdate( 'Y-m-d H:i:s' ), (int) $row->id, lock_name( $event_id )
		) );
		if ( 1 !== $updated ) { return error( 'refund_uncertain', 'El resultado requiere conciliacion.' ); }
		$row->status = 'unknown';
		return refund_result( $row, false );
	} catch ( \Throwable $exception ) { return error( 'refund_uncertain', 'El resultado requiere conciliacion.' ); }
	finally { release( $event_id ); }
}

/** The future gateway adapter must authenticate and match this entire envelope. */
function refund_verified_evidence( $request, $evidence ) {
	$verified = apply_filters( 'casa_eventos/verify_refund_evidence', null, $evidence, array(
		'order_id' => (int) $request->order_id, 'request_hash' => (string) $request->request_hash,
		'amount' => (string) $request->amount, 'currency' => (string) $request->currency,
	) );
	if ( ! is_array( $verified ) || true !== ( $verified['verified'] ?? null )
		|| (int) ( $verified['order_id'] ?? 0 ) !== (int) $request->order_id
		|| ! hash_equals( (string) $request->request_hash, (string) ( $verified['request_hash'] ?? '' ) )
		|| (string) $request->amount !== (string) ( $verified['amount'] ?? '' )
		|| (string) $request->currency !== (string) ( $verified['currency'] ?? '' )
		|| ! is_string( $verified['reference'] ?? null ) || '' === $verified['reference']
		|| strlen( $verified['reference'] ) > 200 ) {
		return error( 'refund_evidence', 'Falta evidencia verificada del procesador.' );
	}
	return array( 'reference_hash' => hash( 'sha256', $verified['reference'] ),
		'evidence_hash' => hash( 'sha256', wp_json_encode( $verified ) ) );
}

/** Recover a Woo refund after an interrupted response, using its durable reason marker. */
function refund_find_woo( $order, $request_hash ) {
	$marker = 'LCDA T6 ' . $request_hash;
	$found = null;
	foreach ( $order->get_refunds() as $refund ) {
		if ( $marker !== (string) $refund->get_reason() ) { continue; }
		if ( $found ) { return error( 'refund_duplicate', 'Hay reembolsos Woo duplicados; requiere revision.' ); }
		$found = $refund;
	}
	return $found;
}

function confirm_ticket_refund( $order_id, $request_key, $evidence ) {
	global $wpdb;
	$order_id = integer_value( $order_id, 1 );
	if ( null === $order_id || ! is_string( $request_key ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9-]{27,40}$/D', $request_key ) ) {
		return error( 'refund_request', 'La referencia de solicitud es invalida.' );
	}
	$order = wc_get_order( $order_id );
	$event_id = $order ? (int) $order->get_meta( '_lcda_ticket_event_id' ) : 0;
	if ( ! refund_authorized( $event_id ) ) { return error( 'refund_forbidden', 'No tenes permiso para confirmar reembolsos.' ); }
	if ( (int) get_option( REFUND_SCHEMA_OPTION, 0 ) !== REFUND_SCHEMA ) { return error( 'refund_schema', 'La conciliacion no esta disponible.' ); }
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	$transaction_started = false;
	try {
		$request = refund_request_row( $order_id, hash( 'sha256', $request_key ) );
		if ( is_wp_error( $request ) || ! $request ) { return error( 'refund_request', 'La solicitud no existe.' ); }
		$proof = refund_verified_evidence( $request, $evidence );
		if ( is_wp_error( $proof ) ) { return $proof; }
		if ( 'confirmed' === $request->status ) {
			return hash_equals( (string) $request->reference_hash, $proof['reference_hash'] )
				? refund_result( $request, true ) : error( 'refund_conflict', 'La evidencia difiere de la confirmacion.' );
		}
		if ( ! in_array( $request->status, array( 'requested', 'unknown', 'verified' ), true ) ) { return error( 'refund_state', 'El reembolso requiere revision.' ); }
		if ( $request->reference_hash && ! hash_equals( (string) $request->reference_hash, $proof['reference_hash'] ) ) {
			return error( 'refund_conflict', 'La referencia financiera difiere.' );
		}
		$source = refund_order_source( $order_id );
		if ( is_wp_error( $source ) || (int) $source['row']->id !== (int) $request->reservation_id ) { return error( 'refund_order', 'La compra requiere conciliacion.' ); }
		$ticket_ids = json_decode( (string) $request->ticket_ids, true );
		$selection = refund_selection( $source, $ticket_ids );
		if ( is_wp_error( $selection ) || $selection['amount'] !== (string) $request->amount
			|| wp_json_encode( $selection['line_quantities'] ) !== (string) $request->line_quantities ) {
			return error( 'refund_tickets', 'Las entradas cambiaron; requiere conciliacion.' );
		}
		// Persist the financial reference before Woo CRUD. Unknown outcomes stay occupied.
		$marked = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . refunds_table() . " SET status = 'verified', evidence_hash = %s, reference_hash = %s, updated_gmt = %s WHERE id = %d AND status IN ('requested','unknown','verified') AND (reference_hash IS NULL OR reference_hash = %s) AND IS_USED_LOCK(%s) = CONNECTION_ID()",
			$proof['evidence_hash'], $proof['reference_hash'], gmdate( 'Y-m-d H:i:s' ), (int) $request->id,
			$proof['reference_hash'], lock_name( $event_id )
		) );
		if ( false === $marked ) { return error( 'refund_uncertain', 'La evidencia requiere conciliacion.' ); }
		$woo_refund = refund_find_woo( $order, (string) $request->request_hash );
		if ( is_wp_error( $woo_refund ) ) { return $woo_refund; }
		if ( ! $woo_refund ) {
			$GLOBALS['lcda_t6_refund_write'] = (string) $request->request_hash;
			try {
				$woo_refund = wc_create_refund( array( 'order_id' => $order_id, 'amount' => $request->amount,
					'reason' => 'LCDA T6 ' . $request->request_hash, 'line_items' => $selection['line_items'],
					'refund_payment' => false, 'restock_items' => false ) );
			} finally { unset( $GLOBALS['lcda_t6_refund_write'] ); }
			if ( is_wp_error( $woo_refund ) || ! $woo_refund ) {
				$wpdb->query( $wpdb->prepare( "UPDATE " . refunds_table() . " SET status = 'unknown', updated_gmt = %s WHERE id = %d AND status = 'verified'", gmdate( 'Y-m-d H:i:s' ), (int) $request->id ) );
				return error( 'refund_uncertain', 'El registro Woo requiere conciliacion; el cupo sigue ocupado.' );
			}
		}
		if ( (int) $woo_refund->get_parent_id() !== $order_id
			|| wc_format_decimal( $woo_refund->get_amount(), wc_get_price_decimals() ) !== (string) $request->amount ) {
			return error( 'refund_woo', 'El reembolso Woo no coincide; el cupo sigue ocupado.' );
		}
		// Ticket invalidation, refund state and T2 occupancy projection commit together.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return error( 'refund_uncertain', 'No se pudo iniciar la invalidacion; el cupo sigue ocupado.' );
		}
		$transaction_started = true;
		foreach ( $ticket_ids as $ticket_id ) {
			$changed = $wpdb->query( $wpdb->prepare(
				"UPDATE " . tickets_table() . " SET status = 'revoked' WHERE id = %d AND order_id = %d AND status = 'issued'",
				(int) $ticket_id, $order_id
			) );
			if ( 1 !== $changed ) { $wpdb->query( 'ROLLBACK' ); $transaction_started = false; return error( 'refund_uncertain', 'La invalidacion requiere conciliacion; el cupo sigue ocupado.' ); }
		}
		$changed = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . refunds_table() . " SET status = 'confirmed', woo_refund_id = %d, updated_gmt = %s WHERE id = %d AND status = 'verified' AND IS_USED_LOCK(%s) = CONNECTION_ID()",
			(int) $woo_refund->get_id(), gmdate( 'Y-m-d H:i:s' ), (int) $request->id, lock_name( $event_id )
		) );
		if ( 1 !== $changed ) { $wpdb->query( 'ROLLBACK' ); $transaction_started = false; return error( 'refund_uncertain', 'La confirmacion requiere conciliacion; el cupo sigue ocupado.' ); }
		$remaining = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . tickets_table() . ' WHERE order_id = %d AND status = %s', $order_id, 'issued' ) );
		if ( $wpdb->last_error || null === $remaining ) {
			$wpdb->query( 'ROLLBACK' ); $transaction_started = false;
			return error( 'refund_uncertain', 'No se puede verificar las entradas vigentes.' );
		}
		if ( 0 === (int) $remaining ) {
			$changed = reservation_update( $event_id, (int) $request->reservation_id, 'paid', 'refunded', null, null, array( 'refund_hash' => $proof['reference_hash'] ) );
			if ( 1 !== $changed ) { $wpdb->query( 'ROLLBACK' ); $transaction_started = false; return error( 'refund_uncertain', 'El cierre total requiere conciliacion.' ); }
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) { return error( 'refund_uncertain', 'El resultado local requiere conciliacion.' ); }
		$transaction_started = false;
		$request->status = 'confirmed';
		$request->woo_refund_id = $woo_refund->get_id();
		$request->reference_hash = $proof['reference_hash'];
		return refund_result( $request, false );
	} catch ( \Throwable $exception ) {
		if ( $transaction_started ) { $wpdb->query( 'ROLLBACK' ); }
		return error( 'refund_uncertain', 'El resultado requiere conciliacion; el cupo sigue ocupado.' );
	} finally { release( $event_id ); }
}

/** T2 calls this while already holding its event lock. Missing or malformed data closes admission. */
function confirmed_refund_quantities( $event_id ) {
	global $wpdb;
	if ( (int) get_option( REFUND_SCHEMA_OPTION, 0 ) !== REFUND_SCHEMA ) { return error( 'refund_schema', 'La conciliacion no esta disponible.' ); }
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT reservation_id,line_quantities FROM " . refunds_table() . " WHERE event_id = %d AND status = 'confirmed'", $event_id ) );
	if ( $wpdb->last_error || ! is_array( $rows ) ) { return error( 'refund_ledger', 'No se puede verificar el cupo restituido.' ); }
	$totals = array();
	foreach ( $rows as $row ) {
		$lines = json_decode( (string) $row->line_quantities, true );
		if ( ! is_array( $lines ) || ! $lines ) { return error( 'refund_ledger', 'El reembolso guardado es invalido.' ); }
		foreach ( $lines as $type_id => $quantity ) {
			$id = integer_value( $type_id, 1 );
			$qty = integer_value( $quantity, 1 );
			if ( null === $id || null === $qty ) { return error( 'refund_ledger', 'El reembolso guardado es invalido.' ); }
			$totals[ (int) $row->reservation_id ][ $id ] = ( $totals[ (int) $row->reservation_id ][ $id ] ?? 0 ) + $qty;
		}
	}
	return $totals;
}

/** Fail admission closed if Woo financial history and the T6 ledger diverge. */
function refund_woo_reconciled( $order, $reservation ) {
	global $wpdb;
	if ( (int) get_option( REFUND_SCHEMA_OPTION, 0 ) !== REFUND_SCHEMA ) { return false; }
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT request_hash,status,woo_refund_id,amount,line_quantities FROM ' . refunds_table() . ' WHERE order_id = %d',
		$order->get_id()
	) );
	if ( $wpdb->last_error || ! is_array( $rows ) ) { return false; }
	$confirmed = array();
	$quantities = array();
	foreach ( $rows as $refund ) {
		if ( 'confirmed' !== $refund->status ) {
			if ( ! in_array( $refund->status, array( 'requested', 'unknown', 'verified' ), true ) ) { return false; }
			continue;
		}
		if ( ! $refund->woo_refund_id || isset( $confirmed[ (int) $refund->woo_refund_id ] ) ) { return false; }
		$confirmed[ (int) $refund->woo_refund_id ] = $refund;
		$lines = json_decode( (string) $refund->line_quantities, true );
		if ( ! is_array( $lines ) || ! $lines ) { return false; }
		foreach ( $lines as $type_id => $quantity ) {
			$type_id = integer_value( $type_id, 1 );
			$quantity = integer_value( $quantity, 1 );
			if ( null === $type_id || null === $quantity ) { return false; }
			$quantities[ $type_id ] = ( $quantities[ $type_id ] ?? 0 ) + $quantity;
		}
	}
	$seen = array();
	foreach ( $order->get_refunds() as $woo_refund ) {
		$id = (int) $woo_refund->get_id();
		$entry = $confirmed[ $id ] ?? null;
		if ( ! $entry || $woo_refund->get_reason() !== 'LCDA T6 ' . $entry->request_hash
			|| wc_format_decimal( $woo_refund->get_amount(), wc_get_price_decimals() ) !== (string) $entry->amount ) { return false; }
		$seen[ $id ] = true;
	}
	if ( count( $seen ) !== count( $confirmed ) ) { return false; }
	$original = json_decode( (string) $reservation->quantities, true );
	if ( ! is_array( $original ) || ! $original ) { return false; }
	foreach ( $original as $type_id => $quantity ) {
		if ( ( $quantities[ (int) $type_id ] ?? 0 ) > (int) $quantity ) { return false; }
	}
	if ( 'refunded' === $reservation->status ) {
		foreach ( $original as $type_id => $quantity ) {
			if ( ( $quantities[ (int) $type_id ] ?? 0 ) !== (int) $quantity ) { return false; }
		}
	}
	return true;
}

/** Woo must never run an independent stock restoration for coordinator orders. */
function refund_woo_restock_guard( $allowed, $order ) {
	return checkout_woo_stock_guard( $allowed, $order );
}
add_filter( 'woocommerce_can_restore_order_stock', __NAMESPACE__ . '\\refund_woo_restock_guard', PHP_INT_MAX, 2 );

/** Woo admin/REST refund writes cannot bypass the evidence boundary. */
function guard_direct_ticket_refund( $refund, $args ) {
	$order = wc_get_order( (int) ( $args['order_id'] ?? 0 ) );
	if ( ! $order || 'lcda-ticket' !== $order->get_created_via() ) { return; }
	$expected = (string) ( $GLOBALS['lcda_t6_refund_write'] ?? '' );
	if ( ! $expected || (string) $refund->get_reason() !== 'LCDA T6 ' . $expected
		|| ! refund_authorized( (int) $order->get_meta( '_lcda_ticket_event_id' ) ) ) {
		throw new \Exception( 'Los reembolsos de entradas requieren conciliacion T6 y evidencia verificada.' );
	}
}
add_action( 'woocommerce_create_refund', __NAMESPACE__ . '\\guard_direct_ticket_refund', 1, 2 );

function guard_direct_refunded_status( $order ) {
	if ( ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund
		|| 'lcda-ticket' !== $order->get_created_via() || 'refunded' !== $order->get_status() ) { return; }
	if ( empty( $GLOBALS['lcda_t6_refund_write'] ) ) {
		throw new \Exception( 'El estado reembolsado requiere conciliacion T6.' );
	}
}
add_action( 'woocommerce_before_order_object_save', __NAMESPACE__ . '\\guard_direct_refunded_status', 1, 1 );

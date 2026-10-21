<?php
/** Disposable LocalWP clone only. Synthetic payment and gateway evidence stay in this process. */
$load = getenv( 'LCDA_QA_WP_LOAD' );
if ( ! $load || ! is_file( $load ) || false === strpos( str_replace( '\\', '/', $load ), 'lcda-e32-qa-20261009/' ) ) {
	fwrite( STDERR, "Disposable clone path required.\n" );
	exit( 2 );
}
register_shutdown_function( static function () {
	$error = error_get_last();
	if ( $error && E_ERROR === $error['type'] ) { fwrite( STDERR, $error['message'] . ' at ' . $error['file'] . ':' . $error['line'] . "\n" ); }
} );
require $load;

use CasaEventos\Commerce;
use CasaEventos\Core;

function t6_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function t6_evidence( $pre, $evidence, $context ) {
	if ( ! is_array( $evidence ) || 'T6-QA-ONLY' !== ( $evidence['fixture'] ?? '' ) ) { return null; }
	return array_merge( $context, array( 'verified' => true, 'reference' => (string) $evidence['reference'] ) );
}
function t6_fixture_order( $event_id, $parent, $type, $quantity ) {
	global $wpdb;
	$request_hash = hash( 'sha256', wp_generate_uuid4() );
	$order = new WC_Order();
	$order->set_created_via( 'lcda-ticket' );
	$order->set_status( 'pending' );
	$order->set_currency( 'ARS' );
	$order->set_billing_first_name( 'QA' );
	$order->set_billing_last_name( 'T6' );
	$order->set_billing_email( 't6@example.invalid' );
	$item = new WC_Order_Item_Product();
	$item->set_product_id( $parent->get_id() );
	$item->set_variation_id( $type->get_id() );
	$item->set_name( 'General QA' );
	$item->set_quantity( $quantity );
	$item->set_subtotal( 100 * $quantity );
	$item->set_total( 100 * $quantity );
	$item->add_meta_data( '_lcda_ticket_unit_price', '100', true );
	$item->add_meta_data( '_lcda_ticket_type_uuid', $type->get_meta( '_lcda_ticket_uuid' ), true );
	$order->add_item( $item );
	$order->update_meta_data( '_lcda_ticket_event_id', $event_id );
	$order->update_meta_data( '_lcda_ticket_request_hash', $request_hash );
	$order->update_meta_data( '_lcda_ticket_event_snapshot', array( 'title' => 'QA T6', 'start_local' => '2026-11-01 20:00:00' ) );
	$order->calculate_totals( false );
	$inserted = $wpdb->insert( Commerce\reservation_table(), array(
		'event_id' => $event_id, 'request_hash' => $request_hash, 'session_hash' => hash( 'sha256', 't6-session' ),
		'payload_hash' => hash( 'sha256', 't6-payload' ), 'quantities' => wp_json_encode( array( $type->get_id() => $quantity ) ),
		'snapshot' => '{}', 'status' => 'paid', 'created_at' => time(), 'expires_at' => time() + 1200,
		'attempt_hash' => hash( 'sha256', 't6-attempt' ), 'order_id' => $order->get_id(),
		'payment_hash' => hash( 'sha256', 'T6 QA synthetic paid fixture' ),
	) );
	t6_check( 1 === $inserted, 'reservation fixture: ' . $wpdb->last_error );
	$reservation_id = (int) $wpdb->insert_id;
	$order->update_meta_data( '_lcda_ticket_reservation_id', $reservation_id );
	$order->set_date_paid( time() );
	$order->set_status( 'processing' );
	$order->save();
	$issued = Commerce\issue_paid_reservation_tickets( $event_id, $reservation_id, $order->get_id(), hash( 'sha256', 'T6 QA synthetic paid fixture' ) );
	t6_check( ! is_wp_error( $issued ), 'ticket issuance: ' . ( is_wp_error( $issued ) ? $issued->get_error_message() : '' ) );
	return wc_get_order( $order->get_id() );
}
function t6_occupancy( $event_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,status,expires_at,quantities FROM ' . Commerce\reservation_table() . ' WHERE event_id = %d', $event_id ) );
	$refunds = Commerce\confirmed_refund_quantities( $event_id );
	t6_check( ! is_wp_error( $refunds ), 'refund projection unavailable' );
	$held = Commerce\reservation_held( $rows, time(), $refunds );
	t6_check( ! is_wp_error( $held ), 'occupancy projection invalid' );
	return $held;
}
function t6_concurrent_confirm( $order_id, $key, $reference ) {
	$barrier = tempnam( get_temp_dir(), 't6-go-' );
	t6_check( $barrier && unlink( $barrier ), 'concurrency barrier' );
	$env = getenv();
	$env['LCDA_T6_ORDER'] = (string) $order_id;
	$env['LCDA_T6_KEY'] = $key;
	$env['LCDA_T6_REFERENCE'] = $reference;
	$env['LCDA_T6_BARRIER'] = $barrier;
	$commands = array( PHP_BINARY, '-c', php_ini_loaded_file(), __DIR__ . '/qa-refund-worker.php' );
	$processes = array();
	for ( $i = 0; $i < 2; ++$i ) {
		$process = proc_open( $commands, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env );
		t6_check( is_resource( $process ), 'worker start failed' );
		fclose( $pipes[0] );
		$processes[] = array( $process, $pipes );
	}
	sleep( 3 );
	touch( $barrier );
	$results = array();
	foreach ( $processes as $entry ) {
		list( $process, $pipes ) = $entry;
		$out = stream_get_contents( $pipes[1] );
		$err = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); fclose( $pipes[2] );
		$exit = proc_close( $process );
		$match = array();
		$result = preg_match( '/(\\{"(?:status|error)".*\\})/s', $out, $match ) ? json_decode( $match[1], true ) : null;
		t6_check( 0 === $exit && is_array( $result ), 'worker failed: ' . $err . ' ' . $out );
		$results[] = $result;
	}
	unlink( $barrier );
	return $results;
}

t6_check( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'HPOS disabled' );
t6_check( Commerce\install_refunds(), 'refund table unavailable' );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
t6_check( $admin, 'no administrator' );
wp_set_current_user( $admin->ID );
add_filter( 'pre_wp_mail', static fn() => true );
global $wpdb;
$event_id = 0; $product_id = 0; $orders = array();
try {
	$event_id = wp_insert_post( array( 'post_type' => 'casa_evento', 'post_status' => 'draft', 'post_title' => 'QA T6 disposable' ), true );
	t6_check( ! is_wp_error( $event_id ) && $event_id, 'event fixture failed' );
	$start = gmdate( 'Y-m-d H:i:s', time() + 48 * HOUR_IN_SECONDS );
	update_post_meta( $event_id, Core\META_TIMEZONE, 'America/Argentina/Buenos_Aires' );
	update_post_meta( $event_id, Core\META_START, Core\gmt_to_local( $start, 'America/Argentina/Buenos_Aires' ) );
	update_post_meta( $event_id, Core\META_ACCESS_MODE, Core\ACCESS_TICKETS );
	Core\sync_event( $event_id );
	wp_update_post( array( 'ID' => $event_id, 'post_status' => 'publish' ) );
	remove_action( 'woocommerce_before_product_object_save', 'CasaEventos\\Commerce\\guard_product_save' );
	remove_action( 'woocommerce_before_product_variation_object_save', 'CasaEventos\\Commerce\\guard_product_save' );
	$parent = new WC_Product_Variable();
	$parent->set_name( 'QA T6 parent' );
	$parent->set_status( 'private' );
	$parent->set_sku( 'lcda-event-' . Core\Event::get( $event_id )->uuid() );
	$parent->update_meta_data( '_lcda_ticket_event', $event_id );
	$parent->update_meta_data( '_lcda_ticket_event_uuid', Core\Event::get( $event_id )->uuid() );
	$parent->update_meta_data( '_lcda_ticket_schema', '1' );
	$product_id = $parent->save();
	$parent->update_meta_data( '_lcda_ticket_origin', hash_hmac( 'sha256', (string) $product_id, wp_salt( 'auth' ) ) );
	$parent->save();
	$type = new WC_Product_Variation();
	$type->set_parent_id( $product_id );
	$type->set_status( 'private' );
	$type->set_regular_price( '100' );
	$type->set_manage_stock( true );
	$type->set_stock_quantity( 3 );
	$type->update_meta_data( '_lcda_ticket_uuid', wp_generate_uuid4() );
	$type->update_meta_data( '_lcda_ticket_name', 'General QA' );
	$type->update_meta_data( '_lcda_ticket_currency', 'ARS' );
	$type->update_meta_data( '_lcda_ticket_active', '1' );
	$type->update_meta_data( '_lcda_ticket_request_complete', '1' );
	$type->update_meta_data( '_lcda_ticket_maximum', '10' );
	$type->save();
	$type->update_meta_data( '_lcda_ticket_stock_proof', Commerce\stock_proof( $type, $event_id ) );
	$type->save();
	$order = t6_fixture_order( $event_id, $parent, $type, 3 ); $orders[] = $order;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,status,ticket_code FROM ' . Commerce\tickets_table() . ' WHERE order_id = %d ORDER BY id', $order->get_id() ) );
	t6_check( 3 === count( $rows ) && 3 === t6_occupancy( $event_id )['global'], 'initial occupancy' );
	t6_check( Commerce\ticket_orders_reconciled( $parent ), 'T2 rejected verified paid order' );
	$direct = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => '1.00',
		'reason' => 'direct Woo refund', 'refund_payment' => false, 'restock_items' => false ) );
	t6_check( is_wp_error( $direct ) && 0 === count( $order->get_refunds() ), 'direct Woo refund bypassed evidence gate' );
	$manual_status = wc_get_order( $order->get_id() );
	$manual_status->set_status( 'refunded' );
	$status_before_save = $manual_status->get_status();
	$manual_status->save(); // Woo catches the guard exception and leaves the persisted status intact.
	t6_check( 'refunded' === $status_before_save && has_action( 'woocommerce_before_order_object_save', 'CasaEventos\\Commerce\\guard_direct_refunded_status' )
		&& 'processing' === wc_get_order( $order->get_id() )->get_status(), 'manual refunded status bypassed evidence gate' );
	$key1 = wp_generate_uuid4();
	$request = Commerce\request_ticket_refund( $order->get_id(), array( (int) $rows[0]->id ), $key1 );
	t6_check( ! is_wp_error( $request ) && 'requested' === $request['status'] && '100.00' === $request['amount'], 'partial request' );
	t6_check( 3 === t6_occupancy( $event_id )['global'], 'request released capacity' );
	$reference1 = 'partial-' . $key1;
	t6_check( is_wp_error( Commerce\confirm_ticket_refund( $order->get_id(), $key1, array( 'fixture' => 'T6-QA-ONLY', 'reference' => $reference1 ) ) ), 'unverified evidence accepted' );
	t6_check( 3 === t6_occupancy( $event_id )['global'], 'unverified evidence released capacity' );
	$unknown = Commerce\mark_refund_outcome_unknown( $order->get_id(), $key1 );
	t6_check( ! is_wp_error( $unknown ) && 'unknown' === $unknown['status'] && 3 === t6_occupancy( $event_id )['global'], 'unknown outcome released capacity' );
	$programador = get_users( array( 'role' => 'programador', 'number' => 1 ) )[0] ?? null;
	if ( $programador ) {
		wp_set_current_user( $programador->ID );
		t6_check( is_wp_error( Commerce\request_ticket_refund( $order->get_id(), array( (int) $rows[1]->id ), wp_generate_uuid4() ) ), 'Programador could request refund' );
		wp_set_current_user( $admin->ID );
	}
	add_filter( 'casa_eventos/verify_refund_evidence', 't6_evidence', 10, 3 );
	$confirmed = Commerce\confirm_ticket_refund( $order->get_id(), $key1, array( 'fixture' => 'T6-QA-ONLY', 'reference' => $reference1 ) );
	t6_check( ! is_wp_error( $confirmed ) && 'confirmed' === $confirmed['status'], 'partial confirmation: ' . ( is_wp_error( $confirmed ) ? $confirmed->get_error_message() . ' ' . wp_json_encode( $confirmed->get_error_data() ) : '' ) );
	$audit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Commerce\refunds_table() . ' WHERE id = %d', $confirmed['id'] ) );
	t6_check( $audit && 'confirmed' === $audit->status && (int) $audit->woo_refund_id > 0
		&& preg_match( '/^[a-f0-9]{64}$/D', (string) $audit->evidence_hash ), 'refund audit record incomplete' );
	t6_check( 2 === t6_occupancy( $event_id )['global'], 'partial capacity restoration' );
	t6_check( 'revoked' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . Commerce\tickets_table() . ' WHERE id = %d', $rows[0]->id ) ), 'selected ticket not revoked' );
	t6_check( 'issued' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . Commerce\tickets_table() . ' WHERE id = %d', $rows[1]->id ) ), 'other ticket revoked' );
	$replay = Commerce\confirm_ticket_refund( $order->get_id(), $key1, array( 'fixture' => 'T6-QA-ONLY', 'reference' => $reference1 ) );
	t6_check( ! is_wp_error( $replay ) && $replay['replayed'] && 2 === t6_occupancy( $event_id )['global'], 'repeat confirmation altered capacity' );
	t6_check( 1 === count( $order->get_refunds() ), 'repeat confirmation duplicated Woo refund' );
	$reminder = Commerce\reminder_data( $order->get_id() );
	t6_check( ! is_wp_error( $reminder ) && 2 === $reminder['groups'][0]['quantity'], 'partial reminder eligibility' );
	$export = Commerce\export_order_rows( wc_get_order( $order->get_id() ), $event_id );
	t6_check( 1 === count( $export ) && 2 === $export[0][7] && false === strpos( $export[0][13], (string) $rows[0]->ticket_code ), 'partial export quantity' );
	$key2 = wp_generate_uuid4();
	$full = Commerce\request_ticket_refund( $order->get_id(), array( (int) $rows[1]->id, (int) $rows[2]->id ), $key2 );
	t6_check( ! is_wp_error( $full ) && '200.00' === $full['amount'], 'remaining full request' );
	$parallel = t6_concurrent_confirm( $order->get_id(), $key2, 'remaining-' . $key2 );
	t6_check( 1 === count( array_filter( $parallel, static fn( $result ) => 'confirmed' === ( $result['status'] ?? '' ) && empty( $result['replayed'] ) ) ), 'concurrent confirmation did not have one winner' );
	t6_check( 0 === t6_occupancy( $event_id )['global'] && 2 === count( wc_get_order( $order->get_id() )->get_refunds() ), 'concurrent full refund duplicated or failed' );
	t6_check( Commerce\ticket_orders_reconciled( $parent ), 'T2 rejected reconciled refund order' );
	$confirmed = Commerce\confirm_ticket_refund( $order->get_id(), $key2, array( 'fixture' => 'T6-QA-ONLY', 'reference' => 'remaining-' . $key2 ) );
	t6_check( ! is_wp_error( $confirmed ) && $confirmed['replayed'] && 0 === t6_occupancy( $event_id )['global'], 'full refund replay' );
	$reopened = Commerce\reserve( $event_id, array( $type->get_id() => 3 ), wp_generate_uuid4(), time() );
	t6_check( ! is_wp_error( $reopened ) && 'checkout_active' === $reopened['status'],
		'T2 did not admit exactly restored capacity: ' . ( is_wp_error( $reopened ) ? $reopened->get_error_message() : wp_json_encode( $reopened ) ) );
	t6_check( is_wp_error( Commerce\reminder_data( $order->get_id() ) ), 'full refund reminder eligible' );
	$export = Commerce\export_order_rows( wc_get_order( $order->get_id() ), $event_id );
	t6_check( 0 === $export[0][7] && '' === $export[0][13], 'full refund export still shows valid tickets' );
	echo "T6 clone QA passed: HPOS; synthetic full/partial refunds; requested/unknown hold; verifier and Woo gates; concurrent/repeated confirmation; T2 restored capacity; tickets; reminder; export; audit; admin boundary.\n";
} finally {
	remove_filter( 'casa_eventos/verify_refund_evidence', 't6_evidence', 10 );
	foreach ( $orders as $order ) {
		if ( ! $order ) { continue; }
		$wpdb->delete( Commerce\refunds_table(), array( 'order_id' => $order->get_id() ) );
		$wpdb->delete( Commerce\tickets_table(), array( 'order_id' => $order->get_id() ) );
		$wpdb->delete( Commerce\ticket_mail_table(), array( 'order_id' => $order->get_id() ) );
		$wpdb->delete( Commerce\reminder_table(), array( 'order_id' => $order->get_id() ) );
		$wpdb->delete( Commerce\reservation_table(), array( 'order_id' => $order->get_id() ) );
		$order->delete( true );
	}
	if ( $event_id ) { $wpdb->delete( Commerce\reservation_table(), array( 'event_id' => $event_id ) ); }
	if ( $product_id ) { foreach ( wc_get_products( array( 'parent' => $product_id, 'limit' => -1 ) ) as $child ) { $child->delete( true ); } wc_get_product( $product_id )->delete( true ); }
	if ( $event_id ) { wp_delete_post( $event_id, true ); }
}

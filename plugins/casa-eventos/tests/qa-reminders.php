<?php
/** Run against the disposable LocalWP clone only; mail is intercepted. */
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

function t8_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
t8_assert( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'HPOS must be enabled' );
t8_assert( Commerce\install_reminder_table(), 'reminder table unavailable' );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
t8_assert( $admin, 'clone has no administrator' );
wp_set_current_user( $admin->ID );
global $wpdb;
$event_id = 0; $product_id = 0; $orders = array();
$mail_calls = array(); $mail_result = true;
add_filter( 'pre_wp_mail', static function ( $pre, $atts ) use ( &$mail_calls, &$mail_result ) {
	if ( 0 === strpos( (string) ( $atts['subject'] ?? '' ), 'Recordatorio:' ) ) { $mail_calls[] = $atts; }
	return $mail_result;
}, 10, 2 );
function t8_row( $order_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Commerce\reminder_table() . ' WHERE order_id = %d', $order_id ) );
}
function t8_order( $event_id, $parent, $type, $paid ) {
	global $wpdb;
	$order = new WC_Order();
	$order->set_created_via( 'lcda-ticket' );
	$order->set_status( 'pending' );
	$order->set_billing_email( 't8-qa@example.invalid' );
	$order->set_billing_first_name( 'QA' );
	$order->set_billing_last_name( 'T8' );
	$item = new WC_Order_Item_Product();
	$item->set_product_id( $parent->get_id() );
	$item->set_variation_id( $type->get_id() );
	$item->set_name( 'General QA' );
	$item->set_quantity( 1 );
	$item->set_subtotal( 100 );
	$item->set_total( 100 );
	$item->add_meta_data( '_lcda_ticket_unit_price', '100', true );
	$item->add_meta_data( '_lcda_ticket_type_uuid', $type->get_meta( '_lcda_ticket_uuid' ), true );
	$order->add_item( $item );
	$order->set_currency( 'ARS' );
	$request = hash( 'sha256', wp_generate_uuid4() );
	$order->update_meta_data( '_lcda_ticket_event_id', $event_id );
	$order->update_meta_data( '_lcda_ticket_request_hash', $request );
	$order->update_meta_data( '_lcda_ticket_event_snapshot', array( 'title' => 'QA T8', 'start_local' => '2026-11-01 20:00:00' ) );
	$order->save();
	if ( ! $paid ) { return $order; }
	$inserted = $wpdb->insert( Commerce\reservation_table(), array(
		'event_id' => $event_id, 'request_hash' => $request, 'session_hash' => hash( 'sha256', 't8-session' ),
		'payload_hash' => hash( 'sha256', 't8-payload' ), 'quantities' => wp_json_encode( array( $type->get_id() => 1 ) ),
		'snapshot' => '{}', 'status' => 'paid', 'created_at' => time(), 'expires_at' => time() + 1200,
		'attempt_hash' => hash( 'sha256', 't8-attempt' ), 'order_id' => $order->get_id(), 'payment_hash' => hash( 'sha256', 't8-verified' ),
	) );
	t8_assert( 1 === $inserted, 'ledger fixture failed: ' . $wpdb->last_error );
	$reservation_id = $wpdb->insert_id;
	$order->update_meta_data( '_lcda_ticket_reservation_id', $reservation_id );
	$order->set_date_paid( time() );
	$order->set_status( 'processing' );
	$order->save();
	return wc_get_order( $order->get_id() );
}
try {
	$event_id = wp_insert_post( array( 'post_type' => 'casa_evento', 'post_status' => 'draft', 'post_title' => 'QA T8 disposable' ), true );
	t8_assert( ! is_wp_error( $event_id ) && $event_id, 'event fixture failed' );
	$start_gmt = gmdate( 'Y-m-d H:i:s', time() + 48 * HOUR_IN_SECONDS );
	update_post_meta( $event_id, Core\META_TIMEZONE, 'America/Argentina/Buenos_Aires' );
	update_post_meta( $event_id, Core\META_START, Core\gmt_to_local( $start_gmt, 'America/Argentina/Buenos_Aires' ) );
	update_post_meta( $event_id, Core\META_ACCESS_MODE, Core\ACCESS_TICKETS );
	Core\sync_event( $event_id );
	wp_update_post( array( 'ID' => $event_id, 'post_status' => 'publish' ) );
	t8_assert( Core\Event::get( $event_id )->is_published(), 'event not published' );
	// Test-only fixtures bypass the T1 admin write boundary; no purchase is made.
	remove_action( 'woocommerce_before_product_object_save', 'CasaEventos\\Commerce\\guard_product_save' );
	remove_action( 'woocommerce_before_product_variation_object_save', 'CasaEventos\\Commerce\\guard_product_save' );
	$parent = new WC_Product_Variable();
	$parent->set_name( 'QA T8 ticket parent' );
	$parent->set_status( 'private' );
	$parent->set_sku( 'lcda-event-' . Core\Event::get( $event_id )->uuid() );
	$parent->update_meta_data( '_lcda_ticket_event', $event_id );
	$parent->update_meta_data( '_lcda_ticket_event_uuid', Core\Event::get( $event_id )->uuid() );
	$parent->update_meta_data( '_lcda_ticket_schema', '1' );
	$product_id = $parent->save();
	$type = new WC_Product_Variation();
	$type->set_parent_id( $product_id );
	$type->set_status( 'private' );
	$type->set_regular_price( '100' );
	$type->update_meta_data( '_lcda_ticket_uuid', wp_generate_uuid4() );
	$type->save();
	$paid = t8_order( $event_id, $parent, $type, true ); $orders[] = $paid;
	$pending = t8_order( $event_id, $parent, $type, false ); $orders[] = $pending;
	t8_assert( t8_row( $paid->get_id() ) && ! t8_row( $pending->get_id() ), 'paid/pending eligibility failed' );
	t8_assert( abs( (int) t8_row( $paid->get_id() )->due_at - ( Core\gmt_datetime( Core\Event::get( $event_id )->start_gmt() )->getTimestamp() - DAY_IN_SECONDS ) ) <= 2, '24-hour due time wrong' );
	t8_assert( ! is_wp_error( Commerce\reminder_data( $paid->get_id() ) ), 'issued ticket validation failed' );
	t8_assert( is_wp_error( Commerce\reminder_data( $pending->get_id() ) ), 'pending order eligible' );
	Commerce\queue_ticket_reminder( $paid->get_id(), $event_id );
	t8_assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Commerce\reminder_table() . ' WHERE order_id = %d', $paid->get_id() ) ), 'duplicate queue row' );
	$old_due = (int) t8_row( $paid->get_id() )->due_at;
	update_post_meta( $event_id, Core\META_START, Core\gmt_to_local( gmdate( 'Y-m-d H:i:s', time() + 72 * HOUR_IN_SECONDS ), 'America/Argentina/Buenos_Aires' ) );
	Core\sync_event( $event_id );
	t8_assert( (int) t8_row( $paid->get_id() )->due_at > $old_due + 23 * HOUR_IN_SECONDS, 'reschedule did not move due time' );
	update_post_meta( $event_id, Core\META_STATUS, Core\STATUS_CANCELLED );
	Core\sync_event( $event_id );
	t8_assert( 'cancelled' === t8_row( $paid->get_id() )->status && ! wp_next_scheduled( Commerce\REMINDER_HOOK, array( $paid->get_id() ) ), 'cancellation did not clear job' );
	Commerce\send_ticket_reminder( $paid->get_id() );
	t8_assert( 0 === count( $mail_calls ), 'cancelled event mailed' );
	update_post_meta( $event_id, Core\META_STATUS, Core\STATUS_ACTIVE );
	Core\sync_event( $event_id );
	t8_assert( 'pending' === t8_row( $paid->get_id() )->status, 'reactivation did not restore job' );
	foreach ( array( 'America/New_York' => '2027-03-14 12:30:00', 'Europe/Madrid' => '2027-10-31 12:30:00' ) as $zone => $local ) {
		$gmt = Core\local_to_gmt( $local, $zone );
		$start = Core\gmt_datetime( $gmt )->getTimestamp();
		update_post_meta( $event_id, Core\META_TIMEZONE, $zone );
		update_post_meta( $event_id, Core\META_START, $local );
		Core\sync_event( $event_id );
		t8_assert( $start - DAY_IN_SECONDS === Commerce\reminder_due_for_event( $event_id ), 'DST reminder instant wrong' );
		t8_assert( $local === Core\gmt_to_local( $gmt, $zone ), 'DST local round trip wrong' );
		t8_assert( '12:30:00' !== substr( Core\gmt_to_local( gmdate( 'Y-m-d H:i:s', $start - DAY_IN_SECONDS ), $zone ), 11 ), 'DST transition not exercised' );
	}
	// Bring the event inside the 24-hour window. No real email can leave this process.
	update_post_meta( $event_id, Core\META_TIMEZONE, 'America/Argentina/Buenos_Aires' );
	update_post_meta( $event_id, Core\META_START, Core\gmt_to_local( gmdate( 'Y-m-d H:i:s', time() + 23 * HOUR_IN_SECONDS ), 'America/Argentina/Buenos_Aires' ) );
	Core\sync_event( $event_id );
	$wpdb->update( Commerce\reminder_table(), array( 'due_at' => time() - 1 ), array( 'order_id' => $paid->get_id() ) );
	$mail_result = false;
	Commerce\send_ticket_reminder( $paid->get_id() );
	t8_assert( 'retry' === t8_row( $paid->get_id() )->status && 1 === (int) t8_row( $paid->get_id() )->attempts, 'failed mail retry missing' );
	$wpdb->update( Commerce\reminder_table(), array( 'due_at' => time() - 1 ), array( 'order_id' => $paid->get_id() ) );
	$mail_result = true;
	Commerce\send_ticket_reminder( $paid->get_id() );
	t8_assert( 'sent' === t8_row( $paid->get_id() )->status && 2 === count( $mail_calls ), 'successful retry missing' );
	Commerce\send_ticket_reminder( $paid->get_id() );
	t8_assert( 2 === count( $mail_calls ), 'duplicate reminder sent' );
	$html = $mail_calls[1]['message'] ?? '';
	t8_assert( false !== strpos( $html, 'Lugar:' ) && false !== strpos( $html, 'General QA' ) && false !== strpos( $html, 'Fecha y hora:' ), 'reminder content incomplete' );
	$exhausted = t8_order( $event_id, $parent, $type, true ); $orders[] = $exhausted;
	$mail_result = false;
	for ( $attempt = 1; $attempt <= Commerce\REMINDER_MAX_ATTEMPTS; ++$attempt ) {
		$wpdb->update( Commerce\reminder_table(), array( 'due_at' => time() - 1 ), array( 'order_id' => $exhausted->get_id() ) );
		Commerce\send_ticket_reminder( $exhausted->get_id() );
	}
	t8_assert( 'failed' === t8_row( $exhausted->get_id() )->status && Commerce\REMINDER_MAX_ATTEMPTS === (int) t8_row( $exhausted->get_id() )->attempts, 'retry cap failed' );
	$before = count( $mail_calls );
	Commerce\send_ticket_reminder( $exhausted->get_id() );
	t8_assert( $before === count( $mail_calls ), 'failed reminder retried again' );
	$interrupted = t8_order( $event_id, $parent, $type, true ); $orders[] = $interrupted;
	$wpdb->update( Commerce\reminder_table(), array( 'status' => 'sending', 'lease_until' => time() - 1 ), array( 'order_id' => $interrupted->get_id() ) );
	Commerce\send_ticket_reminder( $interrupted->get_id() );
	t8_assert( 'uncertain' === t8_row( $interrupted->get_id() )->status && $before === count( $mail_calls ), 'interrupted send replayed' );
	$refunded = t8_order( $event_id, $parent, $type, true ); $orders[] = $refunded;
	$wpdb->update( Commerce\reservation_table(), array( 'status' => 'refunded' ), array( 'order_id' => $refunded->get_id() ) );
	t8_assert( is_wp_error( Commerce\reminder_data( $refunded->get_id() ) ), 'refunded ticket eligible' );
	$wpdb->update( Commerce\reminder_table(), array( 'due_at' => time() - 1 ), array( 'order_id' => $refunded->get_id() ) );
	Commerce\send_ticket_reminder( $refunded->get_id() );
	t8_assert( $before === count( $mail_calls ) && 'blocked' === t8_row( $refunded->get_id() )->status, 'refunded order mailed' );
	$invalid = t8_order( $event_id, $parent, $type, true ); $orders[] = $invalid;
	$wpdb->update( Commerce\tickets_table(), array( 'status' => 'revoked' ), array( 'order_id' => $invalid->get_id() ) );
	t8_assert( is_wp_error( Commerce\reminder_data( $invalid->get_id() ) ), 'revoked ticket eligible' );
	$wpdb->update( Commerce\reminder_table(), array( 'due_at' => time() - 1 ), array( 'order_id' => $invalid->get_id() ) );
	Commerce\send_ticket_reminder( $invalid->get_id() );
	t8_assert( $before === count( $mail_calls ) && 'blocked' === t8_row( $invalid->get_id() )->status, 'revoked ticket mailed' );
	echo "T8 clone QA passed: HPOS, eligibility, 24h, reschedule, cancel/reactivate, DST, capped retry, interruption, duplicate, refund, invalid ticket, intercepted content.\n";
} catch ( \Throwable $exception ) {
	fwrite( STDERR, $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine() . "\n" );
	throw $exception;
} finally {
	foreach ( $orders as $order ) {
		Commerce\clear_reminder_job( $order->get_id() );
		$wpdb->delete( Commerce\reminder_table(), array( 'order_id' => $order->get_id() ) );
		$wpdb->delete( Commerce\tickets_table(), array( 'order_id' => $order->get_id() ) );
		$wpdb->delete( Commerce\ticket_mail_table(), array( 'order_id' => $order->get_id() ) );
		$wpdb->delete( Commerce\reservation_table(), array( 'order_id' => $order->get_id() ) );
		$order->delete( true );
	}
	remove_filter( 'pre_delete_post', 'CasaEventos\\Commerce\\guard_delete', 10 );
	if ( isset( $type ) && $type->get_id() ) { $type->delete( true ); }
	if ( $product_id ) { wc_get_product( $product_id )->delete( true ); }
	if ( $event_id ) { wp_delete_post( $event_id, true ); }
	add_filter( 'pre_delete_post', 'CasaEventos\\Commerce\\guard_delete', 10, 2 );
	add_action( 'woocommerce_before_product_object_save', 'CasaEventos\\Commerce\\guard_product_save' );
	add_action( 'woocommerce_before_product_variation_object_save', 'CasaEventos\\Commerce\\guard_product_save' );
}

<?php
/** Run only against the disposable LocalWP clone: LCDA_QA_WP_LOAD=... php qa-export.php */
$load = getenv( 'LCDA_QA_WP_LOAD' );
if ( ! $load || ! is_file( $load ) || false === strpos( str_replace( '\\', '/', $load ), 'lcda-e32-qa-20261009/' ) ) {
	fwrite( STDERR, "Disposable clone path required.\n" );
	exit( 2 );
}
require $load;

use CasaEventos\Commerce;

function qa_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function qa_order( $event_id, $status, $types ) {
	$order = new WC_Order();
	$order->set_created_via( 'lcda-ticket' );
	$order->set_status( $status );
	$order->set_billing_first_name( '=HYPERLINK("bad")' );
	$order->set_billing_last_name( 'Prueba' );
	$order->set_billing_email( 'test@example.invalid' );
	$order->set_billing_phone( '+541111111111' );
	$order->set_currency( 'ARS' );
	$order->update_meta_data( '_lcda_ticket_event_id', $event_id );
	foreach ( $types as $name => $quantity ) {
		$item = new WC_Order_Item_Product();
		$item->set_name( $name );
		$item->set_quantity( $quantity );
		$item->set_subtotal( 100 * $quantity );
		$item->set_total( 100 * $quantity );
		$item->add_meta_data( '_lcda_ticket_type_uuid', wp_generate_uuid4(), true );
		$item->add_meta_data( '_lcda_ticket_unit_price', '100', true );
		$order->add_item( $item );
	}
	$order->save();
	if ( 'processing' === $status ) { $order->set_date_paid( time() ); $order->save(); }
	return $order;
}

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
qa_assert( $admin, 'clone has no administrator' );
wp_set_current_user( $admin->ID );
qa_assert( class_exists( 'ZipArchive' ), 'ZIP extension absent' );
qa_assert( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'clone must use HPOS' );
qa_assert( "'\t=cmd" === Commerce\export_safe_text( "\t=cmd" ) && "'@cmd" === Commerce\export_safe_text( '@cmd' ), 'formula prefix escaping failed' );
$event_id = wp_insert_post( array( 'post_type' => 'casa_evento', 'post_status' => 'draft', 'post_title' => 'QA T7 disposable' ), true );
qa_assert( ! is_wp_error( $event_id ) && $event_id, 'event creation failed' );
$orders = array();
$files = array();
global $wpdb;
try {
	qa_assert( Commerce\export_authorized( $event_id ), 'admin authorization failed' );
	require_once dirname( __DIR__ ) . '/inc/admin/export.php';
	ob_start(); \CasaEventos\Admin\render_buyer_export( $event_id ); $form = ob_get_clean();
	qa_assert( false !== strpos( $form, 'lcda_event_buyers_export' ) && false !== strpos( $form, '_wpnonce' ), 'admin form missing action or nonce' );
	$path = tempnam( get_temp_dir(), 'qa-t7-' ); $files[] = $path;
	qa_assert( true === Commerce\export_write_file( $event_id, $path ), 'empty export failed' );
	$zip = new ZipArchive(); qa_assert( true === $zip->open( $path ), 'empty XLSX invalid ZIP' );
	$xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' ); $zip->close();
	qa_assert( substr_count( $xml, '<row ' ) === 1, 'empty XLSX must contain headers only' );
	$paid = qa_order( $event_id, 'processing', array( 'General' => 2, 'Anticipada' => 1 ) ); $orders[] = $paid;
	qa_assert( 'ARS' === wc_get_order( $paid->get_id() )->get_currency(), 'synthetic order currency snapshot was not persisted as ARS' );
	$pending = qa_order( $event_id, 'pending', array( 'General' => 1 ) ); $orders[] = $pending;
	$buyer = Commerce\checkout_buyer( array( 'first_name' => 'QA', 'last_name' => 'Comprador', 'email' => 'qa@example.invalid', 'phone' => '+541111111111', 'newsletter' => '0' ) );
	qa_assert( is_array( $buyer ) && 'qa@example.invalid' === $buyer['email'], 'T3 buyer validation regression' );
	$html = Commerce\ticket_confirmation_html( array( 'order' => $paid, 'site_name' => 'La Casa',
		'event' => array( 'title' => 'QA T7', 'start_local' => '2026-10-22 20:00', 'timezone' => 'America/Argentina/Buenos_Aires' ),
		'groups' => array( array( 'name' => 'General', 'quantity' => 2, 'codes' => array( 'TEST1', 'TEST2' ) ) ) ) );
	qa_assert( false !== strpos( $html, 'TEST1' ) && false !== strpos( $html, 'QA T7' ), 'T5 confirmation rendering regression' );
	$other_event = wp_insert_post( array( 'post_type' => 'casa_evento', 'post_status' => 'draft', 'post_title' => 'QA T7 other' ), true );
	qa_assert( ! is_wp_error( $other_event ) && $other_event, 'other event creation failed' );
	$other = qa_order( $other_event, 'pending', array( 'Other' => 1 ) ); $orders[] = $other;
	$items = array_values( $paid->get_items( 'line_item' ) );
	$table = Commerce\tickets_table();
	foreach ( array( 1, 2 ) as $unit ) {
		$wpdb->insert( $table, array( 'ticket_code' => str_repeat( (string) $unit, 64 ), 'order_id' => $paid->get_id(), 'reservation_id' => 1,
			'event_id' => $event_id, 'ticket_type_id' => 1, 'purchaser_id' => 0, 'order_item_id' => $items[0]->get_id(),
			'unit_number' => $unit, 'status' => 'issued', 'created_gmt' => gmdate( 'Y-m-d H:i:s' ) ) );
	}
	$duplicate = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (ticket_code,order_id,reservation_id,event_id,ticket_type_id,purchaser_id,order_item_id,unit_number,status,created_gmt) VALUES (%s,%d,1,%d,1,0,%d,1,'issued',%s)", str_repeat( '9', 64 ), $paid->get_id(), $event_id, $items[0]->get_id(), gmdate( 'Y-m-d H:i:s' ) ) );
	qa_assert( 0 === $duplicate, 'repeat issuance created a duplicate ticket' );
	qa_assert( 2 === count( Commerce\ticket_records_for_order( $paid->get_id() ) ), 'T5 ticket lookup regression' );
	$path = tempnam( get_temp_dir(), 'qa-t7-' ); $files[] = $path;
	qa_assert( true === Commerce\export_write_file( $event_id, $path ), 'populated export failed' );
	$zip = new ZipArchive(); qa_assert( true === $zip->open( $path ), 'XLSX invalid ZIP' );
	$xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
	foreach ( array( '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/worksheets/sheet1.xml' ) as $part ) {
		$part_xml = $zip->getFromName( $part );
		$part_doc = new DOMDocument();
		qa_assert( false !== $part_xml && $part_doc->loadXML( $part_xml ), 'invalid XLSX package part: ' . $part );
	}
	$zip->close();
	$doc = new DOMDocument(); qa_assert( $doc->loadXML( $xml ), 'worksheet invalid XML' );
	qa_assert( substr_count( $xml, '<row ' ) === 4, 'expected three lines plus header, with no duplicate' );
	qa_assert( 3 === substr_count( $xml, '>ARS<' ), 'XLSX must retain ARS for each exported order line' );
	preg_match( '/<c r="C2".*?<\/c>/', $xml, $formula_cell );
	qa_assert( false !== strpos( $formula_cell[0] ?? '', '&apos;=HYPERLINK' ) && false === strpos( $xml, '<f>' ), 'formula not neutralized' );
	qa_assert( false !== strpos( $xml, 'Emitidas' ) && false !== strpos( $xml, 'Pendiente de emisión' ) && false !== strpos( $xml, 'Reserva pendiente de pago' ), 'issuance states missing' );
	qa_assert( 1 === substr_count( $xml, str_repeat( '1', 64 ) ) && 1 === substr_count( $xml, str_repeat( '2', 64 ) )
		&& 0 === substr_count( $xml, str_repeat( '9', 64 ) ), 'ticket identifiers duplicated or missing' );
	qa_assert( false === strpos( $xml, 'Other' ), 'other event leaked' );
	wp_set_current_user( 0 );
	ob_start(); \CasaEventos\Admin\render_buyer_export( $event_id ); $hidden_form = ob_get_clean();
	qa_assert( '' === $hidden_form, 'anonymous export control visible' );
	$denied_path = tempnam( get_temp_dir(), 'qa-denied-' ); $files[] = $denied_path;
	qa_assert( is_wp_error( Commerce\export_write_file( $event_id, $denied_path ) ), 'anonymous export allowed' );
	echo "T7 clone QA passed: empty, paid/pending, multi-type, codes, scope, authorization, ZIP/XML, formula safety.\n";
} finally {
	$wpdb->delete( Commerce\tickets_table(), array( 'event_id' => $event_id ) );
	foreach ( $orders as $order ) { $order->delete( true ); }
	wp_delete_post( $event_id, true );
	if ( isset( $other_event ) && ! is_wp_error( $other_event ) ) { wp_delete_post( $other_event, true ); }
	foreach ( $files as $file ) { @unlink( $file ); }
}

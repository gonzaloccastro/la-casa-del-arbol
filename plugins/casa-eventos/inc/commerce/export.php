<?php
/** Private, bounded XLSX export for one event. */
namespace CasaEventos\Commerce;

if ( ! defined( 'ABSPATH' ) ) { exit; }

const EXPORT_BATCH_SIZE = 100;

function export_authorized( $event_id ) {
	return $event_id > 0 && 'casa_evento' === get_post_type( $event_id )
		&& current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $event_id );
}

function export_headers() {
	return array( 'Pedido', 'Fecha de compra', 'Nombre', 'Apellido', 'Email', 'Teléfono', 'Tipo de entrada', 'Cantidad', 'Precio unitario', 'Total línea', 'Total pedido (repetido)', 'Moneda', 'Estado de pago', 'Identificadores', 'Estado de emisión', 'Ingreso' );
}

/** Excel treats even text cells beginning with these characters as possible formulas in some import paths. */
function export_safe_text( $value ) {
	$value = (string) $value;
	$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
	return preg_match( '/^\s*[=+\-@]/u', $value ) ? "'" . $value : $value;
}

function export_xml_text( $value ) {
	return htmlspecialchars( export_safe_text( $value ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

function export_cell( $column, $row, $value, $numeric = false ) {
	$address = $column . $row;
	if ( $numeric && is_numeric( $value ) && is_finite( (float) $value ) ) {
		return '<c r="' . $address . '"><v>' . htmlspecialchars( (string) $value, ENT_XML1, 'UTF-8' ) . '</v></c>';
	}
	return '<c r="' . $address . '" t="inlineStr"><is><t xml:space="preserve">' . export_xml_text( $value ) . '</t></is></c>';
}

function export_row_xml( $row_number, array $values ) {
	$xml = '<row r="' . $row_number . '">';
	foreach ( $values as $index => $value ) {
		$column = chr( 65 + $index );
		$xml .= export_cell( $column, $row_number, $value, in_array( $index, array( 0, 7, 8, 9, 10 ), true ) );
	}
	return $xml . '</row>';
}

/** Identifiers are tied to the Woo line ID, not just to a variation. */
function export_ticket_codes( $order_id, $event_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT order_item_id,unit_number,ticket_code,status FROM ' . tickets_table() . ' WHERE order_id = %d AND event_id = %d ORDER BY order_item_id,unit_number,id',
		$order_id, $event_id
	), ARRAY_A );
	$codes = array();
	foreach ( (array) $rows as $record ) {
		$item_id = (int) $record['order_item_id'];
		$unit = (int) $record['unit_number'];
		if ( $item_id <= 0 || $unit <= 0 || isset( $codes[ $item_id ][ $unit ] ) ) { continue; }
		$codes[ $item_id ][ $unit ] = $record;
	}
	return $codes;
}

function export_order_rows( $order, $event_id ) {
	if ( ! $order || 'lcda-ticket' !== $order->get_created_via() || (int) $order->get_meta( '_lcda_ticket_event_id' ) !== $event_id ) { return array(); }
	$codes = export_ticket_codes( $order->get_id(), $event_id );
	$paid = $order->is_paid() && $order->get_date_paid() && in_array( $order->get_status(), array( 'processing', 'completed' ), true );
	$date = $order->get_date_created();
	$created = $date ? $date->date_i18n( 'Y-m-d H:i:s' ) : '';
	$rows = array();
	foreach ( $order->get_items( 'line_item' ) as $item ) {
		if ( ! $item->get_meta( '_lcda_ticket_type_uuid' ) ) { continue; }
		$quantity = (int) $item->get_quantity();
		if ( $quantity <= 0 ) { continue; }
		$line_codes = $codes[ $item->get_id() ] ?? array();
		$issued = array();
		foreach ( $line_codes as $unit => $record ) {
			if ( $unit <= $quantity && 'issued' === $record['status'] ) { $issued[] = $record['ticket_code']; }
		}
		$state = ! $paid ? ( 'pending' === $order->get_status() ? 'Reserva pendiente de pago' : 'Sin pago confirmado' )
			: ( count( $issued ) === $quantity ? 'Emitidas' : ( $issued ? 'Emisión parcial' : 'Pendiente de emisión' ) );
		$unit_price = (string) $item->get_meta( '_lcda_ticket_unit_price' );
		$rows[] = array( $order->get_id(), $created, $order->get_billing_first_name(), $order->get_billing_last_name(),
			$order->get_billing_email(), $order->get_billing_phone(), $item->get_name(), $quantity,
			is_numeric( $unit_price ) ? $unit_price : '', $item->get_total(), $order->get_total(), $order->get_currency(),
			wc_get_order_status_name( $order->get_status() ), implode( ', ', $issued ), $state, '' );
	}
	return $rows;
}

function export_write_file( $event_id, $destination ) {
	if ( ! export_authorized( $event_id ) ) { return error( 'export_forbidden', 'No tenés permiso para exportar compradores de este evento.' ); }
	if ( ! available() || ! class_exists( '\\ZipArchive' ) || (int) get_option( TICKET_SCHEMA_OPTION, 0 ) !== TICKET_SCHEMA ) {
		return error( 'export_unavailable', 'La exportación no está disponible.' );
	}
	$sheet = tempnam( get_temp_dir(), 'lcda-sheet-' );
	if ( ! $sheet ) { return error( 'export_file', 'No se pudo crear el archivo temporal.' ); }
	$handle = fopen( $sheet, 'wb' );
	if ( ! $handle ) { @unlink( $sheet ); return error( 'export_file', 'No se pudo crear el archivo temporal.' ); }
	$success = false;
	try {
		fwrite( $handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>' );
		fwrite( $handle, export_row_xml( 1, export_headers() ) );
		$row_number = 2;
		$page = 1;
		$statuses = array_keys( wc_get_order_statuses() );
		while ( true ) {
			$ids = wc_get_orders( array( 'type' => 'shop_order', 'status' => $statuses, 'limit' => EXPORT_BATCH_SIZE,
				'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC', 'return' => 'ids',
				'meta_query' => array( array( 'key' => '_lcda_ticket_event_id', 'value' => (string) $event_id, 'compare' => '=' ) ) ) );
			if ( ! is_array( $ids ) ) { throw new \RuntimeException( 'order_query' ); }
			foreach ( $ids as $id ) {
				$id = (int) $id;
				foreach ( export_order_rows( wc_get_order( $id ), $event_id ) as $row ) {
					if ( $row_number > 1048576 ) { throw new \RuntimeException( 'row_limit' ); }
					fwrite( $handle, export_row_xml( $row_number++, $row ) );
				}
			}
			if ( count( $ids ) < EXPORT_BATCH_SIZE ) { break; }
			++$page;
		}
		fwrite( $handle, '</sheetData><autoFilter ref="A1:P' . max( 1, $row_number - 1 ) . '"/></worksheet>' );
		fclose( $handle );
		$handle = null;
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) { throw new \RuntimeException( 'zip' ); }
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Compradores" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );
		if ( ! $zip->addFile( $sheet, 'xl/worksheets/sheet1.xml' ) ) { throw new \RuntimeException( 'sheet' ); }
		$success = $zip->close();
	} catch ( \Throwable $exception ) {
		$success = false;
	} finally {
		if ( $handle ) { fclose( $handle ); }
		@unlink( $sheet );
	}
	if ( ! $success ) { @unlink( $destination ); return error( 'export_file', 'No se pudo generar el XLSX.' ); }
	return true;
}

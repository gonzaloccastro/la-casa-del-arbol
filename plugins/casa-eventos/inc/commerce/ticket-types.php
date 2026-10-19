<?php
/** Commercial read model. Woo objects never cross the admin boundary. */
namespace CasaEventos\Commerce;
use CasaEventos\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }

function authorize( $event_id, $caps = array( 'manage_casa_ticket_types' ) ) {
	$post = Core\editable_event_post( $event_id );
	if ( is_wp_error( $post ) ) { return $post; }
	foreach ( $caps as $cap ) {
		if ( ! current_user_can( $cap ) ) { return error( 'forbidden', 'No tenés permiso para esta operación comercial.' ); }
	}
	return true;
}

function parent_product( $event_id ) {
	if ( ! available() ) { return error( 'unavailable', 'WooCommerce no está disponible. Los eventos siguen funcionando; la configuración de entradas requiere WooCommerce.' ); }
	$event = Core\Event::get( $event_id );
	if ( ! $event ) { return error( 'identity', 'Guardá primero el evento con su identidad válida.' ); }
	$items = wc_get_products( array( 'lcda_event' => $event_id, 'limit' => -1, 'status' => array_keys( get_post_stati() ), 'return' => 'objects' ) );
	// Core may materialize default capacity before assigning a new Event UUID.
	if ( ! $event->uuid() ) { return $items ? error( 'identity', 'La relación comercial requiere una identidad válida.' ) : null; }
	// An interrupted create may have persisted its unique SKU before metadata.
	$sku_id = wc_get_product_id_by_sku( 'lcda-event-' . $event->uuid() );
	if ( $sku_id && ! array_filter( $items, static fn( $p ) => $p->get_id() === $sku_id ) ) {
		return error( 'relationship', 'Existe un producto incompleto o de otro evento con esta identidad. Requiere revisión.' );
	}
	if ( count( $items ) > 1 ) { return error( 'relationship', 'Hay productos duplicados para este evento. Requiere revisión.' ); }
	if ( ! $items ) { return null; }
	$product = reset( $items );
	if ( ! $product->is_type( 'variable' ) || 'trash' === $product->get_status()
		|| $event->uuid() !== $product->get_meta( '_lcda_ticket_event_uuid' )
		|| '1' !== (string) $product->get_meta( '_lcda_ticket_schema' ) ) {
		return error( 'relationship', 'La relación comercial es inválida o tiene un esquema desconocido.' );
	}
	return $product;
}

function variations( $parent ) {
	return wc_get_products( array( 'type' => 'variation', 'parent' => $parent->get_id(), 'limit' => -1, 'status' => array_keys( get_post_stati() ), 'return' => 'objects' ) );
}

function type_data( $type ) {
	return array(
		'id' => $type->get_id(), 'uuid' => (string) $type->get_meta( '_lcda_ticket_uuid' ),
		'name' => (string) $type->get_meta( '_lcda_ticket_name' ), 'price' => $type->get_regular_price( 'edit' ),
		'capacity' => $type->get_stock_quantity( 'edit' ), 'active' => '1' === (string) $type->get_meta( '_lcda_ticket_active' ),
		'cutoff' => (string) $type->get_meta( '_lcda_ticket_cutoff' ), 'maximum' => (int) $type->get_meta( '_lcda_ticket_maximum' ),
		'currency' => (string) $type->get_meta( '_lcda_ticket_currency' ),
	);
}

function configuration( $event_id ) {
	$auth = authorize( $event_id );
	if ( is_wp_error( $auth ) ) { return $auth; }
	$parent = parent_product( $event_id );
	if ( is_wp_error( $parent ) ) { return $parent; }
	$event = Core\Event::get( $event_id );
	if ( ! $event->uuid() ) { return error( 'identity', 'Guardá primero el evento con su identidad válida.' ); }
	$types = array();
	foreach ( $parent ? variations( $parent ) : array() as $type ) {
		if ( ! $type->get_meta( '_lcda_ticket_uuid' ) || 'trash' === $type->get_status() ) { return error( 'relationship', 'Hay una variación incompleta o eliminada. Requiere revisión.' ); }
		$row = type_data( $type );
		$own_cutoff = Core\local_to_gmt( $row['cutoff'], $event->timezone() );
		$row['effective_cutoff_gmt'] = $own_cutoff && $event->sales_close_gmt() ? min( $own_cutoff, $event->sales_close_gmt() ) : $event->sales_close_gmt();
		$types[] = $row;
	}
	usort( $types, static fn( $a, $b ) => $a['id'] <=> $b['id'] );
	$data = array( 'schema' => 1, 'event_id' => $event_id, 'event_uuid' => $event->uuid(), 'capacity' => $event->capacity(),
		'event_cutoff_gmt' => $event->sales_close_gmt(),
		'timezone' => $event->timezone(), 'currency' => get_woocommerce_currency(), 'decimals' => wc_get_price_decimals(),
		'parent_id' => $parent ? $parent->get_id() : 0, 'types' => $types,
		'consumption_known' => $parent ? zero_consumption( $parent ) : true );
	$data['state'] = hash_hmac( 'sha256', serialize( $data ), wp_salt( 'nonce' ) . get_current_user_id() . wp_get_session_token() );
	return $data;
}

/** Proof of locally configured, untouched stock, never a sales ledger. */
function stock_proof( $type, $event_id ) {
	return hash_hmac( 'sha256', implode( ':', array( $event_id, $type->get_id(), $type->get_parent_id(),
		$type->get_meta( '_lcda_ticket_uuid' ), $type->get_stock_quantity( 'edit' ) ) ), wp_salt( 'auth' ) );
}

/** Inspect order lines through Woo CRUD, including HPOS, without treating other products as ticket history. */
function has_ticket_order( $parent ) {
	$ids = array( $parent->get_id() );
	foreach ( variations( $parent ) as $type ) { $ids[] = $type->get_id(); }
	$page = 1;
	try {
		do {
			$results = wc_get_orders( array( 'limit' => 50, 'paged' => $page, 'paginate' => true, 'return' => 'objects',
				'status' => array_merge( array_keys( wc_get_order_statuses() ), array( 'trash' ) ) ) );
			if ( ! is_object( $results ) || ! isset( $results->orders, $results->max_num_pages ) || ! is_array( $results->orders ) ) { return true; }
			foreach ( $results->orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) { return true; }
				foreach ( $order->get_items( 'line_item' ) as $item ) {
					if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) || ! method_exists( $item, 'get_variation_id' ) ) { return true; }
					if ( in_array( $item->get_product_id(), $ids, true ) || in_array( $item->get_variation_id(), $ids, true ) ) { return true; }
				}
			}
			++$page;
		} while ( $page <= (int) $results->max_num_pages );
	} catch ( \Throwable $exception ) {
		return true; // An unavailable order history cannot establish zero consumption.
	}
	return false;
}

/** Deliberately conservative about this Event's commercial history, not unrelated orders. */
function zero_consumption( $parent ) {
	if ( ! available() || ! hash_equals( hash_hmac( 'sha256', (string) $parent->get_id(), wp_salt( 'auth' ) ), (string) $parent->get_meta( '_lcda_ticket_origin' ) ) ) { return false; }
	if ( has_ticket_order( $parent ) ) { return false; }
	foreach ( variations( $parent ) as $type ) {
		$proof = (string) $type->get_meta( '_lcda_ticket_stock_proof' );
		if ( ! $proof || ! hash_equals( stock_proof( $type, (int) $parent->get_meta( '_lcda_ticket_event' ) ), $proof )
			|| $type->get_total_sales() || wc_get_held_stock_quantity( $type ) > 0 ) { return false; }
	}
	return true;
}

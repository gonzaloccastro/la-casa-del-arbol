<?php
/** T1 configuration writer. No order writes, stock holds or sales transitions. */
namespace CasaEventos\Commerce;
use CasaEventos\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }

function save_type( $event_id, $type_id, array $input, $state, $request_key ) {
	$auth = authorize( $event_id );
	if ( is_wp_error( $auth ) ) { return $auth; }
	if ( ! is_int( $type_id ) || $type_id < 0 || ! is_string( $state ) || ! is_string( $request_key )
		|| ! preg_match( '/^[a-f0-9-]{36}$/D', $request_key ) ) { return error( 'request', 'La solicitud comercial no es válida.' ); }
	if ( ! available() ) { return parent_product( $event_id ); }
	$lock = acquire( $event_id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	try {
		$GLOBALS['lcda_ticket_writing'] = true;
		return save_type_locked( $event_id, $type_id, $input, $state, $request_key );
	} catch ( \Throwable $exception ) {
		return error( 'partial', 'El guardado comercial se interrumpió. Revisá los datos persistidos antes de continuar.', array( 'partial' => true, 'id' => $event_id ) );
	} finally {
		$GLOBALS['lcda_ticket_writing'] = false;
		// Capacity mutations in this request retain the same connection lock until shutdown.
		if ( empty( $GLOBALS['lcda_capacity_locks'][ $event_id ] ) ) { release( $event_id ); }
	}
}

function save_type_locked( $event_id, $type_id, array $input, $state, $key ) {
	$view = configuration( $event_id );
	if ( is_wp_error( $view ) ) { return $view; }
	$parent = parent_product( $event_id );
	if ( is_wp_error( $parent ) ) { return $parent; }
	$children = $parent ? variations( $parent ) : array();
	$type = null;
	foreach ( $children as $child ) { if ( $child->get_id() === $type_id ) { $type = $child; } }
	if ( $type_id && ( ! $type || 'trash' === $type->get_status() || ! $type->get_meta( '_lcda_ticket_uuid' ) ) ) {
		return error( 'type', 'El tipo no pertenece a este evento o no puede editarse.' );
	}
	foreach ( $children as $child ) {
		if ( '1' !== (string) $child->get_meta( '_lcda_ticket_request_complete' ) ) {
			return error( 'partial', 'Un guardado anterior quedó incompleto. Revisá el evento antes de continuar.', array( 'partial' => true, 'id' => $event_id ) );
		}
	}
	$old = $type ? type_data( $type ) : array();
	$values = normalize_fields( $input, $old, $view['timezone'], $view['decimals'] );
	if ( is_wp_error( $values ) ) { return $values; }
	foreach ( array( 'price' => 'edit_casa_ticket_prices', 'capacity' => 'edit_casa_ticket_capacities' ) as $field => $cap ) {
		if ( ( ! $type || (string) $old[ $field ] !== (string) $values[ $field ] ) && ! current_user_can( $cap ) ) {
			return error( 'forbidden', 'No tenés permiso para modificar precio o cupo.' );
		}
	}
	$hash = hash( 'sha256', serialize( $values ) );
	// Retry precedes stale rejection, but never precedes authorization and payload validation.
	foreach ( $children as $child ) {
		$creation = ! $type_id && $key === $child->get_meta( '_lcda_ticket_create_key' );
		if ( $creation || $key === $child->get_meta( '_lcda_ticket_request_key' ) ) {
			$expected = $child->get_meta( $creation ? '_lcda_ticket_create_hash' : '_lcda_ticket_request_hash' );
			if ( ( $type_id && $child->get_id() !== $type_id ) || $hash !== $expected ) { return error( 'conflict', 'La clave de guardado ya se usó con otros datos.' ); }
			if ( '1' !== $child->get_meta( '_lcda_ticket_request_complete' ) ) { return error( 'partial', 'El guardado anterior quedó incompleto. Revisá el evento.', array( 'partial' => true, 'id' => $event_id ) ); }
			return array( 'id' => $event_id, 'type_id' => $child->get_id(), 'replayed' => true );
		}
	}
	if ( ! hash_equals( $view['state'], $state ) ) { return error( 'stale', 'La configuración cambió. Recargá antes de guardar.' ); }
	foreach ( $children as $child ) {
		if ( $child->get_id() !== $type_id && normalized_name( (string) $child->get_meta( '_lcda_ticket_name' ) ) === normalized_name( $values['name'] ) ) {
			return error( 'duplicate', 'Ya existe un tipo con este nombre en el evento.' );
		}
	}
	if ( $type && $old['currency'] !== $view['currency'] && $old['price'] !== $values['price'] ) { return error( 'currency', 'Cambió la moneda de la tienda. Requiere revisión administrativa, sin conversión automática.' ); }
	if ( $parent && ( ! $type || $values['capacity'] !== $old['capacity'] ) && ! zero_consumption( $parent ) ) {
		return error( 'unknown', 'El consumo es desconocido. No se puede crear ni modificar stock absoluto.' );
	}
	if ( ! $parent ) {
		// Conservative marker also protects capacity if Woo is subsequently deactivated.
		update_option( 'lcda_ticket_event_' . $event_id, 1, false );
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Evento ' . $event_id );
		$parent->set_sku( 'lcda-event-' . $view['event_uuid'] );
		$parent->set_status( 'private' );
		$parent->set_catalog_visibility( 'hidden' );
		$parent->set_manage_stock( false );
		$parent->update_meta_data( '_lcda_ticket_event', $event_id );
		$parent->update_meta_data( '_lcda_ticket_event_uuid', $view['event_uuid'] );
		$parent->update_meta_data( '_lcda_ticket_schema', '1' );
		if ( ! $parent->save() ) { throw new \RuntimeException( 'Parent save failed' ); }
		$parent->update_meta_data( '_lcda_ticket_origin', hash_hmac( 'sha256', (string) $parent->get_id(), wp_salt( 'auth' ) ) );
		$parent->save();
	}
	$new_type = ! $type;
	if ( $new_type ) {
		$type = new \WC_Product_Variation();
		$type->set_parent_id( $parent->get_id() );
		$type->set_sku( 'lcda-type-' . hash( 'sha256', $view['event_uuid'] . ':' . $key ) );
		$type->update_meta_data( '_lcda_ticket_uuid', wp_generate_uuid4() );
		$type->update_meta_data( '_lcda_ticket_currency', $view['currency'] );
		$type->update_meta_data( '_lcda_ticket_create_key', $key );
		$type->update_meta_data( '_lcda_ticket_create_hash', $hash );
		$type->set_attributes( array( 'tipo' => $type->get_meta( '_lcda_ticket_uuid' ) ) );
		$type->set_manage_stock( true );
		$type->set_backorders( 'no' );
		$type->set_virtual( true );
		$type->set_status( 'private' );
	}
	if ( $new_type || $old['price'] !== $values['price'] ) {
		$type->set_regular_price( $values['price'] );
		$type->set_price( $values['price'] );
	}
	$type->set_name( $values['name'] );
	if ( $new_type || $values['capacity'] !== $old['capacity'] ) { $type->set_stock_quantity( $values['capacity'] ); }
	foreach ( array( 'name', 'active', 'cutoff', 'maximum' ) as $field ) {
		$type->update_meta_data( '_lcda_ticket_' . $field, 'active' === $field ? ( $values[ $field ] ? '1' : '0' ) : $values[ $field ] );
	}
	$type->update_meta_data( '_lcda_ticket_cutoff_gmt', Core\local_to_gmt( $values['cutoff'], $view['timezone'] ) );
	$type->update_meta_data( '_lcda_ticket_request_key', $key );
	$type->update_meta_data( '_lcda_ticket_request_hash', $hash );
	$type->update_meta_data( '_lcda_ticket_request_complete', '0' );
	// CRUD hooks are allowed only inside this writer; external Woo edits cannot bypass it.
	$GLOBALS['lcda_ticket_writing'] = true;
	try {
		if ( ! $type->save() ) { throw new \RuntimeException( 'Type save failed' ); }
		if ( $new_type || $values['capacity'] !== $old['capacity'] ) {
			$type->update_meta_data( '_lcda_ticket_stock_proof', stock_proof( $type, $event_id ) );
			$type->save();
		}
		$options = array();
		foreach ( variations( $parent ) as $child ) { $options[] = $child->get_meta( '_lcda_ticket_uuid' ); }
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Tipo' );
		$attribute->set_options( $options );
		$attribute->set_visible( false );
		$attribute->set_variation( true );
		$parent->set_attributes( array( $attribute ) );
		$parent->save();
	} finally { $GLOBALS['lcda_ticket_writing'] = false; }
	$fresh = wc_get_product( $type->get_id() );
	$verified_parent = parent_product( $event_id );
	if ( ! owns_lock( $event_id ) || ! $fresh || is_wp_error( $verified_parent ) || ! $verified_parent
		|| $verified_parent->get_id() !== $fresh->get_parent_id()
		|| normalize_fields( array(), type_data( $fresh ), $view['timezone'], $view['decimals'] ) !== $values ) {
		throw new \RuntimeException( 'Persistence verification failed' );
	}
	$GLOBALS['lcda_ticket_writing'] = true;
	$fresh->update_meta_data( '_lcda_ticket_request_complete', '1' );
	if ( ! $fresh->save() ) { throw new \RuntimeException( 'Receipt save failed' ); }
	return array( 'id' => $event_id, 'type_id' => $type->get_id(), 'replayed' => false );
}

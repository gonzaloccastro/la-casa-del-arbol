<?php
/** Configuration exclusion only. No checkout holds, reservations or stock coordinator. */
namespace CasaEventos\Commerce;
use CasaEventos\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }

function lock_name( $event_id ) {
	global $wpdb;
	return 'lcda-config-' . md5( $wpdb->prefix . ':' . $event_id );
}

/** MySQL connection-owned lock. No expiring lease or new table. Fail closed. */
function acquire( $event_id ) {
	global $wpdb;
	if ( ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] ) ) {
		return owns_lock( $event_id ) ? true : error( 'busy', 'Se perdió el bloqueo de configuración. Recargá antes de reintentar.' );
	}
	if ( ! isset( $wpdb ) || '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', lock_name( $event_id ) ) ) ) {
		return error( 'busy', 'Otro guardado está en curso o no se pudo bloquear la configuración. Recargá antes de reintentar.' );
	}
	$GLOBALS['lcda_config_locks'][ $event_id ] = true;
	return true;
}

function owns_lock( $event_id ) {
	global $wpdb;
	return isset( $wpdb ) && '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', lock_name( $event_id ) ) );
}

function release( $event_id ) {
	global $wpdb;
	if ( ! empty( $GLOBALS['lcda_config_locks'][ $event_id ] ) ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', lock_name( $event_id ) ) );
		unset( $GLOBALS['lcda_config_locks'][ $event_id ] );
	}
}
function release_all() { foreach ( array_keys( $GLOBALS['lcda_config_locks'] ?? array() ) as $id ) { release( $id ); } }
add_action( 'shutdown', __NAMESPACE__ . '\\release_all' );

function capacity_allowed( $result, $id, $new, $old ) {
	if ( is_wp_error( $result ) || false === $result ) { return $result; }
	// Registry is a conservative guard even when the optional dependency is inactive.
	$registered = get_option( 'lcda_ticket_event_' . $id, false );
	if ( ! available() ) { return $registered ? error( 'unknown', 'No se puede verificar el consumo comercial. El aforo se conserva.' ) : $result; }
	$lock = acquire( $id );
	if ( is_wp_error( $lock ) ) { return $lock; }
	$GLOBALS['lcda_capacity_locks'][ $id ] = true;
	// Retained until request shutdown: protects the ensuing metadata write too.
	$parent = parent_product( $id );
	// Also serialize a first type creation against an initially non-commercial Event.
	if ( ! $parent && ! $registered ) { return $result; }
	$auth = authorize( $id, array( 'edit_casa_ticket_capacities' ) );
	if ( is_wp_error( $auth ) ) { return $auth; }
	if ( is_wp_error( $parent ) || ! $parent ) { return error( 'unknown', 'La relación comercial requiere revisión; no se modifica el aforo.' ); }
	$new = null === $new || '' === $new ? Core\default_capacity() : integer_value( $new, 1 );
	if ( null === $new ) { return error( 'capacity', 'El aforo no es válido.' ); }
	if ( $new < Core\Event::get( $id )->capacity() && ! zero_consumption( $parent ) ) { return error( 'unknown', 'No se puede reducir el aforo: el consumo comercial es desconocido.' ); }
	return $result;
}
add_filter( 'casa_eventos/capacity_write_allowed', __NAMESPACE__ . '\\capacity_allowed', 10, 4 );

function before_event_write( $result, $id, $fields ) {
	if ( ! $id || ! array_key_exists( 'capacity', $fields ) ) { return $result; }
	$event = Core\Event::get( $id );
	if ( ! $event || (string) $event->capacity() === (string) $fields['capacity'] ) { return $result; }
	return capacity_allowed( $result, $id, $fields['capacity'], $event->capacity() );
}
add_filter( 'casa_eventos/before_event_write', __NAMESPACE__ . '\\before_event_write', 10, 3 );

function validation_guard( $result, $data ) {
	if ( empty( $data['event_id'] ) ) { return $result; }
	$guard = before_event_write( true, (int) $data['event_id'], array( 'capacity' => $data['capacity'] ?? null ) );
	if ( is_wp_error( $guard ) ) { $result['errors']['commercial_capacity'] = $guard->get_error_message(); }
	return $result;
}
add_filter( 'casa_eventos/validate_event', __NAMESPACE__ . '\\validation_guard', 10, 2 );

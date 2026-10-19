<?php
/** Strict values before any commercial persistence. */
namespace CasaEventos\Commerce;
use CasaEventos\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }

function integer_value( $value, $min, $max = 2147483647 ) {
	if ( ! is_int( $value ) && ! is_string( $value ) ) { return null; }
	if ( ! preg_match( '/^(0|[1-9][0-9]*)$/D', (string) $value ) || strlen( (string) $value ) > 10 ) { return null; }
	$value = (int) $value;
	return $value >= $min && $value <= $max ? $value : null;
}

function normalize_fields( array $input, array $old, $timezone, $decimals ) {
	$allowed = array( 'name', 'price', 'capacity', 'active', 'cutoff', 'maximum' );
	if ( array_diff( array_keys( $input ), $allowed ) ) { return error( 'fields', 'La solicitud contiene campos no autorizados.' ); }
	$out = array_merge( array( 'name' => '', 'price' => '', 'capacity' => null, 'active' => false, 'cutoff' => '', 'maximum' => 10 ), $old );
	foreach ( $input as $key => $value ) {
		if ( ! is_scalar( $value ) ) { return error( 'fields', 'El valor del campo no es válido.' ); }
		$out[ $key ] = $value;
	}
	if ( ! is_string( $out['name'] ) || strip_tags( $out['name'] ) !== $out['name'] ) { return error( 'name', 'El nombre debe ser texto sin HTML.' ); }
	$out['name'] = trim( preg_replace( '/\s+/u', ' ', $out['name'] ) );
	if ( '' === $out['name'] || ( function_exists( 'mb_strlen' ) ? mb_strlen( $out['name'] ) : strlen( $out['name'] ) ) > 120 ) { return error( 'name', 'El nombre es obligatorio y admite hasta 120 caracteres.' ); }
	$unchanged_price = isset( $old['price'] ) && ( ! array_key_exists( 'price', $input )
		|| ( is_string( $out['price'] ) && str_replace( ',', '.', $out['price'] ) === (string) $old['price'] ) );
	if ( $unchanged_price ) {
		$out['price'] = (string) $old['price'];
	} else {
		if ( ! is_string( $out['price'] ) || ! preg_match( '/^\d{1,12}(?:[.,]\d{1,' . max( 1, (int) $decimals ) . '})?$/D', $out['price'] ) ) { return error( 'price', 'Ingresá un precio decimal no negativo, sin separador de miles.' ); }
		$parts = explode( '.', str_replace( ',', '.', $out['price'] ) );
		if ( strlen( $parts[1] ?? '' ) > $decimals ) { return error( 'price', 'El precio excede los decimales de la moneda.' ); }
		$out['price'] = ( ltrim( $parts[0], '0' ) ?: '0' ) . ( $decimals ? '.' . str_pad( $parts[1] ?? '', $decimals, '0' ) : '' );
	}
	$out['capacity'] = integer_value( $out['capacity'], 0 );
	$out['maximum'] = integer_value( $out['maximum'], 1, 10 );
	if ( null === $out['capacity'] || null === $out['maximum'] ) { return error( 'quantity', 'El cupo debe ser entero no negativo y el máximo entre 1 y 10.' ); }
	if ( ! in_array( $out['active'], array( true, false, '0', '1' ), true ) ) { return error( 'active', 'El estado del tipo no es válido.' ); }
	$out['active'] = true === $out['active'] || '1' === $out['active'];
	if ( $out['active'] && ! preg_match( '/[1-9]/', $out['price'] ) ) { return error( 'free', 'La activación de tipos gratuitos requiere definir la política comercial.' ); }
	if ( ! is_string( $out['cutoff'] ) ) { return error( 'cutoff', 'El corte no es válido.' ); }
	$out['cutoff'] = str_replace( 'T', ' ', $out['cutoff'] );
	if ( 16 === strlen( $out['cutoff'] ) ) { $out['cutoff'] .= ':00'; }
	if ( '' !== $out['cutoff'] && ! Core\local_datetime( $out['cutoff'], $timezone ) ) { return error( 'cutoff', 'La fecha de corte no es válida en la zona del evento.' ); }
	return array_intersect_key( $out, array_flip( $allowed ) );
}

function normalized_name( $name ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
}

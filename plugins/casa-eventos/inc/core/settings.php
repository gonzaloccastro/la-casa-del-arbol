<?php
/**
 * Plugin settings: the single venue (V1).
 *
 * The venue name and address are the authoritative location for every
 * event. There is no per-event venue override in V1. The WhatsApp number is
 * deliberately not a setting: it stays in the theme's CTA menu location.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default settings, used until the settings screen is saved.
 *
 * @return array<string,string>
 */
function default_settings() {
	return array(
		'venue_name'    => 'La Casa del Árbol',
		'venue_address' => 'Av. Córdoba 5217',
	);
}

/**
 * Saved settings merged over the defaults.
 *
 * @return array<string,string>
 */
function settings() {
	$saved = get_option( SETTINGS, array() );
	return array_merge( default_settings(), is_array( $saved ) ? array_intersect_key( $saved, default_settings() ) : array() );
}

/**
 * Venue name.
 *
 * @return string
 */
function venue_name() {
	return (string) settings()['venue_name'];
}

/**
 * Venue address.
 *
 * @return string
 */
function venue_address() {
	return (string) settings()['venue_address'];
}

/**
 * Sanitize the settings array.
 *
 * @param mixed $input Submitted value.
 * @return array<string,string>
 */
function sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$clean = array();
	foreach ( default_settings() as $key => $default ) {
		$value         = isset( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : '';
		$clean[ $key ] = '' === $value ? $default : $value;
	}
	return $clean;
}

/**
 * Register the option with the Settings API.
 */
function register_settings() {
	register_setting(
		'casa_eventos',
		SETTINGS,
		array(
			'type'              => 'object',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_settings',
			'default'           => default_settings(),
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_settings' );

<?php
/**
 * Registered Event meta.
 *
 * Editorial fields are exposed as typed REST meta (the block editor panels
 * save them in the same request as the post), sanitized, authenticated per
 * event and revisioned. Identity and derived fields are registered for
 * typing and sanitizing only: never exposed for editing, never revisioned.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize a flag to the stored '1' / '0'.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function to_flag( $value ) {
	if ( is_string( $value ) ) {
		return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true ) ? '1' : '0';
	}
	return $value ? '1' : '0';
}

/**
 * Sanitize a local datetime: canonical 'Y-m-d H:i:s', or '' when empty or
 * invalid (invalid input is rejected earlier by validation, with a message).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_local_datetime( $value ) {
	$parsed = parse_local( $value );
	return null === $parsed ? '' : $parsed;
}

/**
 * Sanitize a GMT datetime (derived fields).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_gmt_datetime( $value ) {
	$parsed = parse_local( $value );
	return null === $parsed ? '' : $parsed;
}

/**
 * Sanitize the access mode.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_access_mode( $value ) {
	return in_array( $value, access_modes(), true ) ? $value : '';
}

/**
 * Sanitize the entry kind.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_entry_kind( $value ) {
	return in_array( $value, entry_kinds(), true ) ? $value : '';
}

/**
 * Sanitize the entry label (short plain text).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_entry_label( $value ) {
	return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
}

/**
 * Sanitize the external URL: http(s) only.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_external_url( $value ) {
	if ( ! is_string( $value ) || ! is_http_url( $value ) ) {
		return '';
	}
	return esc_url_raw( trim( $value ), array( 'http', 'https' ) );
}

/**
 * Sanitize capacity: a positive integer; blank or invalid becomes the default.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function sanitize_capacity( $value ) {
	$parsed = parse_capacity( $value );
	return is_int( $parsed ) ? $parsed : default_capacity();
}

/**
 * Sanitize the operational status.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_status( $value ) {
	return normalize_status( $value );
}

/**
 * Sanitize a UUID (lowercase v4 shape) or ''.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_uuid( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = strtolower( trim( $value ) );
	return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value ) ? $value : '';
}

/**
 * Sanitize a timezone identifier or ''.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_timezone( $value ) {
	return is_timezone_identifier( $value ) ? $value : '';
}

/**
 * Auth callback for editorial meta: the user can edit this event.
 *
 * @param bool   $allowed   Default.
 * @param string $meta_key  Meta key.
 * @param int    $object_id Post ID.
 * @param int    $user_id   User ID.
 * @return bool
 */
function can_edit_event_meta( $allowed, $meta_key, $object_id, $user_id ) {
	return user_can( $user_id, 'edit_post', $object_id );
}

/**
 * Editorial meta definitions: key => [type, sanitize, schema extras, default].
 *
 * @return array<string,array>
 */
function editorial_meta_definitions() {
	$datetime_pattern = '^$|^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$';

	return array(
		META_START        => array(
			'type'     => 'string',
			'sanitize' => __NAMESPACE__ . '\\sanitize_local_datetime',
			'schema'   => array( 'pattern' => $datetime_pattern ),
		),
		META_END          => array(
			'type'     => 'string',
			'sanitize' => __NAMESPACE__ . '\\sanitize_local_datetime',
			'schema'   => array( 'pattern' => $datetime_pattern ),
		),
		META_SALES_CLOSE  => array(
			'type'     => 'string',
			'sanitize' => __NAMESPACE__ . '\\sanitize_local_datetime',
			'schema'   => array( 'pattern' => $datetime_pattern ),
		),
		META_ACCESS_MODE  => array(
			'type'     => 'string',
			'sanitize' => __NAMESPACE__ . '\\sanitize_access_mode',
			'schema'   => array( 'enum' => array_merge( array( '' ), access_modes() ) ),
		),
		META_ENTRY_KIND   => array(
			'type'     => 'string',
			'sanitize' => __NAMESPACE__ . '\\sanitize_entry_kind',
			'schema'   => array( 'enum' => array_merge( array( '' ), entry_kinds() ) ),
		),
		META_ENTRY_LABEL  => array(
			'type'     => 'string',
			'sanitize' => __NAMESPACE__ . '\\sanitize_entry_label',
			'schema'   => array( 'maxLength' => 120 ),
		),
		META_EXTERNAL_URL => array(
			'type'     => 'string',
			'sanitize' => __NAMESPACE__ . '\\sanitize_external_url',
			'schema'   => array( 'maxLength' => 2000 ),
		),
		META_CAPACITY     => array(
			'type'     => 'integer',
			'sanitize' => __NAMESPACE__ . '\\sanitize_capacity',
			'schema'   => array(),
			'default'  => default_capacity(),
		),
		META_STATUS       => array(
			'type'     => 'string',
			'sanitize' => __NAMESPACE__ . '\\sanitize_status',
			'schema'   => array( 'enum' => statuses() ),
			'default'  => STATUS_ACTIVE,
			// Operational state, not content: restoring a revision must never
			// cancel or reactivate an event (reactivation is admin-only and
			// cancellation fires lifecycle actions).
			'revisions' => false,
		),
		META_LISTED       => array(
			'type'     => 'boolean',
			'sanitize' => __NAMESPACE__ . '\\to_flag',
			'schema'   => array(),
			'default'  => true,
		),
		META_FEATURED     => array(
			'type'     => 'boolean',
			'sanitize' => __NAMESPACE__ . '\\to_flag',
			'schema'   => array(),
			'default'  => false,
		),
	);
}

/**
 * Editor-facing label of an editorial field (validation messages).
 *
 * @param string $key Meta key.
 * @return string
 */
function meta_field_label( $key ) {
	$labels = array(
		META_START        => __( 'Inicio', 'casa-eventos' ),
		META_END          => __( 'Fin', 'casa-eventos' ),
		META_SALES_CLOSE  => __( 'Cierre de venta', 'casa-eventos' ),
		META_ACCESS_MODE  => __( 'Modalidad de acceso', 'casa-eventos' ),
		META_ENTRY_KIND   => __( 'Tipo de entrada', 'casa-eventos' ),
		META_ENTRY_LABEL  => __( 'Texto de entrada', 'casa-eventos' ),
		META_EXTERNAL_URL => __( 'Enlace de venta externa', 'casa-eventos' ),
		META_CAPACITY     => __( 'Aforo', 'casa-eventos' ),
		META_STATUS       => __( 'Estado', 'casa-eventos' ),
		META_LISTED       => __( 'Visible en la Agenda', 'casa-eventos' ),
		META_FEATURED     => __( 'Destacado en la Home', 'casa-eventos' ),
	);
	return $labels[ $key ] ?? $key;
}

/**
 * Identity and derived meta definitions: key => [type, sanitize].
 *
 * @return array<string,array>
 */
function system_meta_definitions() {
	return array(
		META_UUID          => array( 'string', __NAMESPACE__ . '\\sanitize_uuid' ),
		META_TIMEZONE      => array( 'string', __NAMESPACE__ . '\\sanitize_timezone' ),
		META_START_GMT     => array( 'string', __NAMESPACE__ . '\\sanitize_gmt_datetime' ),
		META_END_GMT       => array( 'string', __NAMESPACE__ . '\\sanitize_gmt_datetime' ),
		META_ENDS_GMT      => array( 'string', __NAMESPACE__ . '\\sanitize_gmt_datetime' ),
		META_CANCELLED_GMT => array( 'string', __NAMESPACE__ . '\\sanitize_gmt_datetime' ),
	);
}

/**
 * Register every Event meta key.
 */
function register_meta() {
	foreach ( editorial_meta_definitions() as $key => $def ) {
		$args = array(
			'type'              => $def['type'],
			'single'            => true,
			'sanitize_callback' => $def['sanitize'],
			'auth_callback'     => __NAMESPACE__ . '\\can_edit_event_meta',
			'revisions_enabled' => $def['revisions'] ?? true,
			'show_in_rest'      => array(
				'schema' => array_merge( array( 'type' => $def['type'] ), $def['schema'] ),
			),
		);
		if ( array_key_exists( 'default', $def ) ) {
			$args['default'] = $def['default'];
		}
		register_post_meta( POST_TYPE, $key, $args );
	}

	foreach ( system_meta_definitions() as $key => $def ) {
		register_post_meta(
			POST_TYPE,
			$key,
			array(
				'type'              => $def[0],
				'single'            => true,
				'sanitize_callback' => $def[1],
				'auth_callback'     => '__return_false',
				'show_in_rest'      => false,
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_meta', 6 );

/**
 * Configuration of the block editor panels: the REST meta names of the
 * editorial fields, the defaults and the option labels. The admin layer
 * passes it to the panel script as is, so meta keys stay defined here.
 *
 * @return array
 */
function editor_panel_config() {
	$options = static function ( array $labels ) {
		$list = array();
		foreach ( $labels as $value => $label ) {
			$list[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $list;
	};
	$taxonomy = get_taxonomy( TAXONOMY );

	return array(
		'postType'         => POST_TYPE,
		'taxonomyRestBase' => $taxonomy && ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : TAXONOMY,
		'keys'             => array(
			'start'       => META_START,
			'end'         => META_END,
			'accessMode'  => META_ACCESS_MODE,
			'entryKind'   => META_ENTRY_KIND,
			'entryLabel'  => META_ENTRY_LABEL,
			'externalUrl' => META_EXTERNAL_URL,
			'capacity'    => META_CAPACITY,
			'salesClose'  => META_SALES_CLOSE,
			'status'      => META_STATUS,
			'listed'      => META_LISTED,
			'featured'    => META_FEATURED,
		),
		'values'           => array(
			'external'  => ACCESS_EXTERNAL,
			'tickets'   => ACCESS_TICKETS,
			'cancelled' => STATUS_CANCELLED,
		),
		'defaults'         => array(
			'capacity'          => default_capacity(),
			'salesCloseMinutes' => default_sales_close_offset_minutes(),
			'durationMinutes'   => default_duration_minutes(),
			'timezone'          => timezone_for_new_event(),
		),
		'accessModes'      => $options( access_mode_labels() ),
		'entryKinds'       => $options( entry_kind_labels() ),
		'statuses'         => $options( status_labels() ),
		'canReactivate'    => current_user_can( reactivate_capability() ),
	);
}

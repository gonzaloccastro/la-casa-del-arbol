<?php
/**
 * Event validation rules (pure: they take the resulting data, not a request).
 *
 * - Format errors block every save (drafts included): unparseable dates,
 *   unknown enumeration values, an invalid URL or capacity, and reactivation
 *   of a cancelled event by a non-administrator.
 * - Completeness errors block only publishing/scheduling. Drafts may stay
 *   incomplete.
 * - Warnings never block.
 *
 * The enforcement layer (enforcement.php) builds the data from the request
 * and stored values, and turns errors into a WP_Error.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a post status is a publishing target that requires complete data.
 *
 * @param string $post_status Post status.
 * @return bool
 */
function status_requires_complete_data( $post_status ) {
	return in_array( $post_status, array( 'publish', 'future' ), true );
}

/**
 * Whether a value is an http(s) URL.
 *
 * @param mixed $url Raw value.
 * @return bool
 */
function is_http_url( $url ) {
	if ( ! is_string( $url ) || '' === trim( $url ) ) {
		return false;
	}
	$url = trim( $url );
	if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return false;
	}
	$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
	return in_array( $scheme, array( 'http', 'https' ), true ) && '' !== (string) parse_url( $url, PHP_URL_HOST );
}

/**
 * Parse a capacity value.
 *
 * @param mixed $value Raw value.
 * @return int|null|false Integer ≥ 1, null when blank (the default applies), false when invalid.
 */
function parse_capacity( $value ) {
	if ( null === $value || '' === $value ) {
		return null;
	}
	if ( is_int( $value ) ) {
		return $value >= 1 ? $value : false;
	}
	if ( is_string( $value ) && preg_match( '/^\s*\d+\s*$/', $value ) ) {
		$int = (int) trim( $value );
		return $int >= 1 ? $int : false;
	}
	if ( is_float( $value ) && floor( $value ) === $value && $value >= 1 ) {
		return (int) $value;
	}
	return false;
}

/**
 * Validate the resulting state of an event.
 *
 * @param array $data {
 *     @type string $target_status   Post status after the save.
 *     @type string $title           Post title.
 *     @type mixed  $start           Local start.
 *     @type mixed  $end             Local end.
 *     @type mixed  $sales_close     Local sales cutoff.
 *     @type mixed  $access_mode     Access mode.
 *     @type mixed  $entry_kind      Entry kind.
 *     @type mixed  $external_url    External URL.
 *     @type mixed  $capacity        Capacity.
 *     @type mixed  $status          Operational status after the save.
 *     @type string $previous_status Stored operational status before the save ('' if none).
 *     @type bool   $can_reactivate  Whether the current user may reactivate a cancelled event.
 *     @type int[]  $category_ids    Category term IDs after the save.
 *     @type bool   $listed          Listed flag.
 *     @type bool   $featured        Featured flag.
 *     @type string $timezone        Event timezone.
 *     @type int    $duration        Default duration (minutes).
 *     @type string $now_gmt         Current instant (GMT), for warnings.
 * }
 * @return array{errors:array<string,string>,warnings:array<string,string>}
 */
function validate_event_data( array $data ) {
	$data = array_merge(
		array(
			'target_status'   => 'draft',
			'title'           => '',
			'start'           => '',
			'end'             => '',
			'sales_close'     => '',
			'access_mode'     => '',
			'entry_kind'      => '',
			'external_url'    => '',
			'capacity'        => null,
			'status'          => STATUS_ACTIVE,
			'previous_status' => '',
			'can_reactivate'  => false,
			'category_ids'    => array(),
			'listed'          => true,
			'featured'        => false,
			'timezone'        => FALLBACK_TIMEZONE,
			'duration'        => 180,
			'now_gmt'         => '',
		),
		$data
	);

	$errors   = array();
	$warnings = array();

	// Format rules: every save.
	$start       = parse_local( $data['start'] );
	$end         = parse_local( $data['end'] );
	$sales_close = parse_local( $data['sales_close'] );

	if ( null === $start ) {
		$errors['start_invalid'] = __( 'La fecha y hora de inicio no es válida.', 'casa-eventos' );
	}
	if ( null === $end ) {
		$errors['end_invalid'] = __( 'La fecha y hora de fin no es válida.', 'casa-eventos' );
	}
	if ( null === $sales_close ) {
		$errors['sales_close_invalid'] = __( 'El cierre de venta no es una fecha válida.', 'casa-eventos' );
	}
	if ( '' !== (string) $data['access_mode'] && ! in_array( $data['access_mode'], access_modes(), true ) ) {
		$errors['access_mode_invalid'] = __( 'La modalidad de acceso no es válida.', 'casa-eventos' );
	}
	if ( '' !== (string) $data['entry_kind'] && ! in_array( $data['entry_kind'], entry_kinds(), true ) ) {
		$errors['entry_kind_invalid'] = __( 'El tipo de entrada no es válido.', 'casa-eventos' );
	}
	if ( ! in_array( $data['status'], statuses(), true ) ) {
		$errors['status_invalid'] = __( 'El estado del evento no es válido.', 'casa-eventos' );
	}
	if ( '' !== trim( (string) $data['external_url'] ) && ! is_http_url( $data['external_url'] ) ) {
		$errors['external_url_invalid'] = __( 'El enlace externo debe ser una URL http(s) válida.', 'casa-eventos' );
	}
	if ( false === parse_capacity( $data['capacity'] ) ) {
		$errors['capacity_invalid'] = __( 'El aforo debe ser un número entero mayor que cero.', 'casa-eventos' );
	}
	if ( STATUS_CANCELLED === $data['previous_status'] && STATUS_CANCELLED !== $data['status'] && ! $data['can_reactivate'] ) {
		$errors['reactivate_forbidden'] = __( 'Solo un administrador puede reactivar un evento cancelado.', 'casa-eventos' );
	}

	// Derived instants for the cross-field rules.
	$gmt       = derive_gmt( (string) $start, (string) $end, $data['timezone'], (int) $data['duration'] );
	$close_gmt = '' !== (string) $sales_close ? local_to_gmt( $sales_close, $data['timezone'] ) : '';

	// Completeness rules: publish / schedule only.
	if ( status_requires_complete_data( $data['target_status'] ) ) {
		if ( '' === trim( (string) $data['title'] ) ) {
			$errors['title_required'] = __( 'Falta el título.', 'casa-eventos' );
		}
		if ( '' === $start ) {
			$errors['start_required'] = __( 'Falta la fecha y hora de inicio.', 'casa-eventos' );
		}
		if ( is_string( $start ) && '' !== $start && is_string( $end ) && '' !== $end && $end <= $start ) {
			$errors['end_before_start'] = __( 'El fin tiene que ser posterior al inicio.', 'casa-eventos' );
		}
		if ( '' === (string) $data['access_mode'] ) {
			$errors['access_mode_required'] = __( 'Falta la modalidad de acceso.', 'casa-eventos' );
		}
		if ( ACCESS_EXTERNAL === $data['access_mode'] && ! is_http_url( $data['external_url'] ) && ! isset( $errors['external_url_invalid'] ) ) {
			$errors['external_url_required'] = __( 'La venta externa necesita el enlace de compra.', 'casa-eventos' );
		}
		$category_count = count( array_unique( array_map( 'intval', (array) $data['category_ids'] ) ) );
		if ( 1 !== $category_count ) {
			$errors['category_count'] = 0 === $category_count
				? __( 'Falta la categoría.', 'casa-eventos' )
				: __( 'El evento tiene que tener una sola categoría.', 'casa-eventos' );
		}
		if ( '' !== $close_gmt && '' !== $gmt['ends_gmt'] && $close_gmt > $gmt['ends_gmt'] ) {
			$errors['sales_close_after_end'] = __( 'El cierre de venta no puede ser posterior al fin del evento.', 'casa-eventos' );
		}
	}

	// Warnings.
	if ( '' !== $close_gmt && '' !== $gmt['start_gmt'] && $close_gmt > $gmt['start_gmt'] && ! isset( $errors['sales_close_after_end'] ) ) {
		$warnings['sales_close_after_start'] = __( 'El cierre de venta es posterior al inicio del evento.', 'casa-eventos' );
	}
	if ( $data['featured'] && ! $data['listed'] ) {
		$warnings['featured_not_listed'] = __( 'Está destacado pero no visible en la Agenda: no aparecerá en la Home.', 'casa-eventos' );
	}
	if ( '' !== (string) $data['now_gmt'] && is_finished( $gmt['ends_gmt'], $data['now_gmt'] ) ) {
		$warnings['already_finished'] = __( 'La fecha del evento ya pasó: se mostrará como finalizado.', 'casa-eventos' );
	}

	/**
	 * Filter validation results. Later modules add guards here (for
	 * example capacity below sold + held tickets).
	 *
	 * @param array $result {errors, warnings}.
	 * @param array $data   Validated data.
	 */
	$result = apply_filters(
		'casa_eventos/validate_event',
		array(
			'errors'   => $errors,
			'warnings' => $warnings,
		),
		$data
	);

	return array(
		'errors'   => isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : $errors,
		'warnings' => isset( $result['warnings'] ) && is_array( $result['warnings'] ) ? $result['warnings'] : $warnings,
	);
}

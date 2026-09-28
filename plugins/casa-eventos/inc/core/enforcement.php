<?php
/**
 * Server-side enforcement of the validation rules (validation.php).
 *
 * - Block editor (REST): rest_pre_insert_casa_evento runs before the post,
 *   its terms and its meta are written, so an invalid publish is rejected
 *   as a whole with a readable message (the editor shows it).
 * - Quick Edit / Bulk Edit / classic admin saves: wp_insert_post_data
 *   validates the stored data plus the submitted title and categories.
 * - Programmatic saves (WP-CLI, cron, custom code) are not blocked; the
 *   admin list flags published events with incomplete data.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation data built from a stored event.
 *
 * @param WP_Post  $post          Event post.
 * @param string   $target_status Post status to validate for.
 * @param int|null $timestamp     Now (unix); defaults to time().
 * @return array
 */
function stored_validation_data( WP_Post $post, $target_status, $timestamp = null ) {
	$id     = (int) $post->ID;
	$status = metadata_exists( 'post', $id, META_STATUS ) ? (string) get_post_meta( $id, META_STATUS, true ) : '';
	$tz     = sanitize_timezone( get_post_meta( $id, META_TIMEZONE, true ) );
	$terms  = wp_get_object_terms( $id, TAXONOMY, array( 'fields' => 'ids' ) );

	return array(
		'target_status'   => $target_status,
		'title'           => (string) $post->post_title,
		'start'           => (string) get_post_meta( $id, META_START, true ),
		'end'             => (string) get_post_meta( $id, META_END, true ),
		'sales_close'     => (string) get_post_meta( $id, META_SALES_CLOSE, true ),
		'access_mode'     => (string) get_post_meta( $id, META_ACCESS_MODE, true ),
		'entry_kind'      => (string) get_post_meta( $id, META_ENTRY_KIND, true ),
		'external_url'    => (string) get_post_meta( $id, META_EXTERNAL_URL, true ),
		'capacity'        => metadata_exists( 'post', $id, META_CAPACITY ) ? get_post_meta( $id, META_CAPACITY, true ) : null,
		'status'          => '' === $status ? STATUS_ACTIVE : $status,
		'previous_status' => $status,
		'can_reactivate'  => true,
		'category_ids'    => is_array( $terms ) ? array_map( 'intval', $terms ) : array(),
		'listed'          => '1' === to_flag( get_post_meta( $id, META_LISTED, true ) ),
		'featured'        => '1' === to_flag( get_post_meta( $id, META_FEATURED, true ) ),
		'timezone'        => '' !== $tz ? $tz : timezone_for_new_event(),
		'duration'        => default_duration_minutes(),
		'now_gmt'         => now_gmt( $timestamp ),
	);
}

/**
 * Validation data for a new event with nothing stored yet.
 *
 * @return array
 */
function empty_validation_data() {
	return array(
		'target_status'   => 'draft',
		'title'           => '',
		'status'          => STATUS_ACTIVE,
		'previous_status' => '',
		'can_reactivate'  => true,
		'category_ids'    => array(),
		'listed'          => true,
		'featured'        => false,
		'timezone'        => timezone_for_new_event(),
		'duration'        => default_duration_minutes(),
		'now_gmt'         => now_gmt(),
	);
}

/**
 * Overlay REST meta values on validation data.
 *
 * @param array $data Validation data.
 * @param array $meta REST "meta" object from the request.
 * @return array
 */
function apply_request_meta( array $data, array $meta ) {
	$map = array(
		META_START        => 'start',
		META_END          => 'end',
		META_SALES_CLOSE  => 'sales_close',
		META_ACCESS_MODE  => 'access_mode',
		META_ENTRY_KIND   => 'entry_kind',
		META_EXTERNAL_URL => 'external_url',
		META_CAPACITY     => 'capacity',
		META_STATUS       => 'status',
		META_LISTED       => 'listed',
		META_FEATURED     => 'featured',
	);
	foreach ( $map as $key => $field ) {
		if ( ! array_key_exists( $key, $meta ) ) {
			continue;
		}
		$value = $meta[ $key ];
		switch ( $field ) {
			case 'status':
				$data[ $field ] = null === $value ? STATUS_ACTIVE : $value;
				break;
			case 'listed':
				$data[ $field ] = null === $value ? true : '1' === to_flag( $value );
				break;
			case 'featured':
				$data[ $field ] = null === $value ? false : '1' === to_flag( $value );
				break;
			case 'capacity':
				$data[ $field ] = $value;
				break;
			default:
				$data[ $field ] = null === $value ? '' : $value;
		}
	}
	return $data;
}

/**
 * Turn validation errors into a WP_Error.
 *
 * @param array<string,string> $errors        Errors.
 * @param string               $target_status Target post status.
 * @return WP_Error
 */
function validation_error( array $errors, $target_status ) {
	$intro = status_requires_complete_data( $target_status )
		? __( 'No se puede publicar el evento:', 'casa-eventos' )
		: __( 'No se puede guardar el evento:', 'casa-eventos' );

	return new WP_Error(
		'casa_eventos_invalid_event',
		$intro . ' ' . implode( ' ', array_values( $errors ) ),
		array(
			'status' => 400,
			'errors' => $errors,
		)
	);
}

/**
 * REST: validate the resulting event before anything is written.
 *
 * @param \stdClass|WP_Error $prepared_post Post prepared for the database.
 * @param \WP_REST_Request   $request       Request.
 * @return \stdClass|WP_Error
 */
function validate_rest_insert( $prepared_post, $request ) {
	if ( is_wp_error( $prepared_post ) ) {
		return $prepared_post;
	}

	$post_id  = isset( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;
	$existing = $post_id ? get_post( $post_id ) : null;

	if ( $existing instanceof WP_Post && POST_TYPE === $existing->post_type ) {
		$data = stored_validation_data( $existing, $existing->post_status );
	} else {
		$data = empty_validation_data();
	}

	if ( isset( $prepared_post->post_status ) ) {
		$data['target_status'] = (string) $prepared_post->post_status;
	}
	if ( isset( $prepared_post->post_title ) ) {
		$data['title'] = (string) $prepared_post->post_title;
	}

	$meta          = $request->get_param( 'meta' );
	$schema_errors = array();
	if ( is_array( $meta ) ) {
		$schema_errors = request_meta_schema_errors( $meta );
		$data          = apply_request_meta( $data, $meta );
	}

	$taxonomy  = get_taxonomy( TAXONOMY );
	$rest_base = $taxonomy && ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : TAXONOMY;
	$terms     = $request->get_param( $rest_base );
	if ( null !== $terms ) {
		$data['category_ids'] = existing_category_ids( (array) $terms );
	}

	$data['can_reactivate'] = current_user_can( reactivate_capability() );

	$result = validate_event_data( $data );
	$errors = array_merge( $schema_errors, $result['errors'] );
	if ( $errors ) {
		return validation_error( $errors, $data['target_status'] );
	}
	return $prepared_post;
}

/**
 * Validate request meta against the registered REST schema, up front.
 *
 * Core checks the meta schema only after the post row (status, title,
 * terms) has been written, so a schema error there leaves a half-applied
 * save — e.g. an event published without its start. Checking the same
 * schema here rejects the whole request before anything is written.
 *
 * @param array $meta REST "meta" object from the request.
 * @return array<string,string> Errors.
 */
function request_meta_schema_errors( array $meta ) {
	$definitions = editorial_meta_definitions();
	$errors      = array();
	foreach ( $meta as $key => $value ) {
		if ( null === $value || ! isset( $definitions[ $key ] ) ) {
			continue; // null deletes; unknown keys are ignored by core.
		}
		$schema = array_merge( array( 'type' => $definitions[ $key ]['type'] ), $definitions[ $key ]['schema'] );
		if ( is_wp_error( rest_validate_value_from_schema( $value, $schema, 'meta.' . $key ) ) ) {
			$errors[ 'meta_invalid' . $key ] = sprintf(
				/* translators: %s: field label. */
				__( 'El campo «%s» no tiene un valor válido.', 'casa-eventos' ),
				meta_field_label( $key )
			);
		}
	}
	return $errors;
}

/**
 * The requested IDs that really are categories of this taxonomy.
 *
 * Core silently drops unknown IDs (and IDs of other taxonomies) when it
 * assigns terms, so counting the raw request would let an event publish
 * with no category.
 *
 * @param array $ids Requested term IDs.
 * @return int[]
 */
function existing_category_ids( array $ids ) {
	$out = array();
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 && term_exists( $id, TAXONOMY ) ) {
			$out[] = $id;
		}
	}
	return array_values( array_unique( $out ) );
}
add_filter( 'rest_pre_insert_' . POST_TYPE, __NAMESPACE__ . '\\validate_rest_insert', 10, 2 );

/**
 * Admin (non-REST) saves: Quick Edit, Bulk Edit, classic post.php.
 *
 * @param array $data    Slashed post data about to be written.
 * @param array $postarr Slashed submitted data.
 * @return array
 */
function guard_admin_publish( $data, $postarr ) {
	if ( POST_TYPE !== ( $data['post_type'] ?? '' ) || ! status_requires_complete_data( $data['post_status'] ?? '' ) ) {
		return $data;
	}
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || ! is_admin() ) {
		return $data;
	}

	$post = empty( $postarr['ID'] ) ? null : get_post( (int) $postarr['ID'] );
	if ( ! $post instanceof WP_Post ) {
		return $data;
	}

	$validation          = stored_validation_data( $post, $data['post_status'] );
	$validation['title'] = wp_unslash( (string) ( $data['post_title'] ?? '' ) );
	if ( isset( $postarr['tax_input'][ TAXONOMY ] ) ) {
		$validation['category_ids'] = existing_category_ids( (array) $postarr['tax_input'][ TAXONOMY ] );
	}

	$result = validate_event_data( $validation );
	if ( $result['errors'] ) {
		$error = validation_error( $result['errors'], $data['post_status'] );
		wp_die(
			esc_html( $error->get_error_message() ),
			esc_html__( 'Evento incompleto', 'casa-eventos' ),
			array(
				'response'  => 400,
				'back_link' => true,
			)
		);
	}
	return $data;
}
add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\\guard_admin_publish', 10, 2 );

<?php
/**
 * Operational Event edit/write API (E3.1). No routes or form rendering.
 *
 * Inputs are UNSLASHED values, never a raw $_POST array. Callers must handle
 * transport/nonce protection; this API always enforces capabilities itself.
 * No field omission is a delete. Only listed fields/publication actions exist.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<string,string> Operational name => registered editorial meta. */
function event_write_meta_fields() {
	return array(
		'start' => META_START, 'end' => META_END,
		'access_mode' => META_ACCESS_MODE, 'entry_kind' => META_ENTRY_KIND,
		'entry_label' => META_ENTRY_LABEL, 'external_url' => META_EXTERNAL_URL,
		'capacity' => META_CAPACITY, 'sales_close' => META_SALES_CLOSE,
		'status' => META_STATUS, 'listed' => META_LISTED, 'featured' => META_FEATURED,
	);
}

/** @return WP_Error An API/authorization/persistence error, not a domain rule. */
function event_write_error( $code, $message, array $data = array() ) {
	return new WP_Error( 'casa_eventos_' . $code, $message, $data );
}

/** @return WP_Post|WP_Error Editable, existing Event; never a cast/forged ID. */
function editable_event_post( $post_id ) {
	if ( ! is_int( $post_id ) || $post_id < 1 ) {
		return event_write_error( 'invalid_id', __( 'El ID del evento no es válido.', 'casa-eventos' ) );
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
		return event_write_error( 'invalid_event', __( 'El evento no existe o no se puede editar.', 'casa-eventos' ) );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return event_write_error( 'forbidden', __( 'No tenés permiso para editar este evento.', 'casa-eventos' ) );
	}
	return $post;
}

/**
 * Conservative simple-editor boundary: all block-marked content is protected.
 * Native Gutenberg remains the administrator fallback. No conversion occurs.
 *
 * @return bool
 */
function event_content_is_protected( $content ) {
	return has_blocks( $content ) || false !== strpos( $content, '<!-- /wp:' );
}

/**
 * Lossless current edit snapshot. Stored editorial values are not sanitized
 * on read (including an external URL hidden by the current access mode).
 * meta_present distinguishes absent storage from registered defaults.
 * Read-only identity/context keys cannot be submitted to save_event().
 *
 * @return array|WP_Error {id, post_status, fields, meta_present,
 *                         content_protected, uuid, timezone, can_reactivate}.
 */
function event_edit_snapshot( $post_id ) {
	$post = editable_event_post( $post_id );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$terms = wp_get_object_terms( $post_id, TAXONOMY, array( 'fields' => 'ids' ) );
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}
	$fields = array(
		'title' => $post->post_title, 'excerpt' => $post->post_excerpt,
		'content' => $post->post_content, 'poster_id' => (int) get_post_thumbnail_id( $post_id ),
		'category_ids' => array_map( 'intval', $terms ),
	);
	$present = array();
	foreach ( event_write_meta_fields() as $field => $key ) {
		$fields[ $field ] = get_post_meta( $post_id, $key, true );
		$present[ $field ] = metadata_exists( 'post', $post_id, $key );
	}
	$event = Event::get( $post );
	return array(
		'id' => $post_id, 'post_status' => $post->post_status, 'fields' => $fields,
		'meta_present' => $present,
		'content_protected' => event_content_is_protected( $post->post_content ),
		'uuid' => $event->uuid(), 'timezone' => $event->timezone(),
		'can_reactivate' => current_user_can( reactivate_capability() ),
	);
}

/**
 * Resolve only explicit publication actions. null preserves an existing
 * status (including future/private/pending); a new Event starts as draft.
 * save_draft is for new/draft/auto-draft Events; return_to_draft is explicit.
 * publish publishes now, never at the Event start; update requires publish.
 *
 * @return string|WP_Error Target WordPress status.
 */
function event_publication_target( $post, $action ) {
	$current = $post ? $post->post_status : 'draft';
	if ( null === $action ) {
		return $current;
	}
	if ( ! in_array( $action, array( 'save_draft', 'publish', 'update', 'return_to_draft' ), true ) ) {
		return event_write_error( 'publication_action', __( 'La acción de publicación no está autorizada.', 'casa-eventos' ) );
	}
	switch ( $action ) {
		case 'save_draft':
			if ( ! $post || in_array( $current, array( 'draft', 'auto-draft' ), true ) ) {
				return 'draft';
			}
			break;
		case 'publish':
			return 'publish';
		case 'update':
			if ( $post && 'publish' === $current ) {
				return 'publish';
			}
			break;
		case 'return_to_draft':
			if ( $post ) {
				return 'draft';
			}
			break;
	}
	return event_write_error( 'publication_action', __( 'La acción de publicación no es válida para este evento.', 'casa-eventos' ) );
}

/**
 * Create (ID 0) or update an Event. Returns {id,warnings}, or WP_Error.
 *
 * Allowlist: title, excerpt, content (strings; '' clears); poster_id (image
 * attachment integer, 0 clears); category_ids (existing positive integer
 * IDs, [] clears); the eleven event_write_meta_fields().
 * Meta null explicitly resets to its registered default or ''.
 * Capacity '' also requests the existing default. Omission preserves.
 * System/meta keys, raw post_status/dates/author and arbitrary WP input fail.
 *
 * Validation/authorization failures precede every mutation. WordPress is not
 * transactional: a detected persistence failure returns partial=true and an
 * ID for inspection, emits no successful sync lifecycle, and never rolls back.
 * Other plugins' save_post/transition hooks still execute normally.
 *
 * @param int         $post_id Existing ID, or 0 to create.
 * @param array       $fields  Unslashed, explicitly supplied fields only.
 * @param string|null $publication_action save_draft|publish|update|return_to_draft|null.
 * @return array|WP_Error
 */
function save_event( $post_id, array $fields, $publication_action = null ) {
	if ( ! is_int( $post_id ) || $post_id < 0 ) {
		return event_write_error( 'invalid_id', __( 'El ID del evento no es válido.', 'casa-eventos' ) );
	}
	$post = $post_id ? editable_event_post( $post_id ) : null;
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$guard = apply_filters( 'casa_eventos/before_event_write', true, $post_id, $fields );
	if ( is_wp_error( $guard ) ) { return $guard; }
	if ( ! $post && ! current_user_can( event_capabilities()['create_posts'] ) ) {
		return event_write_error( 'forbidden', __( 'No tenés permiso para crear eventos.', 'casa-eventos' ) );
	}
	$target = event_publication_target( $post, $publication_action );
	if ( is_wp_error( $target ) ) {
		return $target;
	}
	if ( status_requires_complete_data( $target ) && ! current_user_can( event_capabilities()['publish_posts'] ) ) {
		return event_write_error( 'forbidden', __( 'No tenés permiso para publicar eventos.', 'casa-eventos' ) );
	}

	$map = event_write_meta_fields();
	$allowed = array_merge( array( 'title', 'excerpt', 'content', 'poster_id', 'category_ids' ), array_keys( $map ) );
	if ( array_diff( array_keys( $fields ), $allowed ) ) {
		return event_write_error( 'unknown_field', __( 'La solicitud contiene campos no autorizados.', 'casa-eventos' ) );
	}
	$postarr = array( 'post_type' => POST_TYPE, 'post_status' => $target );
	$native = array( 'title' => 'post_title', 'excerpt' => 'post_excerpt', 'content' => 'post_content' );
	foreach ( $native as $field => $column ) {
		if ( ! array_key_exists( $field, $fields ) ) {
			continue;
		}
		if ( ! is_string( $fields[ $field ] ) ) {
			return event_write_error( 'field_type', __( 'El contenido editorial debe ser texto.', 'casa-eventos' ) );
		}
		$value = $fields[ $field ];
		if ( 'content' === $field && ( event_content_is_protected( $value ) || ( $post && event_content_is_protected( $post->post_content ) ) ) ) {
			if ( ! $post || $value !== $post->post_content ) {
				return event_write_error( 'protected_content', __( 'Esta descripción requiere el editor nativo; se conserva sin convertir.', 'casa-eventos' ) );
			}
			continue; // Identical protected content is preservation, never re-sanitization.
		}
		// Use the same WordPress post-field sanitization before domain validation.
		$postarr[ $column ] = wp_unslash( sanitize_post_field( $column, wp_slash( $value ), $post_id, 'db' ) );
	}

	$data = $post ? stored_validation_data( $post, $target ) : empty_validation_data();
	$data['target_status'] = $target;
	$data['title'] = $postarr['post_title'] ?? ( $post ? $post->post_title : '' );
	$data['can_reactivate'] = current_user_can( reactivate_capability() );
	$meta = array();
	foreach ( $map as $field => $key ) {
		if ( array_key_exists( $field, $fields ) ) {
			$meta[ $key ] = 'capacity' === $field && '' === $fields[ $field ] ? null : $fields[ $field ];
		}
	}
	$errors = request_meta_schema_errors( $meta );
	if ( $errors ) {
		return validation_error( $errors, $target );
	}
	$data = apply_request_meta( $data, $meta );

	if ( array_key_exists( 'category_ids', $fields ) ) {
		if ( ! current_user_can( category_capabilities()['assign_terms'] ) ) {
			return event_write_error( 'forbidden', __( 'No tenés permiso para asignar categorías.', 'casa-eventos' ) );
		}
		$ids = $fields['category_ids'];
		if ( ! is_array( $ids ) || array_filter( $ids, static fn( $id ) => ! is_int( $id ) || $id < 1 ) ) {
			return event_write_error( 'category_ids', __( 'Las categorías deben ser IDs existentes.', 'casa-eventos' ) );
		}
		$ids = array_values( array_unique( $ids ) );
		$existing = existing_category_ids( $ids );
		if ( count( $existing ) !== count( $ids ) ) {
			return event_write_error( 'category_ids', __( 'Una categoría no existe en el vocabulario de eventos.', 'casa-eventos' ) );
		}
		$data['category_ids'] = $existing;
		$postarr['tax_input'] = array( TAXONOMY => $existing );
	}

	if ( array_key_exists( 'poster_id', $fields ) ) {
		$poster = $fields['poster_id'];
		if ( ! is_int( $poster ) || $poster < 0 ) {
			return event_write_error( 'poster_id', __( 'El afiche debe ser un ID de imagen o cero.', 'casa-eventos' ) );
		}
		if ( $poster && ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'read_post', $poster ) ) ) {
			return event_write_error( 'forbidden', __( 'No tenés permiso para seleccionar este afiche.', 'casa-eventos' ) );
		}
		$attachment = $poster ? get_post( $poster ) : null;
		if ( $poster && ( ! $attachment || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status || ! wp_attachment_is_image( $poster ) || ! wp_get_attachment_image( $poster, 'thumbnail' ) ) ) {
			return event_write_error( 'poster_id', __( 'El afiche no es una imagen utilizable.', 'casa-eventos' ) );
		}
	}
	$result = validate_event_data( $data );
	$errors = array_merge( $errors, $result['errors'] );
	if ( $errors ) {
		return validation_error( $errors, $target );
	}

	$definitions = editorial_meta_definitions();
	foreach ( $meta as $key => $value ) {
		$def = $definitions[ $key ];
		$value = null === $value ? ( $def['default'] ?? '' ) : $value;
		$postarr['meta_input'][ $key ] = call_user_func( $def['sanitize'], $value );
	}
	if ( array_key_exists( 'poster_id', $fields ) ) {
		$postarr['meta_input']['_thumbnail_id'] = $fields['poster_id'];
	}
	if ( 'publish' === $publication_action && ( ! $post || 'publish' !== $post->post_status ) ) {
		// Clear inherited scheduling: publish now, independently of Event dates.
		$postarr['post_date_gmt'] = now_gmt();
		$postarr['post_date'] = wp_date( DATETIME_FORMAT, null, wp_timezone() );
	}
	if ( $post ) {
		$postarr['ID'] = $post_id;
	}

	// Scope temporary filters by a unique, non-persisted WP context. Preserve
	// omitted native fields byte-for-byte even if KSES would change old content.
	// Restore the chosen status if WP auto-interprets an inherited post date.
	$token = 'casa-eventos-write-' . wp_generate_uuid4();
	$postarr['context'] = $token;
	$matches = static fn( $arr ) => POST_TYPE === ( $arr['post_type'] ?? '' ) && $token === ( $arr['context'] ?? '' );
	$preserve = static function ( $row, $arr ) use ( $matches, $post, $postarr, $native, $target ) {
		if ( $matches( $arr ) ) {
			$row['post_status'] = $target;
			foreach ( $native as $column ) {
				if ( $post && ! array_key_exists( $column, $postarr ) ) {
					$row[ $column ] = wp_slash( $post->$column );
				}
			}
		}
		return $row;
	};
	$allow_empty = static fn( $empty, $arr ) => $matches( $arr ) ? false : $empty;
	add_filter( 'wp_insert_post_data', $preserve, 9, 2 );
	add_filter( 'wp_insert_post_empty_content', $allow_empty, 10, 2 );
	try {
		$id = $post
			? wp_update_post( wp_slash( $postarr ), true, false )
			: wp_insert_post( wp_slash( $postarr ), true, false );
	} finally {
		remove_filter( 'wp_insert_post_data', $preserve, 9 );
		remove_filter( 'wp_insert_post_empty_content', $allow_empty, 10 );
	}
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	if ( ! $id ) {
		return event_write_error( 'persist_failed', __( 'No se pudo guardar el evento.', 'casa-eventos' ) );
	}
	$saved = get_post( $id );
	$persisted = $saved && POST_TYPE === $saved->post_type && $target === $saved->post_status;
	foreach ( $native as $column ) {
		$expected = $postarr[ $column ] ?? ( $post ? $post->$column : '' );
		$persisted = $persisted && $expected === $saved->$column;
	}
	foreach ( $postarr['meta_input'] ?? array() as $key => $value ) {
		$persisted = $persisted && metadata_exists( 'post', $id, $key ) && (string) $value === (string) get_post_meta( $id, $key, true );
	}
	if ( isset( $postarr['tax_input'] ) ) {
		$actual = wp_get_object_terms( $id, TAXONOMY, array( 'fields' => 'ids' ) );
		$expected = $postarr['tax_input'][ TAXONOMY ];
		if ( is_array( $actual ) ) {
			$actual = array_map( 'intval', $actual );
			sort( $actual );
			sort( $expected );
		}
		$persisted = $persisted && $actual === $expected;
	}
	if ( ! $persisted ) {
		return event_write_error( 'persist_failed', __( 'El guardado no quedó completo; requiere revisión antes de continuar.', 'casa-eventos' ), array( 'id' => (int) $id, 'partial' => true ) );
	}
	// Release the normal after-insert lifecycle after editorial persistence.
	// WP actions discard return values: capture Core's failure for this ID only.
	$sync_error = null;
	$capture_failure = static function ( $failed_id, $error ) use ( $id, &$sync_error ) {
		if ( (int) $id === $failed_id && is_wp_error( $error ) ) {
			$sync_error = $error;
		}
	};
	add_action( 'casa_eventos/event_sync_failed', $capture_failure, 10, 2 );
	try {
		wp_after_insert_post( $saved, (bool) $post, $post );
	} finally {
		remove_filter( 'casa_eventos/event_sync_failed', $capture_failure, 10 );
	}
	if ( $sync_error ) {
		return $sync_error;
	}
	return array( 'id' => (int) $id, 'warnings' => $result['warnings'] );
}

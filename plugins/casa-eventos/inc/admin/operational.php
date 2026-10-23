<?php
/**
 * E3.2 operational admin transport. Event writes belong exclusively to E3.1.
 * No stored locks, request receipts, options or capability changes.
 *
 * @package CasaEventos
 */
namespace CasaEventos\Admin;

use CasaEventos\Core;
use CasaEventos\Core\Event;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) { exit; }

const OPERATIONAL_PAGE = 'casa-eventos-operational';

function operational_url( $id = 0 ) {
	return add_query_arg( array( 'page' => OPERATIONAL_PAGE, 'event_id' => $id ), admin_url( 'edit.php?post_type=' . Core\POST_TYPE ) );
}

/** WordPress login takes event staff directly to the existing form. */
function programador_login_redirect( $redirect, $requested, $user ) {
	return $user instanceof \WP_User && Core\is_programador( $user )
		? operational_url() : $redirect;
}
add_filter( 'login_redirect', __NAMESPACE__ . '\\programador_login_redirect', 10, 3 );

/** The generic dashboard is not the Programador's landing page. */
function programador_dashboard_redirect() {
	global $pagenow;
	if ( Core\is_programador() && 'index.php' === $pagenow ) {
		wp_safe_redirect( operational_url() );
		exit;
	}
}
add_action( 'admin_init', __NAMESPACE__ . '\\programador_dashboard_redirect', 1 );

/** Send WordPress's Add New link straight to the permitted Programador form. */
function operational_new_event_url( $url, $path ) {
	if ( ! Core\is_programador() || ! current_user_can( Core\event_capabilities()['create_posts'] ) ) { return $url; }
	$parts = parse_url( $path );
	if ( ! is_array( $parts ) || 'post-new.php' !== basename( $parts['path'] ?? '' ) ) { return $url; }
	parse_str( $parts['query'] ?? '', $query );
	return count( $query ) === 1 && ( $query['post_type'] ?? null ) === Core\POST_TYPE ? operational_url() : $url;
}
add_filter( 'admin_url', __NAMESPACE__ . '\\operational_new_event_url', 10, 2 );

/** Strict transport parsing, never absint/casting of forged IDs. */
function operational_id( $raw ) {
	if ( ! is_string( $raw ) || ! preg_match( '/^(0|[1-9][0-9]*)$/D', $raw ) || strlen( $raw ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $raw ) === strlen( (string) PHP_INT_MAX ) && strcmp( $raw, (string) PHP_INT_MAX ) > 0 ) ) {
		return operational_error( 'id', 'El ID del evento no es válido.' );
	}
	return (int) $raw;
}

function operational_error( $code, $message ) {
	return new WP_Error( 'casa_eventos_ui_' . $code, __( $message, 'casa-eventos' ) );
}

/** Reading a new form does not create an auto-draft. */
function operational_snapshot( $id ) {
	if ( $id ) { return Core\event_edit_snapshot( $id ); }
	if ( ! current_user_can( Core\event_capabilities()['create_posts'] ) ) {
		return operational_error( 'forbidden', 'No tenés permiso para crear eventos.' );
	}
	return array(
		'id' => 0, 'post_status' => 'draft', 'fields' => array(
			'title' => '', 'excerpt' => '', 'content' => '', 'poster_id' => 0, 'category_ids' => array(),
			'start' => '', 'end' => '', 'access_mode' => '', 'entry_kind' => '', 'entry_label' => '',
			'external_url' => '', 'capacity' => '', 'sales_close' => '',
			'status' => Core\STATUS_ACTIVE, 'listed' => '1', 'featured' => '0',
		),
		'meta_present' => array(), 'content_protected' => false, 'uuid' => '',
		'timezone' => Core\timezone_for_new_event(), 'can_reactivate' => current_user_can( Core\reactivate_capability() ),
	);
}

/** Fingerprint raw editorial state, metadata presence, terms, identity and native context.
 * Includes modification time but does not rely on it: meta/terms can change separately.
 * Excludes clock-derived finished state, so passage of time alone cannot stale a form.
 */
function operational_state( array $snapshot ) {
	$state = $snapshot;
	sort( $state['fields']['category_ids'], SORT_NUMERIC );
	if ( $snapshot['id'] ) {
		$post = Event::get( $snapshot['id'] )->post();
		foreach ( array( 'post_modified', 'post_modified_gmt', 'post_date', 'post_date_gmt', 'post_author', 'post_name' ) as $key ) {
			$state['native'][ $key ] = $post->$key ?? '';
		}
		$state['cancelled_at'] = Event::get( $snapshot['id'] )->cancelled_gmt();
	}
	return hash_hmac( 'sha256', serialize( $state ), wp_salt( 'nonce' ) . get_current_user_id() . wp_get_session_token() );
}

function operational_nonce_action( $id, $intent, $state ) {
	return 'casa-eventos-ui:' . $id . ':' . $intent . ':' . $state;
}

/** UI availability, not duplicated domain validation. */
function operational_actions( array $snapshot ) {
	$status = $snapshot['post_status'];
	$actions = array();
	if ( in_array( $status, array( 'draft', 'auto-draft' ), true ) ) {
		$actions['save_draft'] = 'Guardar borrador';
	} elseif ( 'publish' === $status ) {
		$actions['update'] = 'Actualizar evento';
	} else {
		$actions['preserve'] = 'Guardar cambios';
	}
	if ( 'publish' !== $status && current_user_can( Core\event_capabilities()['publish_posts'] ) ) {
		$actions['publish'] = 'Publicar ahora';
	}
	if ( $snapshot['id'] ) {
		if ( ! in_array( $status, array( 'draft', 'auto-draft' ), true ) ) { $actions['return_to_draft'] = 'Volver a borrador'; }
		$operational = $snapshot['fields']['status'];
		if ( Core\STATUS_ACTIVE === $operational ) { $actions['pause'] = 'Pausar'; }
		if ( Core\STATUS_PAUSED === $operational ) { $actions['resume'] = 'Retomar'; }
		if ( Core\STATUS_CANCELLED !== $operational ) { $actions['cancel'] = 'Cancelar evento'; }
		if ( Core\STATUS_CANCELLED === $operational && $snapshot['can_reactivate'] ) {
			$actions['reactivate_active'] = 'Reactivar como activo';
			$actions['reactivate_paused'] = 'Reactivar como pausado';
		}
	}
	return $actions;
}

function operational_confirmation( $intent ) {
	if ( 'cancel' === $intent ) { return __( '¿Cancelar este evento? Conserva su publicación y bloquea sus acciones. No se hace ningún reembolso automático.', 'casa-eventos' ); }
	if ( in_array( $intent, array( 'reactivate_active', 'reactivate_paused' ), true ) ) {
		return __( 'Este evento fue cancelado. Reactivarlo puede volver a habilitar sus acciones. Las personas avisadas de la cancelación no reciben otro aviso. ¿Continuar?', 'casa-eventos' );
	}
	if ( 'return_to_draft' === $intent ) { return __( '¿Volver a borrador? El evento dejará de estar publicado.', 'casa-eventos' ); }
	return '';
}

/** Normalize only present controls. Unchanged rendered values are omitted.
 * Defaults are hints, not a reason to materialize missing fields in the UI.
 */
function operational_fields( $raw, array $snapshot ) {
	if ( ! is_array( $raw ) ) { return operational_error( 'fields', 'Los campos enviados no son válidos.' ); }
	$allowed = array_diff( array_keys( $snapshot['fields'] ), array( 'status' ) );
	if ( array_diff( array_keys( $raw ), $allowed ) ) { return operational_error( 'fields', 'La solicitud contiene campos no autorizados.' ); }
	$out = array();
	foreach ( $raw as $key => $value ) {
		if ( ! is_string( $value ) ) { return operational_error( 'fields', 'Los campos enviados no son válidos.' ); }
		$current = $snapshot['fields'][ $key ];
		if ( 'category_ids' === $key ) {
			if ( '__preserve' === $value && count( $current ) > 1 ) { continue; }
			$id = '' === $value ? 0 : operational_id( $value );
			if ( is_wp_error( $id ) ) { return $id; }
			$value = $id ? array( $id ) : array();
		} elseif ( 'poster_id' === $key ) {
			$value = operational_id( $value );
			if ( is_wp_error( $value ) ) { return $value; }
		} elseif ( in_array( $key, array( 'listed', 'featured' ), true ) ) {
			if ( ! in_array( $value, array( '0', '1' ), true ) ) { return operational_error( 'fields', 'La visibilidad enviada no es válida.' ); }
			$value = '1' === $value;
			$current = '1' === (string) $current;
		} elseif ( in_array( $key, array( 'start', 'end', 'sales_close' ), true ) ) {
			// datetime-local uses T; preserve seconds. Core owns date validity.
			$value = str_replace( 'T', ' ', $value );
			if ( strlen( $value ) === 16 ) { $value .= ':00'; }
		}
		if ( is_array( $value ) ? $value === $current : (string) $value === (string) $current ) { continue; }
		$out[ $key ] = $value;
	}
	return $out;
}

/** Testable POST boundary. No writes before ID, auth, nonce, stale and intent checks. */
function operational_submit( array $request, $method ) {
	if ( 'POST' !== $method ) { return operational_error( 'method', 'El guardado requiere una solicitud POST.' ); }
	$id = operational_id( $request['event_id'] ?? null );
	if ( is_wp_error( $id ) ) { return $id; }
	$snapshot = operational_snapshot( $id );
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	$intent = $request['intent'] ?? null;
	$state = $request['state'] ?? null;
	$nonce = $request['nonces'][ is_string( $intent ) ? $intent : '' ] ?? null;
	if ( ! is_string( $intent ) || ! is_string( $state ) || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, operational_nonce_action( $id, $intent, $state ) ) ) {
		return operational_error( 'nonce', 'La solicitud venció o no es válida. Recargá el formulario.' );
	}
	if ( ! hash_equals( operational_state( $snapshot ), $state ) ) {
		return operational_error( 'stale', 'El evento cambió desde que abriste el formulario. Recargá antes de guardar; tus cambios no se aplicaron.' );
	}
	if ( ! isset( operational_actions( $snapshot )[ $intent ] ) ) { return operational_error( 'action', 'La acción no está disponible para este evento.' ); }
	$confirmation = operational_confirmation( $intent );
	if ( $confirmation && '1' !== ( $request['confirmed'] ?? '' ) ) {
		return array( 'confirm' => $intent, 'snapshot' => $snapshot );
	}
	$fields = array();
	$publication = null;
	$operational = array( 'pause' => Core\STATUS_PAUSED, 'resume' => Core\STATUS_ACTIVE, 'cancel' => Core\STATUS_CANCELLED, 'reactivate_active' => Core\STATUS_ACTIVE, 'reactivate_paused' => Core\STATUS_PAUSED );
	if ( isset( $operational[ $intent ] ) ) {
		if ( ! empty( $request['fields'] ) ) { return operational_error( 'fields', 'Guardá los cambios editoriales antes de realizar esta acción.' ); }
		$fields['status'] = $operational[ $intent ];
	} elseif ( 'return_to_draft' === $intent ) {
		if ( ! empty( $request['fields'] ) ) { return operational_error( 'fields', 'Esta acción solo cambia la publicación del evento guardado.' ); }
		$publication = $intent;
	} else {
		$fields = operational_fields( $request['fields'] ?? array(), $snapshot );
		if ( is_wp_error( $fields ) ) { return $fields; }
		$publication = 'preserve' === $intent ? null : $intent;
	}
	try {
		$result = Core\save_event( $id, $fields, $publication );
	} catch ( \Throwable $error ) {
		// A new ID may not have been returned. Never offer a blind create retry.
		return new WP_Error( 'casa_eventos_ui_exception', __( 'El guardado se interrumpió y puede haber aplicado cambios. Revisá la lista de eventos antes de continuar; no repitas la creación.', 'casa-eventos' ), array( 'partial' => true, 'id' => $id ) );
	}
	if ( is_wp_error( $result ) ) { return $result; }
	return $result + array( 'intent' => $intent );
}

/** Signed, short-lived outcome receipt for PRG, no persistent options/transients. */
function operational_receipt( $result, $id = null ) {
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		$record = array( 'id' => $id, 'error' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => is_array( $data ) ? $data : array() );
	} else {
		$record = array( 'id' => $result['id'], 'warnings' => $result['warnings'], 'intent' => $result['intent'] );
	}
	$record['time'] = time();
	$payload = rtrim( strtr( base64_encode( wp_json_encode( $record ) ), '+/', '-_' ), '=' );
	return $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) . get_current_user_id() . wp_get_session_token() );
}
function operational_read_receipt( $raw, $id ) {
	if ( ! is_string( $raw ) || strlen( $raw ) > 12000 ) { return null; }
	$parts = explode( '.', $raw );
	if ( count( $parts ) !== 2 || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) . get_current_user_id() . wp_get_session_token() ), $parts[1] ) ) { return null; }
	$result = json_decode( (string) base64_decode( strtr( $parts[0], '-_', '+/' ), true ), true );
	if ( ! is_array( $result ) || ( $result['id'] ?? null ) !== $id || ! isset( $result['time'] ) || $result['time'] > time() || $result['time'] < time() - 300 ) { return null; }
	return isset( $result['error'] ) ? new WP_Error( $result['error'], $result['message'], $result['data'] ) : $result;
}

/** Uncertain/partial writes use PRG too: refreshing an error never repeats create. */
function operational_result_redirect( $result, $original_id ) {
	if ( is_array( $result ) && ! isset( $result['confirm'] ) ) {
		return add_query_arg( 'receipt', operational_receipt( $result ), operational_url( $result['id'] ) );
	}
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		if ( is_array( $data ) && ! empty( $data['partial'] ) ) {
			$id = ! empty( $data['id'] ) ? (int) $data['id'] : $original_id;
			return add_query_arg( 'receipt', operational_receipt( $result, $id ), operational_url( $id ) );
		}
	}
	return null;
}

function register_operational_page() {
	$hook = add_submenu_page( 'edit.php?post_type=' . Core\POST_TYPE, __( 'Cargar evento', 'casa-eventos' ), __( 'Cargar evento', 'casa-eventos' ), Core\event_capabilities()['create_posts'], OPERATIONAL_PAGE, __NAMESPACE__ . '\\operational_page' );
	add_action( 'load-' . $hook, __NAMESPACE__ . '\\load_operational_page' );
	if ( Core\is_programador() ) { remove_submenu_page( 'edit.php?post_type=' . Core\POST_TYPE, 'post-new.php?post_type=' . Core\POST_TYPE ); }
}
add_action( 'admin_menu', __NAMESPACE__ . '\\register_operational_page', 20 );

/** Handle before admin output so successful saves can redirect safely. */
function load_operational_page() {
	$id = operational_id( $_GET['event_id'] ?? '0' );
	if ( is_wp_error( $id ) ) { wp_die( esc_html( $id->get_error_message() ), '', array( 'response' => 400 ) ); }
	$snapshot = operational_snapshot( $id );
	if ( is_wp_error( $snapshot ) ) { wp_die( esc_html( $snapshot->get_error_message() ), '', array( 'response' => 403 ) ); }
	$result = null;
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		$request = wp_unslash( $_POST );
		// URL and form must identify the same record; neither is authoritative alone.
		if ( ( $request['event_id'] ?? null ) !== (string) $id ) {
			wp_die( esc_html__( 'La solicitud no corresponde a este evento.', 'casa-eventos' ), '', array( 'response' => 400 ) );
		}
		if ( isset( $request['ticket_intent'] ) ) {
			$GLOBALS['casa_ticket_result'] = ticket_type_submit( $request, 'POST' );
			$GLOBALS['casa_eventos_operational_view'] = array( 'snapshot' => $snapshot, 'result' => null, 'request' => $request );
			return;
		}
		$result = operational_submit( $request, 'POST' );
		$redirect = operational_result_redirect( $result, $id );
		if ( $redirect ) {
			wp_safe_redirect( $redirect, 303 );
			exit;
		}
	}
	$GLOBALS['casa_eventos_operational_view'] = array( 'snapshot' => $snapshot, 'result' => $result, 'request' => isset( $request ) ? $request : array() );
}

function operational_page() {
	$view = $GLOBALS['casa_eventos_operational_view'] ?? null;
	if ( ! $view ) { return; }
	render_operational_form( $view['snapshot'], $view['result'], $view['request'] );
	render_ticket_types( $view['snapshot']['id'], $GLOBALS['casa_ticket_result'] ?? null, $view['request'] );
	render_buyer_export( $view['snapshot']['id'] );
}

function enqueue_operational_assets( $hook ) {
	if ( false === strpos( $hook, '_page_' . OPERATIONAL_PAGE ) ) { return; }
	wp_enqueue_media();
	wp_enqueue_style( 'casa-eventos-operational', CASA_EVENTOS_URL . 'assets/admin/operational.css', array(), CASA_EVENTOS_VERSION );
	wp_enqueue_script( 'casa-eventos-operational', CASA_EVENTOS_URL . 'assets/admin/operational.js', array( 'media-views' ), CASA_EVENTOS_VERSION, true );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_operational_assets' );

/**
 * Deny native REST Event mutations before endpoint callbacks. Reads keep
 * native permissions. Subresources (autosaves/revisions) and dispatched
 * batch subrequests use this same hook. Administrators remain unaffected.
 */
function guard_operational_rest_writes( $response, $handler, $request ) {
	if ( is_wp_error( $response ) || ! Core\is_programador() || in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) { return $response; }
	$type = get_post_type_object( Core\POST_TYPE );
	if ( ! $type || ! $type->show_in_rest ) { return $response; }
	$namespace = ! empty( $type->rest_namespace ) ? $type->rest_namespace : 'wp/v2';
	$base = ! empty( $type->rest_base ) ? $type->rest_base : Core\POST_TYPE;
	$collection = '/' . trim( $namespace, '/' ) . '/' . trim( $base, '/' );
	if ( ! preg_match( '#^' . preg_quote( $collection, '#' ) . '(?:/|$)#i', $request->get_route() ) ) { return $response; }
	return new WP_Error( 'casa_eventos_ui_rest_forbidden', __( 'Usá el formulario operacional de Eventos para realizar esta acción.', 'casa-eventos' ), array( 'status' => 403 ) );
}
add_filter( 'rest_request_before_callbacks', __NAMESPACE__ . '\\guard_operational_rest_writes', 0, 3 );

/** Classify native requests using actual objects, including revision parents.
 * Programador retains the native list and media, but not native Event writes.
 */
function operational_native_route( $page, $method, array $request ) {
	if ( ! Core\is_programador() ) { return null; }
	// This registered plugin page handles POST itself before the native list writer.
	if ( 'edit.php' === $page && OPERATIONAL_PAGE === ( $request['page'] ?? '' ) ) { return null; }
	// The legacy uploader trusts attachments[] keys without checking post type.
	// Refuse forged Event targets while preserving uploads and actual media edits.
	if ( 'media-upload.php' === $page && 'POST' === $method && isset( $request['attachments'] ) && is_array( $request['attachments'] ) ) {
		foreach ( array_keys( $request['attachments'] ) as $attachment_id ) {
			if ( Core\POST_TYPE === get_post_type( (int) $attachment_id ) ) { return array( 'deny' => true ); }
		}
	}
	$event = ( $request['post_type'] ?? '' ) === Core\POST_TYPE;
	$id = 0;
	foreach ( array( 'post', 'post_ID', 'post_id', 'revision' ) as $key ) {
		if ( ! isset( $request[ $key ] ) ) { continue; }
		// Classify the target using the native AJAX cast, including malformed
		// numeric prefixes. Operational form IDs remain strictly validated.
		$candidate = 'admin-ajax.php' === $page ? (int) $request[ $key ] : operational_id( $request[ $key ] );
		if ( is_wp_error( $candidate ) ) {
			if ( in_array( $page, array( 'post.php', 'revision.php' ), true ) ) { return array( 'deny' => true ); }
			continue;
		}
		$post = get_post( $candidate );
		if ( $post && 'revision' === $post->post_type ) { $post = get_post( $post->post_parent ); }
		if ( $post && Core\POST_TYPE === $post->post_type ) { $event = true; $id = (int) $post->ID; }
	}
	foreach ( (array) ( $request['post'] ?? array() ) as $candidate ) {
		$parsed = operational_id( $candidate );
		if ( ! is_wp_error( $parsed ) && Core\POST_TYPE === get_post_type( $parsed ) ) { $event = true; }
	}
	// Native heartbeat/autosave nests its post ID under data.
	if ( 'admin-ajax.php' === $page && in_array( $request['action'] ?? '', array( 'heartbeat', 'autosave' ), true ) ) {
		foreach ( array( $request['data']['wp_autosave'] ?? null, $request['data']['wp-autosave'] ?? null, $request['data'] ?? null ) as $nested ) {
			if ( is_array( $nested ) ) {
				$nested['action'] = 'autosave';
				$route = operational_native_route( $page, $method, $nested );
				if ( $route ) { return $route; }
			}
		}
	}
	if ( ! $event ) { return null; }
	if ( 'post-new.php' === $page && 'GET' === $method ) {
		return current_user_can( Core\event_capabilities()['create_posts'] ) ? array( 'redirect' => operational_url() ) : array( 'deny' => true );
	}
	if ( 'post.php' === $page && 'GET' === $method && 'edit' === ( $request['action'] ?? 'edit' ) && $id ) {
		return is_wp_error( Core\editable_event_post( $id ) ) ? array( 'deny' => true ) : array( 'redirect' => operational_url( $id ) );
	}
	if ( in_array( $page, array( 'post.php', 'post-new.php', 'revision.php' ), true ) ) { return array( 'deny' => true ); }
	if ( 'edit.php' === $page && ( 'POST' === $method || ( isset( $request['action'] ) && ! in_array( $request['action'], array( '', '-1' ), true ) ) || ( isset( $request['action2'] ) && ! in_array( $request['action2'], array( '', '-1' ), true ) ) ) ) { return array( 'deny' => true ); }
	if ( 'admin-ajax.php' === $page && in_array( $request['action'] ?? '', array( 'inline-save', 'autosave', 'wp-remove-post-lock', 'set-post-thumbnail' ), true ) ) { return array( 'deny' => true ); }
	return null;
}

function guard_operational_native_paths() {
	global $pagenow;
	// POST takes precedence, but inspect both sources so a forged post_type/ID
	// cannot mask another Event reference. Bulk IDs are resolved individually.
	foreach ( array( wp_unslash( $_GET ), wp_unslash( $_POST ), wp_unslash( array_merge( $_GET, $_POST ) ) ) as $request ) {
		$route = operational_native_route( $pagenow, $_SERVER['REQUEST_METHOD'] ?? 'GET', $request );
		if ( ! $route ) { continue; }
		if ( isset( $route['deny'] ) ) { wp_die( esc_html__( 'Usá el formulario operacional de Eventos para realizar esta acción.', 'casa-eventos' ), '', array( 'response' => 403 ) ); }
		wp_safe_redirect( $route['redirect'] );
		exit;
	}
}
add_action( 'admin_init', __NAMESPACE__ . '\\guard_operational_native_paths', 0 );

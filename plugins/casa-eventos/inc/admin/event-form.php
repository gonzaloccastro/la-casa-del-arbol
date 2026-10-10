<?php
/**
 * E3.2 server-rendered form. Reads the edit snapshot and Event API only.
 *
 * @package CasaEventos
 */
namespace CasaEventos\Admin;

use CasaEventos\Core;
use CasaEventos\Core\Event;
use CasaEventos\Core\Queries;

if ( ! defined( 'ABSPATH' ) ) { exit; }

function operational_hidden( $name, $value ) {
	printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( $value ) );
}

function operational_notice( $kind, $messages ) {
	echo '<div class="notice notice-' . esc_attr( $kind ) . '" role="status"><ul>';
	foreach ( (array) $messages as $message ) { echo '<li>' . esc_html( $message ) . '</li>'; }
	echo '</ul></div>';
}

/** Errors remain errors even when partial writes have already happened. */
function operational_result_notice( $result ) {
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		echo '<div id="e32-errors" class="notice notice-error" role="alert" tabindex="-1"><p>' . esc_html( $result->get_error_message() ) . '</p>';
		if ( is_array( $data ) && ! empty( $data['errors'] ) ) {
			echo '<ul>';
			foreach ( $data['errors'] as $message ) { echo '<li>' . esc_html( $message ) . '</li>'; }
			echo '</ul>';
		}
		if ( is_array( $data ) && ! empty( $data['partial'] ) ) {
			echo '<p>' . esc_html__( 'El guardado puede haber aplicado cambios. No se confirma éxito. Revisá lo persistido antes de continuar.', 'casa-eventos' ) . '</p>';
		}
		echo '</div>';
		return;
	}
	if ( is_array( $result ) && isset( $result['id'], $result['intent'] ) ) {
		$labels = array( 'save_draft' => 'Borrador guardado.', 'publish' => 'Evento publicado.', 'update' => 'Evento actualizado.', 'preserve' => 'Cambios guardados sin cambiar la publicación.', 'return_to_draft' => 'Evento pasado a borrador.', 'pause' => 'Evento pausado.', 'resume' => 'Evento retomado.', 'cancel' => 'Evento cancelado.', 'reactivate_active' => 'Evento reactivado como activo.', 'reactivate_paused' => 'Evento reactivado como pausado.' );
		operational_notice( 'success', __( $labels[ $result['intent'] ] ?? 'Evento guardado.', 'casa-eventos' ) );
		if ( ! empty( $result['warnings'] ) ) { operational_notice( 'warning', $result['warnings'] ); }
	}
}

/** Browser transport format; seconds and blank values survive a round trip. */
function operational_control_value( $key, $value ) {
	if ( in_array( $key, array( 'start', 'end', 'sales_close' ), true ) ) { return str_replace( ' ', 'T', (string) $value ); }
	if ( in_array( $key, array( 'listed', 'featured' ), true ) ) { return '1' === (string) $value ? '1' : '0'; }
	if ( 'category_ids' === $key ) { return count( $value ) > 1 ? '__preserve' : ( $value ? (string) reset( $value ) : '' ); }
	return (string) $value;
}

function operational_control( $key, $label, $value, $type = 'text', $options = null, $help = '' ) {
	$id = 'e32-' . $key;
	echo '<div class="casa-operational-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
	if ( is_array( $options ) ) {
		echo '<select id="' . esc_attr( $id ) . '" name="fields[' . esc_attr( $key ) . ']" aria-describedby="e32-errors ' . esc_attr( $id . '-help' ) . '">';
		if ( ! array_key_exists( (string) $value, $options ) ) { $options = array( (string) $value => __( 'Valor actual: ', 'casa-eventos' ) . (string) $value ) + $options; }
		foreach ( $options as $option => $text ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $option ), (string) $value === (string) $option ? ' selected' : '', esc_html( $text ) );
		}
		echo '</select>';
	} elseif ( 'textarea' === $type ) {
		echo '<textarea id="' . esc_attr( $id ) . '" name="fields[' . esc_attr( $key ) . ']" rows="3" aria-describedby="e32-errors">' . esc_textarea( $value ) . '</textarea>';
	} else {
		printf( '<input id="%s" type="%s" name="fields[%s]" value="%s" aria-describedby="e32-errors %s"%s>', esc_attr( $id ), esc_attr( $type ), esc_attr( $key ), esc_attr( $value ), esc_attr( $id . '-help' ), 'datetime-local' === $type ? ' step="1"' : '' );
	}
	echo '<p class="description" id="' . esc_attr( $id . '-help' ) . '">' . esc_html( $help ) . '</p></div>';
}

function operational_form_security( array $snapshot, array $intents ) {
	$state = operational_state( $snapshot );
	operational_hidden( 'event_id', $snapshot['id'] );
	operational_hidden( 'state', $state );
	foreach ( $intents as $intent ) {
		operational_hidden( 'nonces[' . $intent . ']', wp_create_nonce( operational_nonce_action( $snapshot['id'], $intent, $state ) ) );
	}
}

function render_operational_form( array $snapshot, $result = null, array $request = array() ) {
	$id = $snapshot['id'];
	// Recheck access at render as well as load; never expose unauthorized inputs.
	$current = operational_snapshot( $id );
	if ( is_wp_error( $current ) ) { operational_result_notice( $current ); return; }
	echo '<div class="wrap casa-operational"><h1>' . esc_html( $id ? __( 'Editar evento', 'casa-eventos' ) : __( 'Cargar evento', 'casa-eventos' ) ) . '</h1>';
	echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . Core\POST_TYPE ) ) . '">' . esc_html__( 'Volver a Eventos', 'casa-eventos' ) . '</a></p>';
	if ( null === $result && isset( $_GET['receipt'] ) ) {
		$result = operational_read_receipt( wp_unslash( $_GET['receipt'] ), $id );
		if ( null === $result ) { $result = operational_error( 'nonce', 'El resultado del guardado venció o no es válido. Revisá la lista y recargá el evento antes de continuar.' ); }
	}
	operational_result_notice( $result );
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		$recovery = is_array( $data ) && ! empty( $data['partial'] );
		$blocked = $recovery || in_array( $result->get_error_code(), array( 'casa_eventos_ui_stale', 'casa_eventos_ui_nonce', 'casa_eventos_ui_exception' ), true );
		if ( $blocked ) {
			$known = $recovery && ! empty( $data['id'] ) ? (int) $data['id'] : $id;
			if ( $known ) { echo '<p><a href="' . esc_url( operational_url( $known ) ) . '">' . esc_html__( 'Recargar y revisar el evento guardado', 'casa-eventos' ) . '</a></p>'; }
			echo '<p>' . esc_html__( 'No reenvíes este formulario. Revisá el evento o la lista antes de continuar.', 'casa-eventos' ) . '</p></div>';
			return;
		}
	}
	$actions = operational_actions( $snapshot );
	if ( is_array( $result ) && isset( $result['confirm'] ) ) {
		$intent = $result['confirm'];
		echo '<form method="post" action="' . esc_url( operational_url( $id ) ) . '"><h2>' . esc_html( $actions[ $intent ] ) . '</h2><p>' . esc_html( operational_confirmation( $intent ) ) . '</p>';
		operational_form_security( $snapshot, array( $intent ) );
		echo '<label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'Confirmo esta acción sobre el evento guardado.', 'casa-eventos' ) . '</label><p>';
		printf( '<button class="button button-primary" name="intent" value="%s">%s</button>', esc_attr( $intent ), esc_html( $actions[ $intent ] ) );
		echo ' <a class="button" href="' . esc_url( operational_url( $id ) ) . '">' . esc_html__( 'Volver sin aplicar', 'casa-eventos' ) . '</a></p></form></div>';
		return;
	}
	$publication_labels = array( 'draft' => 'Borrador', 'auto-draft' => 'Borrador', 'publish' => 'Publicado', 'future' => 'Publicación programada (se conserva)', 'private' => 'Privado (se conserva)', 'pending' => 'Pendiente (se conserva)' );
	$event = $id ? Event::get( $id ) : null;
	echo '<p>' . esc_html__( 'Publicación:', 'casa-eventos' ) . ' <strong>' . esc_html( __( $publication_labels[ $snapshot['post_status'] ] ?? $snapshot['post_status'], 'casa-eventos' ) ) . '</strong>';
	if ( $event ) { echo ' · ' . esc_html__( 'Estado:', 'casa-eventos' ) . ' <strong>' . esc_html( Core\state_label( $event->effective_state() ) ) . '</strong>'; }
	echo '</p><p>' . esc_html__( 'Las fechas se interpretan en:', 'casa-eventos' ) . ' ' . esc_html( $snapshot['timezone'] ) . '</p>';
	if ( $id && ! Core\is_programador() && current_user_can( 'edit_post', $id ) ) {
		echo '<p><a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '">' . esc_html__( 'Abrir editor nativo / revisiones', 'casa-eventos' ) . '</a></p>';
	}
	$values = array();
	foreach ( $snapshot['fields'] as $key => $value ) { $values[ $key ] = operational_control_value( $key, $value ); }
	if ( is_wp_error( $result ) && isset( $request['fields'] ) && is_array( $request['fields'] ) ) {
		foreach ( $request['fields'] as $key => $value ) { if ( isset( $values[ $key ] ) && is_string( $value ) ) { $values[ $key ] = $value; } }
	}
	$editorial_actions = array_intersect_key( $actions, array_flip( array( 'save_draft', 'publish', 'update', 'preserve' ) ) );
	// Retained input is unsaved even when a newly initialized editor is clean.
	$content_pending = ! $snapshot['content_protected'] && $values['content'] !== $snapshot['fields']['content'];
	echo '<form method="post" action="' . esc_url( operational_url( $id ) ) . '" class="casa-operational-editorial" data-content-pending="' . ( $content_pending ? '1' : '0' ) . '">';
	operational_form_security( $snapshot, array_keys( $editorial_actions ) );
	echo '<h2>' . esc_html__( 'Datos del evento', 'casa-eventos' ) . '</h2>';
	operational_control( 'title', __( 'Título', 'casa-eventos' ), $values['title'], 'text', null, __( 'Obligatorio para publicar.', 'casa-eventos' ) );
	operational_control( 'excerpt', __( 'Bajada', 'casa-eventos' ), $values['excerpt'], 'textarea' );
	if ( $snapshot['content_protected'] ) {
		operational_notice( 'warning', __( 'La descripción contiene bloques y se conserva sin modificar. Para editarla, pedí ayuda a un administrador.', 'casa-eventos' ) );
	} else {
		echo '<div class="casa-operational-field"><label for="e32-content">' . esc_html__( 'Descripción', 'casa-eventos' ) . '</label>';
		wp_editor( $values['content'], 'e32-content', array( 'textarea_name' => 'fields[content]', 'media_buttons' => false, 'wpautop' => false, 'teeny' => true, 'quicktags' => false, 'textarea_rows' => 8, 'tinymce' => array( 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo', 'toolbar2' => '' ) ) );
		echo '</div>';
	}
	if ( current_user_can( 'upload_files' ) ) {
		echo '<div class="casa-operational-field" data-poster><label>' . esc_html__( 'Afiche', 'casa-eventos' ) . '</label>';
		operational_hidden( 'fields[poster_id]', $values['poster_id'] );
		echo '<div data-poster-preview>';
		$poster = operational_id( $values['poster_id'] );
		if ( ! is_wp_error( $poster ) && $poster && current_user_can( 'read_post', $poster ) ) { echo wp_get_attachment_image( $poster, 'thumbnail' ); }
		echo '</div><button type="button" class="button" data-poster-select>' . esc_html__( 'Elegir afiche', 'casa-eventos' ) . '</button> <button type="button" class="button" data-poster-clear>' . esc_html__( 'Quitar afiche', 'casa-eventos' ) . '</button>';
		echo '<noscript><p>' . esc_html__( 'La selección de afiche requiere JavaScript. El afiche actual se conserva.', 'casa-eventos' ) . '</p></noscript></div>';
	}
	if ( current_user_can( Core\category_capabilities()['assign_terms'] ) ) {
		$categories = array( '' => __( 'Sin categoría', 'casa-eventos' ) );
		if ( count( $snapshot['fields']['category_ids'] ) > 1 ) { $categories['__preserve'] = __( 'Conservar categorías actuales; elegí una para corregir', 'casa-eventos' ); }
		foreach ( Queries::categories() as $term ) { $categories[ $term->term_id ] = $term->name; }
		operational_control( 'category_ids', __( 'Categoría', 'casa-eventos' ), $values['category_ids'], 'text', $categories, __( 'Una categoría existente es obligatoria para publicar.', 'casa-eventos' ) );
	}
	operational_control( 'start', __( 'Inicio', 'casa-eventos' ), $values['start'], 'datetime-local', null, __( 'Obligatorio para publicar.', 'casa-eventos' ) );
	operational_control( 'end', __( 'Fin', 'casa-eventos' ), $values['end'], 'datetime-local', null, __( 'Opcional; dejar vacío conserva el cálculo automático del fin.', 'casa-eventos' ) );
	echo '<h2>' . esc_html__( 'Acceso', 'casa-eventos' ) . '</h2>';
	operational_control( 'access_mode', __( 'Modalidad de acceso', 'casa-eventos' ), $values['access_mode'], 'text', array( '' => __( 'Sin especificar', 'casa-eventos' ) ) + Core\access_mode_labels() );
	echo '<p data-tickets-note' . ( Core\ACCESS_TICKETS !== $values['access_mode'] ? ' hidden' : '' ) . '>' . esc_html__( 'La venta propia todavía no está disponible; el evento se publica sin botón de compra.', 'casa-eventos' ) . '</p>';
	operational_control( 'entry_kind', __( 'Tipo de entrada', 'casa-eventos' ), $values['entry_kind'], 'text', array( '' => __( 'Sin especificar', 'casa-eventos' ) ) + Core\entry_kind_labels() );
	operational_control( 'entry_label', __( 'Texto de entrada', 'casa-eventos' ), $values['entry_label'] );
	// Keep URL visible without JS; switching modality never clears it.
	echo '<div data-external-url>';
	operational_control( 'external_url', __( 'Enlace externo', 'casa-eventos' ), $values['external_url'], 'url', null, __( 'Obligatorio en modalidad externa. Se conserva al cambiar de modalidad.', 'casa-eventos' ) );
	echo '</div>';
	operational_control( 'capacity', __( 'Aforo', 'casa-eventos' ), $values['capacity'], 'text', null, sprintf( __( 'Vacío restablece el aforo predeterminado: %d.', 'casa-eventos' ), Core\default_capacity() ) );
	operational_control( 'sales_close', __( 'Cierre de venta', 'casa-eventos' ), $values['sales_close'], 'datetime-local', null, __( 'Vacío usa el cierre automático. Se aplica a la venta propia.', 'casa-eventos' ) );
	echo '<h2>' . esc_html__( 'Visibilidad', 'casa-eventos' ) . '</h2>';
	foreach ( array( 'listed' => 'En Agenda', 'featured' => 'Destacado en Home' ) as $key => $label ) {
		operational_hidden( 'fields[' . $key . ']', '0' );
		echo '<p><label><input type="checkbox" name="fields[' . esc_attr( $key ) . ']" value="1"' . ( '1' === $values[ $key ] ? ' checked' : '' ) . '> ' . esc_html( __( $label, 'casa-eventos' ) ) . '</label></p>';
	}
	echo '<p>' . esc_html__( 'Fuera de Agenda conserva su URL pública. Destacado depende de la elegibilidad del evento.', 'casa-eventos' ) . '</p><p class="casa-operational-buttons">';
	foreach ( $editorial_actions as $intent => $label ) { printf( '<button class="button button-primary" name="intent" value="%s">%s</button> ', esc_attr( $intent ), esc_html( __( $label, 'casa-eventos' ) ) ); }
	echo '</p></form>';
	if ( $id ) {
		echo '<section><h2>' . esc_html__( 'Acciones sobre el evento guardado', 'casa-eventos' ) . '</h2><p>' . esc_html__( 'Guardá primero los cambios del formulario. Estas acciones no guardan cambios editoriales pendientes y no cambian la publicación, salvo Volver a borrador.', 'casa-eventos' ) . '</p>';
		if ( Core\STATUS_CANCELLED === $snapshot['fields']['status'] && ! $snapshot['can_reactivate'] ) { operational_notice( 'warning', __( 'Evento cancelado. Solo un administrador puede reactivarlo.', 'casa-eventos' ) ); }
		foreach ( array_diff_key( $actions, $editorial_actions ) as $intent => $label ) {
			echo '<form method="post" action="' . esc_url( operational_url( $id ) ) . '" class="casa-operational-action">';
			operational_form_security( $snapshot, array( $intent ) );
			printf( '<button class="button" name="intent" value="%s">%s</button>', esc_attr( $intent ), esc_html( __( $label, 'casa-eventos' ) ) );
			echo '</form>';
		}
		echo '</section>';
	}
	echo '</div>';
}

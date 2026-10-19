<?php
/** Server-rendered ticket configuration; consumes commercial DTOs/services only. */
namespace CasaEventos\Admin;
use CasaEventos\Commerce;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Administrators retain native editorial editing, with an explicit commercial link. */
function ticket_configuration_link( $actions, $post ) {
	if ( ! is_wp_error( Commerce\authorize( (int) $post->ID ) ) ) {
		$actions['casa_ticket_types'] = '<a href="' . esc_url( operational_url( (int) $post->ID ) . '#casa-ticket-types' ) . '">Tipos de entrada</a>';
	}
	return $actions;
}
add_filter( 'post_row_actions', __NAMESPACE__ . '\\ticket_configuration_link', 20, 2 );

function ticket_type_submit( array $request, $method ) {
	if ( 'POST' !== $method ) { return Commerce\error( 'method', 'El guardado requiere POST.' ); }
	$id = operational_id( $request['event_id'] ?? null );
	$type = operational_id( $request['type_id'] ?? null );
	if ( is_wp_error( $id ) || ! $id || is_wp_error( $type ) ) { return Commerce\error( 'request', 'Los identificadores no son válidos.' ); }
	$auth = Commerce\authorize( $id );
	if ( is_wp_error( $auth ) ) { return $auth; }
	$nonce = $request['ticket_nonce'] ?? null;
	if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'casa-ticket:' . $id . ':' . $type ) ) { return Commerce\error( 'nonce', 'La solicitud venció. Recargá el evento.' ); }
	if ( ! isset( $request['ticket_fields'] ) || ! is_array( $request['ticket_fields'] ) ) { return Commerce\error( 'fields', 'Faltan los datos del tipo.' ); }
	return Commerce\save_type( $id, $type, $request['ticket_fields'], $request['ticket_state'] ?? null, $request['ticket_key'] ?? null );
}

function render_ticket_types( $id, $result = null, array $request = array() ) {
	if ( ! $id || is_wp_error( Commerce\authorize( $id ) ) ) { return; }
	echo '<section id="casa-ticket-types" class="casa-ticket-types"><h2>Tipos de entrada</h2><p>La venta propia todavía no está disponible. Guardá los datos del evento antes de modificar tipos.</p>';
	if ( null !== $result ) {
		if ( is_wp_error( $result ) ) { operational_result_notice( $result ); }
		else { operational_notice( 'success', 'Tipo guardado. La venta continúa deshabilitada.' ); }
	}
	$view = Commerce\configuration( $id );
	if ( is_wp_error( $view ) ) { operational_notice( 'warning', $view->get_error_message() ); echo '</section>'; return; }
	echo '<p>Aforo global: ' . esc_html( $view['capacity'] ) . '. Moneda: ' . esc_html( $view['currency'] ) . '. Zona: ' . esc_html( $view['timezone'] ) . '.</p>';
	if ( ! $view['consumption_known'] ) { operational_notice( 'warning', 'Consumo no disponible: no se puede modificar stock absoluto ni reducir el aforo.' ); }
	$rows = $view['types'];
	$rows[] = array( 'id' => 0, 'name' => '', 'price' => '', 'capacity' => '0', 'active' => false, 'cutoff' => '', 'maximum' => 10 );
	foreach ( $rows as $row ) {
		$tid = $row['id'];
		// Retain rejected input only for correctable errors, never silently refresh a stale request.
		$blocked = is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'casa_tickets_stale', 'casa_tickets_partial', 'casa_tickets_conflict' ), true );
		if ( is_wp_error( $result ) && ! $blocked && (string) $tid === ( $request['type_id'] ?? null ) && isset( $request['ticket_fields'] ) && is_array( $request['ticket_fields'] ) ) {
			foreach ( $request['ticket_fields'] as $key => $value ) { if ( array_key_exists( $key, $row ) && is_scalar( $value ) ) { $row[ $key ] = $value; } }
		}
		echo '<details' . ( ! $tid ? ' open' : '' ) . '><summary>' . esc_html( $tid ? $row['name'] : 'Agregar tipo' ) . '</summary>';
		if ( $tid ) { echo '<p>Corte efectivo (UTC): ' . esc_html( $row['effective_cutoff_gmt'] ?: 'pendiente de fecha del evento' ) . '. Nunca extiende el corte del evento.</p>'; }
		if ( $blocked ) { echo '<p><a href="' . esc_url( operational_url( $id ) ) . '">Recargar la configuración guardada</a> antes de continuar.</p></details>'; continue; }
		echo '<form method="post" class="casa-operational-action casa-ticket-form" action="' . esc_url( operational_url( $id ) ) . '">';
		foreach ( array( 'event_id' => $id, 'type_id' => $tid, 'ticket_intent' => 'save', 'ticket_state' => $view['state'], 'ticket_key' => wp_generate_uuid4(), 'ticket_nonce' => wp_create_nonce( 'casa-ticket:' . $id . ':' . $tid ) ) as $key => $value ) { operational_hidden( $key, $value ); }
		foreach ( array( 'name' => 'Nombre', 'price' => 'Precio nominal (sin definición fiscal)', 'capacity' => 'Cupo / stock configurado', 'cutoff' => 'Corte propio (vacío hereda el del evento)', 'maximum' => 'Máximo por compra' ) as $key => $label ) {
			$cap = 'price' === $key ? 'edit_casa_ticket_prices' : ( 'capacity' === $key ? 'edit_casa_ticket_capacities' : 'manage_casa_ticket_types' );
			$control = 'casa-ticket-' . $tid . '-' . $key;
			$value = 'cutoff' === $key ? str_replace( ' ', 'T', $row[ $key ] ) : $row[ $key ];
			echo '<p><label for="' . esc_attr( $control ) . '">' . esc_html( $label ) . '</label><br><input id="' . esc_attr( $control ) . '" name="ticket_fields[' . esc_attr( $key ) . ']" type="' . ( 'cutoff' === $key ? 'datetime-local' : 'text' ) . '" value="' . esc_attr( $value ) . '"' . ( 'cutoff' === $key ? ' step="1"' : '' ) . ( current_user_can( $cap ) ? '' : ' disabled' ) . '></p>';
		}
		operational_hidden( 'ticket_fields[active]', '0' );
		echo '<p><label><input type="checkbox" name="ticket_fields[active]" value="1"' . ( $row['active'] && '0' !== $row['active'] ? ' checked' : '' ) . '> Activo (configuración; no habilita ventas)</label></p><button class="button button-primary">Guardar tipo</button></form></details>';
	}
	echo '</section>';
}

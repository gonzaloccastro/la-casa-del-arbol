<?php
/** Event-scoped administrator download from the operational Event screen. */
namespace CasaEventos\Admin;

use CasaEventos\Commerce;

if ( ! defined( 'ABSPATH' ) ) { exit; }

function render_buyer_export( $event_id ) {
	if ( ! Commerce\export_authorized( (int) $event_id ) ) { return; }
	$url = admin_url( 'admin-post.php' );
	echo '<section id="casa-buyer-export"><h2>Compradores y entradas</h2>';
	echo '<p>Descargá el listado del evento para control de puerta. Incluye pedidos pendientes, estados de emisión y una columna de ingreso para completar en la planilla.</p>';
	echo '<form method="post" action="' . esc_url( $url ) . '">';
	echo '<input type="hidden" name="action" value="lcda_event_buyers_export">';
	echo '<input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
	wp_nonce_field( 'lcda_event_buyers_export:' . $event_id );
	echo '<button type="submit" class="button">Exportar listado (.xlsx)</button></form></section>';
}

function buyer_export_post() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'Método no permitido', '', array( 'response' => 405 ) ); }
	$raw = isset( $_POST['event_id'] ) ? wp_unslash( $_POST['event_id'] ) : null;
	$id = operational_id( $raw );
	if ( is_wp_error( $id ) || ! $id ) { wp_die( 'Evento inválido', '', array( 'response' => 400 ) ); }
	if ( ! Commerce\export_authorized( $id ) ) { wp_die( 'Acceso denegado', '', array( 'response' => 403 ) ); }
	$nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : null;
	if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'lcda_event_buyers_export:' . $id ) ) {
		wp_die( 'Solicitud inválida', '', array( 'response' => 403 ) );
	}
	$path = tempnam( get_temp_dir(), 'lcda-buyers-' );
	if ( ! $path ) { wp_die( 'No se pudo crear el archivo', '', array( 'response' => 500 ) ); }
	register_shutdown_function( static function () use ( $path ) { @unlink( $path ); } );
	$result = Commerce\export_write_file( $id, $path );
	if ( is_wp_error( $result ) ) {
		@unlink( $path );
		wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 500 ) );
	}
	nocache_headers();
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="compradores-evento-' . $id . '.xlsx"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path );
	@unlink( $path );
	exit;
}
add_action( 'admin_post_lcda_event_buyers_export', __NAMESPACE__ . '\\buyer_export_post' );

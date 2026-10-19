<?php
/** All own ticket purchases stay closed in T1, including direct Woo entry points. */
namespace CasaEventos\Commerce;
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ticket_product( $product ) {
	if ( ! is_object( $product ) ) { return false; }
	if ( $product->get_meta( '_lcda_ticket_event' ) || $product->get_meta( '_lcda_ticket_uuid' ) ) { return true; }
	$sku = (string) $product->get_sku();
	if ( 0 === strpos( $sku, 'lcda-event-' ) || 0 === strpos( $sku, 'lcda-type-' ) ) { return true; }
	$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;
	return $parent && ( $parent->get_meta( '_lcda_ticket_event' ) || 0 === strpos( (string) $parent->get_sku(), 'lcda-event-' ) );
}
function deny_purchase( $allowed, $product ) { return ticket_product( $product ) ? false : $allowed; }
add_filter( 'woocommerce_is_purchasable', __NAMESPACE__ . '\\deny_purchase', PHP_INT_MAX, 2 );
add_filter( 'woocommerce_variation_is_purchasable', __NAMESPACE__ . '\\deny_purchase', PHP_INT_MAX, 2 );
add_filter( 'casa_eventos/ticket_sales_available', '__return_false', PHP_INT_MAX );

function deny_cart( $allowed, $product_id, $quantity, $variation_id = 0 ) {
	return ticket_product( wc_get_product( $variation_id ?: $product_id ) ) ? false : $allowed;
}
add_filter( 'woocommerce_add_to_cart_validation', __NAMESPACE__ . '\\deny_cart', PHP_INT_MAX, 4 );

function deny_order_payment( $needed, $order ) {
	foreach ( $order->get_items() as $item ) {
		if ( ticket_product( $item->get_product() ) ) { return false; }
	}
	return $needed;
}
add_filter( 'woocommerce_order_needs_payment', __NAMESPACE__ . '\\deny_order_payment', PHP_INT_MAX, 2 );

function deny_product_page() {
	if ( ! available() || ! is_singular( 'product' ) ) { return; }
	if ( ticket_product( wc_get_product( get_queried_object_id() ) ) ) { wp_die( esc_html__( 'La venta propia todavía no está disponible.', 'casa-eventos' ), '', array( 'response' => 404 ) ); }
}
add_action( 'template_redirect', __NAMESPACE__ . '\\deny_product_page' );

/** Preserve commercial records; administrator edits also use the Event service. */
function guard_product_save( $product ) {
	if ( ! empty( $GLOBALS['lcda_ticket_writing'] ) ) {
		$event_id = (int) $product->get_meta( '_lcda_ticket_event' );
		if ( ! $event_id && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$event_id = $parent ? (int) $parent->get_meta( '_lcda_ticket_event' ) : 0;
		}
		if ( $event_id && ! owns_lock( $event_id ) ) { throw new \Exception( 'Se perdió el bloqueo comercial.' ); }
	}
	if ( empty( $GLOBALS['lcda_ticket_writing'] ) ) {
		$stored = $product->get_id() ? wc_get_product( $product->get_id() ) : null;
		if ( ticket_product( $product ) || ticket_product( $stored ) ) {
			// Woo queues a derived variable-parent sync when a variation is saved.
			// It may run at shutdown, after the configuration writer released its lock.
			$syncing_parent = $product->is_type( 'variable' ) && $stored && ticket_product( $stored );
			if ( $syncing_parent ) {
				$syncing_parent = false;
				foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ) as $frame ) {
					if ( 'WC_Product_Variable' === ( $frame['class'] ?? '' ) && 'sync' === ( $frame['function'] ?? '' ) ) {
						$syncing_parent = true;
						break;
					}
				}
			}
			if ( ! $syncing_parent ) { throw new \Exception( 'Los tipos de entrada se modifican desde Eventos.' ); }
		}
	}
}
add_action( 'woocommerce_before_product_object_save', __NAMESPACE__ . '\\guard_product_save' );
add_action( 'woocommerce_before_product_variation_object_save', __NAMESPACE__ . '\\guard_product_save' );

function guard_delete( $delete, $post ) {
	if ( available() && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) && ticket_product( wc_get_product( $post->ID ) ) ) { return false; }
	return $delete;
}
add_filter( 'pre_delete_post', __NAMESPACE__ . '\\guard_delete', 10, 2 );
add_filter( 'pre_trash_post', __NAMESPACE__ . '\\guard_delete', 10, 2 );

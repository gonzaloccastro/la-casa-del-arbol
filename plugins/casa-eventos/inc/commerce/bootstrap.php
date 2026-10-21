<?php
/** T1 optional commercial administration. No checkout, reservations or payments. */
namespace CasaEventos\Commerce;
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/ticket-types.php';
require_once __DIR__ . '/ticket-validation.php';
require_once __DIR__ . '/capacity-guard.php';
require_once __DIR__ . '/ticket-write.php';
require_once __DIR__ . '/reservations.php';
require_once __DIR__ . '/checkout.php';
require_once __DIR__ . '/tickets.php';
require_once __DIR__ . '/reminders.php';
require_once __DIR__ . '/refunds.php';
require_once __DIR__ . '/export.php';
require_once __DIR__ . '/purchase-guard.php';

function available() {
	return class_exists( 'WC_Product_Variable' ) && class_exists( 'WC_Product_Variation' )
		&& class_exists( 'WC_Product_Attribute' ) && function_exists( 'wc_get_products' )
		&& function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_held_stock_quantity' )
		&& function_exists( 'wc_get_product' ) && function_exists( 'wc_get_product_id_by_sku' )
		&& function_exists( 'wc_get_order_statuses' ) && function_exists( 'get_woocommerce_currency' )
		&& function_exists( 'wc_get_price_decimals' );
}

function error( $code, $message, $data = array() ) {
	return new \WP_Error( 'casa_tickets_' . $code, __( $message, 'casa-eventos' ), $data );
}

/** Public custom query argument, translated only inside the product datastore adapter. */
function product_query( $args, $vars ) {
	if ( isset( $vars['lcda_event'] ) ) {
		$args['meta_query'][] = array( 'key' => '_lcda_ticket_event', 'value' => (int) $vars['lcda_event'] );
	}
	return $args;
}
add_filter( 'woocommerce_product_data_store_cpt_get_products_query', __NAMESPACE__ . '\\product_query', 10, 2 );

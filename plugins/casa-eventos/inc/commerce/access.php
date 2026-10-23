<?php
/** Woo's customer-only admin gate needs a narrow exception for Event staff. */
namespace CasaEventos\Commerce;

use CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

function programador_woo_admin_access( $prevent ) {
	return Core\is_programador() ? false : $prevent;
}
add_filter( 'woocommerce_prevent_admin_access', __NAMESPACE__ . '\\programador_woo_admin_access' );

function programador_woo_admin_bar( $disable ) {
	return Core\is_programador() ? false : $disable;
}
add_filter( 'woocommerce_disable_admin_bar', __NAMESPACE__ . '\\programador_woo_admin_bar' );

function programador_woo_login_redirect( $redirect, $user ) {
	return $user instanceof \WP_User && Core\is_programador( $user )
		? \CasaEventos\Admin\operational_url() : $redirect;
}
add_filter( 'woocommerce_login_redirect', __NAMESPACE__ . '\\programador_woo_login_redirect', 10, 2 );

/** Account pages belong to customers; Woo's logout endpoint remains usable. */
function programador_account_destination( $is_account, $is_logout ) {
	return Core\is_programador() && $is_account && ! $is_logout
		? \CasaEventos\Admin\operational_url() : null;
}
function programador_account_redirect() {
	$destination = programador_account_destination(
		function_exists( 'is_account_page' ) && is_account_page(),
		function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'customer-logout' )
	);
	if ( $destination ) { wp_safe_redirect( $destination ); exit; }
}
add_action( 'template_redirect', __NAMESPACE__ . '\\programador_account_redirect', 1 );

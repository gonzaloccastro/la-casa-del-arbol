<?php
/**
 * Menu locations for the site chrome.
 *
 * All navigation and contact data is managed in Appearance → Menus. The
 * theme registers locations only; it never creates menus, menu items or
 * pages. A location without an assigned menu renders nothing.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register menu locations.
 *
 * Prefixed slugs keep them independent of Astra's own locations.
 */
function lcda_register_menus() {
	register_nav_menus(
		array(
			'lcda-primary' => __( 'La Casa — Navegación principal (header y menú mobile)', 'la-casa-del-arbol' ),
			'lcda-footer'  => __( 'La Casa — Footer: Navegación', 'la-casa-del-arbol' ),
			'lcda-contact' => __( 'La Casa — Footer: Visitanos (dirección, teléfono, Instagram)', 'la-casa-del-arbol' ),
			'lcda-cta'     => __( 'La Casa — CTA WhatsApp (se usa el primer ítem)', 'la-casa-del-arbol' ),
		)
	);
}
add_action( 'after_setup_theme', 'lcda_register_menus' );

/**
 * Drop the per-item id attribute on theme menus.
 *
 * The primary menu is rendered twice (desktop bar and mobile dialog), so the
 * default "menu-item-{ID}" ids would be duplicated in the document.
 *
 * @param string   $id   Item id attribute.
 * @param WP_Post  $item Menu item.
 * @param stdClass $args wp_nav_menu() arguments.
 * @return string
 */
function lcda_menu_item_id( $id, $item, $args ) {
	return empty( $args->lcda_menu ) ? $id : '';
}
add_filter( 'nav_menu_item_id', 'lcda_menu_item_id', 10, 3 );

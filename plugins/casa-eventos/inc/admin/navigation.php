<?php
/**
 * Programador navigation: a narrow wp-admin menu and admin bar.
 *
 * Presentation only. Authorization stays capability-based
 * (core/capabilities.php): nothing here grants or removes a capability, and
 * screens removed from the menu (Escritorio, Perfil) remain reachable by
 * URL because WordPress needs them for normal account behavior.
 *
 * Loaded on every request (not only in wp-admin): the admin bar is also
 * shown on the public site.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Admin;

use CasaEventos\Core;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The events list screen (the Programador's workspace).
 *
 * @return string Admin page slug.
 */
function events_menu_slug() {
	return 'edit.php?post_type=' . Core\POST_TYPE;
}

/**
 * Top-level menu entries the Programador sees: Eventos and Medios.
 *
 * @return string[] Menu slugs.
 */
function programador_menu_slugs() {
	return array( events_menu_slug(), 'upload.php' );
}

/**
 * Remove every other top-level menu entry (Escritorio, Perfil, separators
 * and anything another plugin adds) for the Programador. An allowlist, so
 * later menus do not leak into the operational navigation.
 */
function filter_programador_menu() {
	global $menu;
	if ( ! Core\is_programador() || ! is_array( $menu ) ) {
		return;
	}
	foreach ( $menu as $item ) {
		$slug = $item[2] ?? '';
		if ( '' !== $slug && ! in_array( $slug, programador_menu_slugs(), true ) ) {
			remove_menu_page( $slug );
		}
	}
}
add_action( 'admin_menu', __NAMESPACE__ . '\\filter_programador_menu', PHP_INT_MAX );

const LOGOUT_NODE = 'casa-eventos-salir';

/**
 * Admin bar nodes the Programador keeps. In wp-admin, the site name (public
 * site) and the mobile menu toggle, without which the left menu cannot be
 * opened on a phone. On the public site, only the site name. Everywhere,
 * the right-hand group that holds the "Salir" action.
 *
 * @return string[] Node IDs.
 */
function programador_admin_bar_nodes() {
	return is_admin() ? array( 'site-name', 'menu-toggle', 'top-secondary' ) : array( 'site-name', 'top-secondary' );
}

/**
 * Reduce the admin bar to navigation + logout for the Programador: no
 * WordPress logo, updates, comments, "+ Nuevo", account menu, search or
 * site-name submenu. The site-name node opens the public site from
 * wp-admin; on the public site it leads back to the events list. "Salir"
 * uses WordPress's own logout URL (with its nonce).
 *
 * @param \WP_Admin_Bar $bar Admin bar.
 */
function filter_programador_admin_bar( $bar ) {
	if ( ! Core\is_programador() ) {
		return;
	}
	$keep = programador_admin_bar_nodes();
	foreach ( array_keys( (array) $bar->get_nodes() ) as $id ) {
		if ( ! in_array( $id, $keep, true ) ) {
			$bar->remove_node( $id );
		}
	}
	if ( is_admin() ) {
		$bar->add_node(
			array(
				'id'   => 'site-name',
				'href' => home_url( '/' ),
			)
		);
	} else {
		$bar->add_node(
			array(
				'id'    => 'site-name',
				'title' => __( 'Eventos', 'casa-eventos' ),
				'href'  => admin_url( events_menu_slug() ),
			)
		);
	}
	$bar->add_node(
		array(
			'id'     => LOGOUT_NODE,
			'parent' => 'top-secondary',
			'title'  => __( 'Salir', 'casa-eventos' ),
			'href'   => wp_logout_url(),
		)
	);
}
add_action( 'admin_bar_menu', __NAMESPACE__ . '\\filter_programador_admin_bar', PHP_INT_MAX );

/**
 * After logging in without a specific destination, the Programador lands
 * on the events list instead of the (hidden) Escritorio.
 *
 * @param string           $redirect_to Destination.
 * @param string           $requested   Requested destination ('' = none).
 * @param WP_User|\WP_Error $user        User.
 * @return string
 */
function programador_login_redirect( $redirect_to, $requested, $user ) {
	if ( ! $user instanceof WP_User || ! Core\is_programador( $user ) ) {
		return $redirect_to;
	}
	if ( '' === (string) $requested || untrailingslashit( $requested ) === untrailingslashit( admin_url() ) ) {
		return admin_url( events_menu_slug() );
	}
	return $redirect_to;
}
add_filter( 'login_redirect', __NAMESPACE__ . '\\programador_login_redirect', 10, 3 );

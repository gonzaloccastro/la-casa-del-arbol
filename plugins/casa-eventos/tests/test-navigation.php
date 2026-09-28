<?php
/**
 * Programador navigation (inc/admin/navigation.php): left menu allowlist,
 * admin bar reduced to public-site navigation, login destination, and the
 * guarantee that navigation never touches capabilities.
 *
 * The rendered menu and admin bar are verified in the LocalWP runtime QA.
 *
 * @package CasaEventos
 */

use CasaEventos\Core;
use CasaEventos\Admin;

// ----- Stand-ins used only by the navigation module ----------------------------------------

function remove_menu_page( $slug ) {
	foreach ( $GLOBALS['menu'] as $i => $item ) {
		if ( $item[2] === $slug ) {
			unset( $GLOBALS['menu'][ $i ] );
			return $item;
		}
	}
	return false;
}
function home_url( $path = '' ) {
	return 'http://site.test' . $path;
}
function admin_url( $path = '' ) {
	return 'http://site.test/wp-admin/' . $path;
}
function wp_logout_url() {
	return 'http://site.test/wp-login.php?action=logout&_wpnonce=abc123';
}
function untrailingslashit( $s ) {
	return rtrim( (string) $s, '/\\' );
}
/** Admin bar stand-in: flat node map with parents, like WP_Admin_Bar. */
class WP_Admin_Bar {
	public $nodes = array();
	public function add_node( array $args ) {
		$id                 = $args['id'];
		$this->nodes[ $id ] = (object) array_merge( (array) ( $this->nodes[ $id ] ?? array( 'id' => $id, 'title' => '', 'href' => '', 'parent' => false ) ), $args );
	}
	public function remove_node( $id ) {
		unset( $this->nodes[ $id ] );
	}
	public function get_nodes() {
		return $this->nodes;
	}
}

require_once dirname( __DIR__ ) . '/inc/admin/navigation.php';

/** A core-like admin menu for a user with read + upload_files + Event caps. */
function t_core_menu() {
	return array(
		2   => array( 'Escritorio', 'read', 'index.php' ),
		4   => array( '', 'read', 'separator1' ),
		5   => array( 'Eventos', 'edit_casa_eventos', 'edit.php?post_type=casa_evento' ),
		10  => array( 'Medios', 'upload_files', 'upload.php' ),
		59  => array( '', 'read', 'separator2' ),
		70  => array( 'Perfil', 'read', 'profile.php' ),
		99  => array( '', 'read', 'separator-last' ),
		100 => array( 'Otro plugin', 'read', 'otro-plugin' ),
	);
}
/** A core-like admin bar (wp-admin). */
function t_core_bar() {
	$bar = new WP_Admin_Bar();
	foreach ( array( 'menu-toggle', 'wp-logo', 'about', 'wporg', 'site-name', 'view-site', 'updates', 'comments', 'new-content', 'new-casa_evento', 'new-media', 'top-secondary', 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout', 'search' ) as $id ) {
		$bar->add_node( array( 'id' => $id, 'title' => $id, 'href' => 'http://site.test/' . $id ) );
	}
	$bar->add_node( array( 'id' => 'site-name', 'title' => 'La Casa del Árbol', 'href' => home_url( '/' ) ) );
	return $bar;
}

t_reset_roles( array( 'administrator' => array( 'read', 'manage_options' ) ) );
Core\install_roles();
$programador = new WP_User( 7, array( 'casa_programador' ), array_keys( array_filter( get_role( 'casa_programador' )->capabilities ) ) );
$admin       = new WP_User( 1, array( 'administrator' ), array_keys( array_filter( get_role( 'administrator' )->capabilities ) ) );
$admin_prog  = new WP_User( 3, array( 'administrator', 'casa_programador' ), array_merge( $admin->roles, array_keys( $admin->allcaps ) ) );
$editor      = new WP_User( 4, array( 'editor' ), array( 'read', 'edit_posts', 'edit_pages', 'upload_files' ) );

t_group( 'navigation/who gets the Programador navigation' );
t_ok( Core\is_programador( $programador ), 'Programador' );
t_ok( ! Core\is_programador( $admin ), 'not the administrator' );
t_ok( ! Core\is_programador( $admin_prog ), 'not an administrator who also holds the role' );
t_ok( ! Core\is_programador( $editor ), 'not other roles' );

t_group( 'navigation/Programador left menu' );
$GLOBALS['t_current_user'] = $programador;
$GLOBALS['menu']           = t_core_menu();
Admin\filter_programador_menu();
t_eq( array( 'edit.php?post_type=casa_evento', 'upload.php' ), array_values( array_column( $GLOBALS['menu'], 2 ) ), 'only Eventos and Medios (no Escritorio, Perfil, separators or other plugins)' );

t_group( 'navigation/Programador admin bar' );
$GLOBALS['t_is_admin'] = true;
$bar                   = t_core_bar();
Admin\filter_programador_admin_bar( $bar );
t_eq( array( 'menu-toggle', 'site-name', 'top-secondary', 'casa-eventos-salir' ), array_keys( $bar->get_nodes() ), 'wp-admin: mobile menu toggle, site name, and the right-hand group with "Salir" only' );
t_eq( 'http://site.test/', $bar->get_nodes()['site-name']->href, 'site name opens the public site' );
$salir = $bar->get_nodes()['casa-eventos-salir'];
t_eq( array( 'Salir', 'top-secondary', wp_logout_url() ), array( $salir->title, $salir->parent, $salir->href ), 'wp-admin: "Salir" uses the core logout URL (nonce) on the right' );
foreach ( array( 'wp-logo', 'updates', 'comments', 'new-content', 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout', 'view-site', 'search' ) as $gone ) {
	t_ok( ! isset( $bar->get_nodes()[ $gone ] ), "no $gone" );
}
$GLOBALS['t_is_admin'] = false;
$bar                   = t_core_bar();
Admin\filter_programador_admin_bar( $bar );
t_eq( array( 'site-name', 'top-secondary', 'casa-eventos-salir' ), array_keys( $bar->get_nodes() ), 'public site: "Eventos" and "Salir" only' );
t_eq( array( 'Eventos', 'http://site.test/wp-admin/edit.php?post_type=casa_evento' ), array( $bar->get_nodes()['site-name']->title, $bar->get_nodes()['site-name']->href ), 'public site: "Eventos" leads back to the events list' );
t_eq( array( 'Salir', wp_logout_url() ), array( $bar->get_nodes()['casa-eventos-salir']->title, $bar->get_nodes()['casa-eventos-salir']->href ), 'public site: "Salir" (core logout URL)' );
t_ok( ! isset( $bar->get_nodes()['my-account'] ) && ! isset( $bar->get_nodes()['edit-profile'] ), 'public site: no account/profile menu' );

t_group( 'navigation/administrator unchanged' );
$GLOBALS['t_current_user'] = $admin;
$GLOBALS['menu']           = t_core_menu();
Admin\filter_programador_menu();
t_eq( t_core_menu(), $GLOBALS['menu'], 'administrator menu untouched' );
$GLOBALS['t_is_admin'] = true;
$bar                   = t_core_bar();
Admin\filter_programador_admin_bar( $bar );
t_eq( array_keys( t_core_bar()->get_nodes() ), array_keys( $bar->get_nodes() ), 'administrator admin bar untouched (no extra "Salir" node)' );
$GLOBALS['t_current_user'] = $admin_prog;
$GLOBALS['menu']           = t_core_menu();
Admin\filter_programador_menu();
t_eq( t_core_menu(), $GLOBALS['menu'], 'administrator holding the role: menu untouched' );
$GLOBALS['t_is_admin'] = false;

t_group( 'navigation/login destination' );
t_eq( 'http://site.test/wp-admin/edit.php?post_type=casa_evento', Admin\programador_login_redirect( admin_url(), '', $programador ), 'Programador without destination → events list' );
t_eq( 'http://site.test/wp-admin/edit.php?post_type=casa_evento', Admin\programador_login_redirect( admin_url(), 'http://site.test/wp-admin/', $programador ), 'Programador sent to /wp-admin/ → events list' );
t_eq( 'http://site.test/wp-admin/upload.php', Admin\programador_login_redirect( 'http://site.test/wp-admin/upload.php', 'http://site.test/wp-admin/upload.php', $programador ), 'explicit destination kept' );
t_eq( admin_url(), Admin\programador_login_redirect( admin_url(), '', $admin ), 'administrator unchanged' );

t_group( 'navigation/never authorization' );
$src = file_get_contents( dirname( __DIR__ ) . '/inc/admin/navigation.php' );
t_ok( ! preg_match( '/add_cap|remove_cap|add_role|remove_role|user_has_cap|map_meta_cap|wp_die|wp_redirect|wp_safe_redirect/', $src ), 'navigation.php never changes capabilities, denies or redirects requests' );
t_ok( ! preg_match( '/show_admin_bar|admin_bar_init/', $src ), 'the admin bar is not disabled' );
$p = array_keys( array_filter( get_role( 'casa_programador' )->capabilities ) );
t_ok( in_array( 'read', $p, true ) && in_array( 'upload_files', $p, true ), 'Programador keeps read and upload_files' );
t_eq( array(), array_values( preg_grep( '/^delete_/', $p ) ), 'no delete capability' );

t_group( 'capabilities/Editor, Author, Contributor get no Event capability (closed decision)' );
t_reset_roles(
	array(
		'administrator' => array( 'read', 'manage_options' ),
		'editor'        => array( 'read', 'edit_posts', 'edit_others_posts', 'publish_posts', 'edit_pages', 'manage_categories', 'upload_files' ),
		'author'        => array( 'read', 'edit_posts', 'publish_posts', 'upload_files' ),
		'contributor'   => array( 'read', 'edit_posts' ),
	)
);
Core\install_roles();
foreach ( array( 'editor', 'author', 'contributor' ) as $slug ) {
	t_eq( array(), array_values( array_intersect( Core\plugin_capabilities(), array_keys( get_role( $slug )->capabilities ) ) ), "$slug: none of the 14 plugin capabilities" );
}

unset( $GLOBALS['t_current_user'], $GLOBALS['menu'] );
t_reset_roles();

<?php
/**
 * Authorization model: Event and category capabilities, and the
 * plugin-owned "Programador" role.
 *
 * - casa_evento uses its own primitive capabilities (*_casa_eventos) with
 *   map_meta_cap, so generic post permissions (edit_posts, …) never grant
 *   access to events, and event access never grants access to posts/pages.
 * - casa_categoria has separate capabilities: administrators create, edit
 *   and delete categories; the Programador only assigns existing ones.
 * - Programador (casa_programador): operational staff who load and
 *   maintain the shared calendar. Edits every event regardless of author;
 *   cannot delete events, manage categories, change settings or reactivate
 *   a cancelled event.
 *
 * Roles live in the database, so the definition is applied by activation
 * and re-applied whenever CAPS_VERSION changes (plugin updates do not run
 * the activation hook).
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ROLE_PROGRAMADOR = 'casa_programador';

// Bump whenever programador_capabilities() or administrator_capabilities()
// change: the next request re-applies the role definition.
const CAPS_VERSION        = 1;
const CAPS_VERSION_OPTION = 'casa_eventos_caps_version';

/**
 * Primitive capabilities of the Event post type (register_post_type
 * 'capabilities'). The meta capabilities edit/read/delete_casa_evento are
 * mapped by WordPress (map_meta_cap) onto these.
 *
 * @return array<string,string> Generic post capability => Event capability.
 */
function event_capabilities() {
	return array(
		'edit_posts'             => 'edit_casa_eventos',
		'edit_others_posts'      => 'edit_others_casa_eventos',
		'edit_published_posts'   => 'edit_published_casa_eventos',
		'edit_private_posts'     => 'edit_private_casa_eventos',
		'publish_posts'          => 'publish_casa_eventos',
		'read_private_posts'     => 'read_private_casa_eventos',
		'delete_posts'           => 'delete_casa_eventos',
		'delete_others_posts'    => 'delete_others_casa_eventos',
		'delete_published_posts' => 'delete_published_casa_eventos',
		'delete_private_posts'   => 'delete_private_casa_eventos',
		'create_posts'           => 'edit_casa_eventos',
		'read'                   => 'read',
	);
}

/**
 * Capabilities of the category taxonomy (register_taxonomy 'capabilities').
 *
 * The taxonomy is hierarchical, so creating a term (REST, editor "Añadir
 * categoría", admin screen) requires edit_terms, not assign_terms.
 *
 * @return array<string,string>
 */
function category_capabilities() {
	return array(
		'manage_terms' => 'manage_casa_categorias',
		'edit_terms'   => 'edit_casa_categorias',
		'delete_terms' => 'delete_casa_categorias',
		'assign_terms' => 'assign_casa_categorias',
	);
}

/**
 * Capabilities of the Programador role (the complete role definition).
 *
 * read          wp-admin access (dashboard, own profile).
 * upload_files  poster upload/selection (Media); see map_own_upload_caps().
 * No delete capability: events are cancelled, not deleted. No generic
 * post, page, category, settings, user, plugin or theme capability.
 *
 * @return string[]
 */
function programador_capabilities() {
	$event = event_capabilities();
	return array(
		'read',
		'upload_files',
		$event['edit_posts'],
		$event['edit_others_posts'],
		$event['edit_published_posts'],
		$event['edit_private_posts'],
		$event['publish_posts'],
		$event['read_private_posts'],
		category_capabilities()['assign_terms'],
	);
}

/**
 * Plugin capabilities granted to the administrator role: every Event and
 * category capability.
 *
 * @return string[]
 */
function administrator_capabilities() {
	return plugin_capabilities();
}

/**
 * Every capability this plugin defines (for install and uninstall).
 *
 * @return string[]
 */
function plugin_capabilities() {
	$caps = array_merge( array_values( event_capabilities() ), array_values( category_capabilities() ) );
	return array_values( array_diff( array_unique( $caps ), array( 'read' ) ) );
}

/**
 * Whether a user is an operational Programador (not an administrator who
 * also holds the role). Used for navigation only, never for authorization.
 *
 * @param \WP_User|null $user User; defaults to the current user.
 * @return bool
 */
function is_programador( $user = null ) {
	$user = null === $user ? wp_get_current_user() : $user;
	return $user instanceof \WP_User
		&& in_array( ROLE_PROGRAMADOR, (array) $user->roles, true )
		&& ! user_can( $user, 'manage_options' );
}

/**
 * Create or update the Programador role and grant the administrator the
 * plugin capabilities. Idempotent.
 *
 * The Programador role is plugin-owned: its capabilities are reset to
 * programador_capabilities() (extra capabilities are removed). The
 * administrator only ever gains capabilities here.
 */
function install_roles() {
	$desired = programador_capabilities();
	$role    = get_role( ROLE_PROGRAMADOR );
	if ( ! $role ) {
		add_role( ROLE_PROGRAMADOR, 'Programador', array_fill_keys( $desired, true ) );
	} else {
		foreach ( array_keys( $role->capabilities ) as $cap ) {
			if ( ! in_array( $cap, $desired, true ) ) {
				$role->remove_cap( $cap );
			}
		}
		foreach ( $desired as $cap ) {
			if ( empty( $role->capabilities[ $cap ] ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( administrator_capabilities() as $cap ) {
			if ( empty( $admin->capabilities[ $cap ] ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	update_option( CAPS_VERSION_OPTION, CAPS_VERSION );
}

/**
 * Apply the role definition when it changed (plugin update, first load
 * after activation elsewhere). Runs before the current user is set up.
 */
function maybe_install_roles() {
	if ( CAPS_VERSION !== (int) get_option( CAPS_VERSION_OPTION, 0 ) ) {
		install_roles();
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\maybe_install_roles', 5 );

/**
 * Deactivation: remove the Programador role definition so its users keep
 * no access (not even uploads) while the plugin is inactive. Users keep
 * their role assignment and regain access when the plugin is activated
 * again. Administrator capabilities stay (inert without the plugin).
 */
function deactivate_roles() {
	remove_role( ROLE_PROGRAMADOR );
	delete_option( CAPS_VERSION_OPTION );
}

/**
 * Uninstall: remove the Programador role and every plugin capability from
 * every role. Users are never deleted; events and categories are kept.
 */
function uninstall_roles() {
	remove_role( ROLE_PROGRAMADOR );
	foreach ( wp_roles()->role_objects as $role ) {
		foreach ( plugin_capabilities() as $cap ) {
			if ( isset( $role->capabilities[ $cap ] ) ) {
				$role->remove_cap( $cap );
			}
		}
	}
	delete_option( CAPS_VERSION_OPTION );
}

/**
 * Event staff edit the details (alt text, title, caption) of the images
 * they uploaded themselves.
 *
 * Core maps edit_post on an attachment to the generic edit_posts, which
 * the Programador must not have (it would open Posts). Deleting media and
 * editing other people's media keep core's rules.
 *
 * @param string[] $caps    Required primitive capabilities.
 * @param string   $cap     Requested capability.
 * @param int      $user_id User ID.
 * @param array    $args    Arguments (post ID first).
 * @return string[]
 */
function map_own_upload_caps( $caps, $cap, $user_id, $args ) {
	if ( 'edit_post' !== $cap || empty( $args[0] ) ) {
		return $caps;
	}
	$post = get_post( (int) $args[0] );
	if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || (int) $post->post_author !== (int) $user_id ) {
		return $caps;
	}
	if ( user_can( $user_id, 'upload_files' ) && user_can( $user_id, event_capabilities()['edit_posts'] ) ) {
		return array( 'upload_files' );
	}
	return $caps;
}
add_filter( 'map_meta_cap', __NAMESPACE__ . '\\map_own_upload_caps', 10, 4 );

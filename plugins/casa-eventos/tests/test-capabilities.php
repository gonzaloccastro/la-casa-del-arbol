<?php
/**
 * Authorization model (E1.1): CPT/taxonomy capability registration, the
 * Programador role (install, update, idempotence, deactivation,
 * uninstall), the capability matrices, reactivation, settings and the
 * own-upload mapping.
 *
 * WordPress's own map_meta_cap is not available here; t_can() mirrors its
 * documented edit_post / delete_post / term rules so the matrices read as
 * behavior. The real mapping is verified in the LocalWP runtime QA.
 *
 * @package CasaEventos
 */

use CasaEventos\Core;

// ----- Helpers --------------------------------------------------------------------------

/**
 * Whether a set of primitive caps satisfies a (meta) capability, following
 * core map_meta_cap for posts (map_meta_cap true) and terms.
 *
 * @param string[] $have Primitive capabilities held.
 * @param string   $cap  Capability.
 * @param array    $ctx  { type: cap object array, post: [author, status], user: int }.
 * @return bool
 */
function t_can( array $have, $cap, array $ctx = array() ) {
	$pt   = $ctx['type'] ?? array();
	$user = $ctx['user'] ?? 7;
	list( $author, $status ) = $ctx['post'] ?? array( 0, 'draft' );
	$own  = $author && $author === $user;
	$live = in_array( $status, array( 'publish', 'future' ), true );
	switch ( $cap ) {
		case 'edit_post':
			$need = $own
				? array( $live ? $pt['edit_published_posts'] : $pt['edit_posts'] )
				: array_merge( array( $pt['edit_others_posts'] ), $live ? array( $pt['edit_published_posts'] ) : ( 'private' === $status ? array( $pt['edit_private_posts'] ) : array() ) );
			break;
		case 'delete_post':
			$need = $own
				? array( $live ? $pt['delete_published_posts'] : $pt['delete_posts'] )
				: array_merge( array( $pt['delete_others_posts'] ), $live ? array( $pt['delete_published_posts'] ) : ( 'private' === $status ? array( $pt['delete_private_posts'] ) : array() ) );
			break;
		default:
			$need = array( $pt[ $cap ] ?? $cap );
	}
	return ! array_diff( $need, $have );
}

$event_type = Core\event_capabilities();
$post_type  = array_combine( array_keys( $event_type ), array_keys( $event_type ) );
$post_type['create_posts'] = 'edit_posts';
$page_type  = array_map( static fn( $c ) => str_replace( 'posts', 'pages', $c ), $post_type );
$tax        = Core\category_capabilities();

// A WordPress administrator before the plugin (subset relevant here).
$core_admin = array( 'read', 'upload_files', 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts', 'edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages', 'manage_categories', 'manage_options', 'list_users', 'edit_users', 'activate_plugins', 'switch_themes', 'edit_theme_options' );

// ----- Registration ---------------------------------------------------------------------

t_group( 'capabilities/post type registration' );
Core\register_post_type();
$args = $GLOBALS['t_registered']['post_type']['casa_evento'];
t_eq( array( 'casa_evento', 'casa_eventos' ), $args['capability_type'], 'capability_type is the Event pair, not post' );
t_eq( true, $args['map_meta_cap'], 'map_meta_cap on (edit/read/delete_casa_evento mapped by core)' );
t_eq( $event_type, $args['capabilities'], 'capabilities = event_capabilities()' );
t_eq(
	array(
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
	),
	$event_type,
	'exact Event capability map'
);
foreach ( $event_type as $generic => $own ) {
	if ( 'read' !== $generic ) {
		t_ok( $own !== $generic && 1 === preg_match( '/_casa_eventos$/', $own ), "$generic → $own is an Event-specific primitive" );
	}
}
t_ok( ! in_array( 'author', $args['supports'], true ), 'no author support: shared calendar, no author panel' );

t_group( 'capabilities/taxonomy registration' );
Core\register_taxonomy();
$targs = $GLOBALS['t_registered']['taxonomy']['casa_categoria'];
t_eq( $tax, $targs['capabilities'], 'capabilities = category_capabilities()' );
t_eq(
	array(
		'manage_terms' => 'manage_casa_categorias',
		'edit_terms'   => 'edit_casa_categorias',
		'delete_terms' => 'delete_casa_categorias',
		'assign_terms' => 'assign_casa_categorias',
	),
	$tax,
	'exact category capability map'
);
t_eq( 4, count( array_unique( $tax ) ), 'four distinct capabilities (assign separable from manage/edit/delete)' );
t_ok( ! array_intersect( $tax, array( 'manage_categories', 'edit_posts' ) ), 'no generic core capability (manage_categories, edit_posts)' );
t_eq( true, $targs['hierarchical'], 'hierarchical: creating a term needs edit_terms, never assign_terms (core REST rule)' );
t_eq( 'CasaEventos\\Core\\can_manage_categories', $GLOBALS['t_registered']['term_meta']['_casa_color']['auth_callback'], 'term meta writes need manage_terms' );

// ----- Role install / update / deactivation / uninstall ----------------------------------

t_group( 'capabilities/install (fresh site)' );
$GLOBALS['t_options'] = array( 'timezone_string' => 'America/Argentina/Buenos_Aires' );
t_reset_roles( array( 'administrator' => $core_admin, 'editor' => array( 'read', 'edit_posts', 'edit_pages', 'manage_categories' ) ) );
Core\install_roles();
$role = get_role( 'casa_programador' );
t_ok( $role instanceof WP_Role, 'role casa_programador created' );
t_eq( 'Programador', $GLOBALS['t_role_names']['casa_programador'] ?? null, 'display name Programador' );
$programador = array_keys( array_filter( $role->capabilities ) );
sort( $programador );
$expected = array( 'assign_casa_categorias', 'edit_casa_eventos', 'edit_others_casa_eventos', 'edit_private_casa_eventos', 'edit_published_casa_eventos', 'publish_casa_eventos', 'read', 'read_private_casa_eventos', 'upload_files' );
t_eq( $expected, $programador, 'exact Programador capability list' );
$admin_caps = array_keys( array_filter( get_role( 'administrator' )->capabilities ) );
t_eq( array(), array_values( array_diff( Core\plugin_capabilities(), $admin_caps ) ), 'administrator has every Event and category capability' );
t_eq( array(), array_values( array_diff( $core_admin, $admin_caps ) ), 'administrator keeps every core capability' );
t_eq( 14, count( Core\plugin_capabilities() ), '14 plugin capabilities (10 Event + 4 category)' );
t_eq( array( 'read', 'edit_posts', 'edit_pages', 'manage_categories' ), array_keys( get_role( 'editor' )->capabilities ), 'editor role untouched (no Event capabilities)' );
t_eq( Core\CAPS_VERSION, get_option( Core\CAPS_VERSION_OPTION ), 'capability version recorded' );

t_group( 'capabilities/install is idempotent' );
$snapshot                 = serialize( $GLOBALS['t_roles'] );
$GLOBALS['t_role_writes'] = array();
Core\install_roles();
t_eq( array(), $GLOBALS['t_role_writes'], 'second install writes nothing' );
t_eq( $snapshot, serialize( $GLOBALS['t_roles'] ), 'roles unchanged' );
Core\maybe_install_roles();
t_eq( array(), $GLOBALS['t_role_writes'], 'maybe_install_roles with the current version: no writes' );

t_group( 'capabilities/update (definition changed or role edited)' );
$role = get_role( 'casa_programador' );
$role->add_cap( 'edit_posts' );          // Drift: a generic cap added by hand or an old version.
$role->add_cap( 'manage_casa_categorias' );
$role->remove_cap( 'publish_casa_eventos' ); // A missing cap.
get_role( 'administrator' )->remove_cap( 'edit_casa_categorias' );
update_option( Core\CAPS_VERSION_OPTION, Core\CAPS_VERSION - 1 );
Core\maybe_install_roles();
$programador = array_keys( array_filter( get_role( 'casa_programador' )->capabilities ) );
sort( $programador );
t_eq( $expected, $programador, 'older version → role reset to the exact definition' );
t_ok( ! empty( get_role( 'administrator' )->capabilities['edit_casa_categorias'] ), 'missing administrator capability restored' );
t_ok( ! empty( get_role( 'administrator' )->capabilities['manage_options'] ), 'administrator core capabilities kept' );
t_eq( Core\CAPS_VERSION, get_option( Core\CAPS_VERSION_OPTION ), 'version updated' );
unset( $GLOBALS['t_options'][ Core\CAPS_VERSION_OPTION ] );
$GLOBALS['t_role_writes'] = array();
Core\maybe_install_roles();
t_eq( array(), $GLOBALS['t_role_writes'], 'no stored version but roles already correct → nothing to write' );
t_eq( Core\CAPS_VERSION, get_option( Core\CAPS_VERSION_OPTION ), 'version stored' );

t_group( 'capabilities/deactivation' );
Core\deactivate_roles();
t_eq( null, get_role( 'casa_programador' ), 'Programador role definition removed' );
t_ok( ! array_key_exists( Core\CAPS_VERSION_OPTION, $GLOBALS['t_options'] ), 'version option removed (reactivation re-installs)' );
t_ok( ! empty( get_role( 'administrator' )->capabilities['edit_casa_eventos'] ), 'administrator capabilities kept' );
Core\maybe_install_roles();
t_ok( get_role( 'casa_programador' ) instanceof WP_Role, 'next load after reactivation restores the role' );

t_group( 'capabilities/uninstall' );
get_role( 'editor' )->add_cap( 'edit_casa_eventos' ); // Granted by hand to another role.
Core\uninstall_roles();
t_eq( null, get_role( 'casa_programador' ), 'role removed' );
foreach ( $GLOBALS['t_roles'] as $slug => $r ) {
	t_eq( array(), array_values( array_intersect( Core\plugin_capabilities(), array_keys( $r->capabilities ) ) ), "$slug: no plugin capability left" );
}
t_eq( array(), array_values( array_diff( $core_admin, array_keys( get_role( 'administrator' )->capabilities ) ) ), 'administrator core capabilities untouched' );
t_ok( ! array_key_exists( Core\CAPS_VERSION_OPTION, $GLOBALS['t_options'] ), 'version option removed' );
$src = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
t_ok( false !== strpos( $src, 'uninstall_roles()' ) && ! preg_match( '/wp_delete_post|wp_delete_term|delete_post_meta|wp_delete_user|DELETE FROM/i', $src ), 'uninstall.php removes authorization only, never content or users' );

// ----- Capability matrices ------------------------------------------------------------------

t_reset_roles( array( 'administrator' => $core_admin ) );
Core\install_roles();
$P = array_keys( array_filter( get_role( 'casa_programador' )->capabilities ) );
$A = array_keys( array_filter( get_role( 'administrator' )->capabilities ) );
$ev = array( 'type' => $event_type );

t_group( 'matrix/Programador — Events' );
t_ok( t_can( $P, 'read' ), 'wp-admin access (read)' );
t_ok( t_can( $P, 'edit_posts', $ev ), 'see Eventos / list events' );
t_ok( t_can( $P, 'create_posts', $ev ), 'create events (Añadir evento)' );
t_ok( t_can( $P, 'edit_post', $ev + array( 'post' => array( 7, 'draft' ) ) ), 'edit own draft' );
t_ok( t_can( $P, 'edit_post', $ev + array( 'post' => array( 1, 'draft' ) ) ), 'edit a draft by another user' );
t_ok( t_can( $P, 'edit_post', $ev + array( 'post' => array( 1, 'publish' ) ) ), 'edit a published event by another user' );
t_ok( t_can( $P, 'edit_post', $ev + array( 'post' => array( 1, 'future' ) ) ), 'edit a scheduled event by another user' );
t_ok( t_can( $P, 'edit_post', $ev + array( 'post' => array( 1, 'private' ) ) ), 'edit a private event by another user' );
t_ok( t_can( $P, 'publish_posts', $ev ), 'publish and schedule (publish_casa_eventos)' );
t_ok( t_can( $P, 'read_private_posts', $ev ), 'read private events' );
t_ok( ! t_can( $P, 'delete_post', $ev + array( 'post' => array( 7, 'draft' ) ) ), 'cannot delete even an own draft' );
t_ok( ! t_can( $P, 'delete_post', $ev + array( 'post' => array( 1, 'publish' ) ) ), 'cannot delete a published event' );

t_group( 'matrix/Programador — categories' );
t_ok( t_can( $P, 'assign_terms', array( 'type' => $tax ) ), 'assign an existing category' );
foreach ( array( 'manage_terms', 'edit_terms', 'delete_terms' ) as $c ) {
	t_ok( ! t_can( $P, $c, array( 'type' => $tax ) ), "no $c ({$tax[ $c ]}): cannot create/edit/delete or open Categorías" );
}
$GLOBALS['t_caps'] = array_fill_keys( $P, true );
t_eq( false, Core\can_manage_categories(), 'category color/order term meta refused' );
$GLOBALS['t_caps'] = array_fill_keys( $A, true );
t_eq( true, Core\can_manage_categories(), 'administrator manages category term meta' );

t_group( 'matrix/Programador — everything else is refused' );
foreach ( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_posts' ) as $c ) {
	t_ok( ! t_can( $P, $c, array( 'type' => $post_type ) ), "Posts: no $c" );
}
t_ok( ! t_can( $P, 'edit_post', array( 'type' => $post_type, 'post' => array( 7, 'draft' ) ) ), 'Posts: cannot edit even an own post' );
foreach ( array( 'edit_pages', 'publish_pages' ) as $c ) {
	t_ok( ! t_can( $P, $c, array( 'type' => $page_type ) ), "Pages: no $c" );
}
t_ok( ! t_can( $P, 'edit_post', array( 'type' => $page_type, 'post' => array( 1, 'publish' ) ) ), 'Pages: cannot edit a page' );
$forbidden = array( 'manage_options', 'manage_categories', 'list_users', 'edit_users', 'create_users', 'promote_users', 'activate_plugins', 'install_plugins', 'edit_plugins', 'switch_themes', 'edit_theme_options', 'customize', 'moderate_comments', 'import', 'export', 'unfiltered_html', 'manage_woocommerce', 'edit_products', 'view_woocommerce_reports', 'delete_casa_eventos', 'delete_others_casa_eventos', 'delete_published_casa_eventos', 'delete_private_casa_eventos', 'manage_casa_categorias', 'edit_casa_categorias', 'delete_casa_categorias' );
t_eq( array(), array_values( array_intersect( $forbidden, $P ) ), 'none of the forbidden capabilities (settings, users, plugins, themes, Woo, deletes, category admin)' );
t_eq( array(), array_values( array_diff( $P, array_merge( array( 'read', 'upload_files' ), array_values( $event_type ), array_values( $tax ) ) ) ), 'nothing beyond read, upload_files and plugin capabilities' );

t_group( 'matrix/Administrator' );
foreach ( array( 'edit_posts', 'create_posts', 'publish_posts', 'read_private_posts' ) as $c ) {
	t_ok( t_can( $A, $c, $ev ), "Events: $c" );
}
foreach ( array( array( 1, 'draft' ), array( 5, 'publish' ), array( 5, 'future' ), array( 5, 'private' ) ) as $p ) {
	t_ok( t_can( $A, 'edit_post', $ev + array( 'post' => $p, 'user' => 1 ) ), "Events: edit ({$p[1]}, author {$p[0]})" );
	t_ok( t_can( $A, 'delete_post', $ev + array( 'post' => $p, 'user' => 1 ) ), "Events: delete ({$p[1]}, author {$p[0]})" );
}
foreach ( $tax as $generic => $c ) {
	t_ok( t_can( $A, $generic, array( 'type' => $tax ) ), "categories: $generic" );
}

// ----- Settings and reactivation ---------------------------------------------------------------

t_group( 'capabilities/settings and reactivation stay administrator-only' );
t_eq( 'manage_options', Core\reactivate_capability(), 'reactivation capability: manage_options' );
t_ok( ! in_array( 'manage_options', $P, true ) && in_array( 'manage_options', $A, true ), 'Programador lacks it, administrator has it' );
$settings_src = file_get_contents( dirname( __DIR__ ) . '/inc/admin/settings-page.php' );
t_ok( 2 === substr_count( $settings_src, "'manage_options'" ), 'Eventos → Ajustes: menu and render both require manage_options' );

t_reset_store();
$GLOBALS['t_caps'] = array_fill_keys( $P, true ) + array( 'edit_post' => true );
$base              = array(
	'meta'           => array(
		'_casa_start'       => '2026-11-20 21:00:00',
		'_casa_access_mode' => 'whatsapp',
	),
	'casa_categoria' => array( 4 ),
);
$ev_id = t_add_post( array( 'post_status' => 'publish', 'post_title' => 'Ensayo abierto', 'post_author' => 1 ) );
foreach ( $base['meta'] as $k => $v ) {
	update_post_meta( $ev_id, $k, $v );
}
t_set_terms( $ev_id, array( 4 ) );
Core\sync_event( $ev_id );
$save = static function ( $status ) use ( $ev_id ) {
	return Core\validate_rest_insert( (object) array( 'ID' => $ev_id, 'post_status' => 'publish' ), new WP_REST_Request( array( 'meta' => array( '_casa_status' => $status ) ) ) );
};
t_ok( ! is_wp_error( $save( 'paused' ) ), 'Programador pauses an event by another user' );
update_post_meta( $ev_id, '_casa_status', 'paused' );
t_ok( ! is_wp_error( $save( 'active' ) ), 'Programador resumes a paused event' );
t_ok( ! is_wp_error( $save( 'cancelled' ) ), 'Programador cancels' );
update_post_meta( $ev_id, '_casa_status', 'cancelled' );
foreach ( array( 'active', 'paused' ) as $to ) {
	$r = $save( $to );
	t_ok( is_wp_error( $r ) && isset( $r->get_error_data()['errors']['reactivate_forbidden'] ), "Programador cannot reactivate a cancelled event (→ $to)" );
}
$GLOBALS['t_caps'] = array_fill_keys( $A, true ) + array( 'edit_post' => true );
t_ok( ! is_wp_error( $save( 'active' ) ), 'administrator can reactivate' );
$r = Core\validate_rest_insert( (object) array( 'ID' => $ev_id, 'post_status' => 'future' ), new WP_REST_Request( array( 'meta' => array( '_casa_status' => 'cancelled' ) ) ) );
t_ok( ! is_wp_error( $r ), 'scheduling a complete event validates' );

// ----- Own uploads --------------------------------------------------------------------------

t_group( 'capabilities/own uploads (poster alt text)' );
$mine   = t_add_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_author' => 7 ) );
$theirs = t_add_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_author' => 1 ) );
$page   = t_add_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => 7 ) );
$GLOBALS['t_caps'] = array_fill_keys( $P, true );
t_eq( array( 'upload_files' ), Core\map_own_upload_caps( array( 'edit_posts' ), 'edit_post', 7, array( $mine ) ), 'edit own upload → upload_files' );
t_eq( array( 'edit_others_posts' ), Core\map_own_upload_caps( array( 'edit_others_posts' ), 'edit_post', 7, array( $theirs ) ), 'someone else\'s upload: core rule kept' );
t_eq( array( 'delete_posts' ), Core\map_own_upload_caps( array( 'delete_posts' ), 'delete_post', 7, array( $mine ) ), 'deleting media: core rule kept' );
t_eq( array( 'edit_published_pages' ), Core\map_own_upload_caps( array( 'edit_published_pages' ), 'edit_post', 7, array( $page ) ), 'not an attachment: core rule kept' );
$GLOBALS['t_caps'] = array( 'read' => true, 'upload_files' => true );
t_eq( array( 'edit_posts' ), Core\map_own_upload_caps( array( 'edit_posts' ), 'edit_post', 7, array( $mine ) ), 'without Event capabilities: core rule kept' );

$GLOBALS['t_caps'] = array( 'manage_options' => false, 'edit_post' => true );
t_reset_store();
t_reset_roles();

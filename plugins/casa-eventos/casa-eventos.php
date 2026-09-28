<?php
/**
 * Plugin Name:       Casa Eventos
 * Description:       Eventos de La Casa del Árbol: entidad Evento, categorías, estados, fechas y consultas (Event Core).
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            La Casa del Árbol
 * Text Domain:       casa-eventos
 * License:           GPL-2.0-or-later
 *
 * Event Core only: no WooCommerce dependency, no frontend output.
 * Contract: docs/implementation/casa-eventos-contract.md (repository).
 *
 * @package CasaEventos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CASA_EVENTOS_VERSION', '0.1.0' );
define( 'CASA_EVENTOS_FILE', __FILE__ );
define( 'CASA_EVENTOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CASA_EVENTOS_URL', plugin_dir_url( __FILE__ ) );

// Event Core: schema, rules, read model and queries.
require_once CASA_EVENTOS_DIR . 'inc/core/schema.php';
require_once CASA_EVENTOS_DIR . 'inc/core/datetime.php';
require_once CASA_EVENTOS_DIR . 'inc/core/state.php';
require_once CASA_EVENTOS_DIR . 'inc/core/validation.php';
require_once CASA_EVENTOS_DIR . 'inc/core/settings.php';
require_once CASA_EVENTOS_DIR . 'inc/core/capabilities.php';
require_once CASA_EVENTOS_DIR . 'inc/core/post-type.php';
require_once CASA_EVENTOS_DIR . 'inc/core/taxonomy.php';
require_once CASA_EVENTOS_DIR . 'inc/core/meta.php';
require_once CASA_EVENTOS_DIR . 'inc/core/class-event.php';
require_once CASA_EVENTOS_DIR . 'inc/core/sync.php';
require_once CASA_EVENTOS_DIR . 'inc/core/uuid.php';
require_once CASA_EVENTOS_DIR . 'inc/core/enforcement.php';
require_once CASA_EVENTOS_DIR . 'inc/core/rest.php';
require_once CASA_EVENTOS_DIR . 'inc/core/queries.php';

// Programador navigation (menu, admin bar): every request, since the admin
// bar is also shown on the public site. Presentation only.
require_once CASA_EVENTOS_DIR . 'inc/admin/navigation.php';

// Admin UI: consumes the core API only.
if ( is_admin() ) {
	require_once CASA_EVENTOS_DIR . 'inc/admin/editor.php';
	require_once CASA_EVENTOS_DIR . 'inc/admin/list-table.php';
	require_once CASA_EVENTOS_DIR . 'inc/admin/categories.php';
	require_once CASA_EVENTOS_DIR . 'inc/admin/settings-page.php';
	require_once CASA_EVENTOS_DIR . 'inc/admin/notices.php';
}

/**
 * Activation: create/update the Programador role and the administrator's
 * Event capabilities, register the post type, then rebuild rewrite rules
 * so /evento/{slug}/ resolves. No content is created (no categories, no
 * pages, no users).
 */
function casa_eventos_activate() {
	CasaEventos\Core\install_roles();
	CasaEventos\Core\register_post_type();
	CasaEventos\Core\register_taxonomy();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'casa_eventos_activate' );

/**
 * Deactivation: remove the Programador role definition and drop the
 * /evento/ rewrite rules. Events, categories, settings and users are kept.
 */
function casa_eventos_deactivate() {
	CasaEventos\Core\deactivate_roles();
	unregister_post_type( CasaEventos\Core\POST_TYPE );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'casa_eventos_deactivate' );

/**
 * Fires once every module is loaded. Later modules hook here.
 */
add_action(
	'plugins_loaded',
	static function () {
		do_action( 'casa_eventos/loaded' );
	}
);

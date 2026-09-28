<?php
/**
 * Uninstall: preserves all Event data; removes only the authorization it
 * added.
 *
 * Events, their meta, the categories and the venue settings are content of
 * La Casa del Árbol, not plugin cache. Deleting the plugin removes its code,
 * the Programador role definition and the plugin capabilities granted to
 * roles. Users are never deleted. Removing data, if ever needed, is an
 * explicit manual operation.
 *
 * @package CasaEventos
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ABSPATH is defined here: WordPress runs uninstall.php from wp-admin.
require_once __DIR__ . '/inc/core/capabilities.php';

// No post, meta, term or option with content is deleted on purpose
// (docs/implementation/casa-eventos-contract.md §11).
CasaEventos\Core\uninstall_roles();

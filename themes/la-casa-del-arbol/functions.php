<?php
/**
 * La Casa del Árbol child theme bootstrap.
 *
 * Presentation only: no event, ticketing or commerce logic lives in this
 * theme (see CLAUDE.md). Each concern lives in its own file under inc/.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LCDA_DIR', get_stylesheet_directory() );
define( 'LCDA_URI', get_stylesheet_directory_uri() );

require_once LCDA_DIR . '/inc/setup.php';
require_once LCDA_DIR . '/inc/assets.php';
require_once LCDA_DIR . '/inc/astra.php';
require_once LCDA_DIR . '/inc/patterns.php';

<?php
/**
 * Taxonomy casa_categoria: a flat controlled vocabulary of general, stable
 * public Agenda filters (Música, Cine, …; never artists, event names,
 * producers, cycles or ticket types), with exactly one term per published
 * event. Hierarchical only for the checkbox UI in the
 * block editor (free-text tag entry would create stray terms).
 *
 * The category owns the tag color (mint | yellow) and the chip order, as
 * term meta. No terms are created by code: categories are editorial.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the category taxonomy and its term meta.
 */
function register_taxonomy() {
	\register_taxonomy(
		TAXONOMY,
		array( POST_TYPE ),
		array(
			'labels'             => array(
				'name'          => __( 'Categorías', 'casa-eventos' ),
				'singular_name' => __( 'Categoría', 'casa-eventos' ),
				'menu_name'     => __( 'Categorías', 'casa-eventos' ),
				'all_items'     => __( 'Todas las categorías', 'casa-eventos' ),
				'edit_item'     => __( 'Editar categoría', 'casa-eventos' ),
				'view_item'     => __( 'Ver categoría', 'casa-eventos' ),
				'update_item'   => __( 'Actualizar categoría', 'casa-eventos' ),
				'add_new_item'  => __( 'Añadir categoría', 'casa-eventos' ),
				'new_item_name' => __( 'Nombre de la categoría', 'casa-eventos' ),
				'search_items'  => __( 'Buscar categorías', 'casa-eventos' ),
				'not_found'     => __( 'No hay categorías.', 'casa-eventos' ),
				'back_to_items' => __( '← Volver a las categorías', 'casa-eventos' ),
			),
			'description'        => __( 'Una sola categoría por evento. Define el color de la etiqueta.', 'casa-eventos' ),
			'hierarchical'       => true,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'rewrite'            => false,
			// Administrators manage categories; the Programador only
			// assigns existing ones (capabilities.php).
			'capabilities'       => category_capabilities(),
		)
	);

	foreach ( term_meta_definitions() as $key => $def ) {
		register_term_meta(
			TAXONOMY,
			$key,
			array(
				'type'              => $def['type'],
				'single'            => true,
				'default'           => $def['default'],
				'sanitize_callback' => $def['sanitize'],
				'auth_callback'     => __NAMESPACE__ . '\\can_manage_categories',
				'show_in_rest'      => array(
					'schema' => array_merge( array( 'type' => $def['type'] ), $def['schema'] ),
				),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_taxonomy', 5 );

/**
 * Category term meta definitions.
 *
 * Sanitize callbacks must be plugin functions: WordPress calls them with
 * four arguments, which PHP internal functions such as intval() reject
 * (ArgumentCountError on PHP 8).
 *
 * @return array<string,array>
 */
function term_meta_definitions() {
	return array(
		TERM_COLOR => array(
			'type'     => 'string',
			'default'  => COLOR_MINT,
			'sanitize' => __NAMESPACE__ . '\\sanitize_color',
			'schema'   => array( 'enum' => colors() ),
		),
		TERM_ORDER => array(
			'type'     => 'integer',
			'default'  => 0,
			'sanitize' => __NAMESPACE__ . '\\sanitize_order',
			'schema'   => array(),
		),
	);
}

/**
 * Sanitize a chip order.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function sanitize_order( $value ) {
	return is_numeric( $value ) ? (int) $value : 0;
}

/**
 * Auth callback for category term meta.
 *
 * @return bool
 */
function can_manage_categories() {
	$taxonomy = get_taxonomy( TAXONOMY );
	return $taxonomy && current_user_can( $taxonomy->cap->manage_terms );
}

/**
 * Sanitize a category color.
 *
 * @param mixed $value Raw value.
 * @return string mint | yellow (unknown values become mint).
 */
function sanitize_color( $value ) {
	return in_array( $value, colors(), true ) ? $value : COLOR_MINT;
}

/**
 * Color of a category.
 *
 * @param int|WP_Term $term Term or term ID.
 * @return string mint | yellow.
 */
function category_color( $term ) {
	$term_id = $term instanceof WP_Term ? $term->term_id : (int) $term;
	return sanitize_color( get_term_meta( $term_id, TERM_COLOR, true ) );
}

/**
 * Chip order of a category.
 *
 * @param int|WP_Term $term Term or term ID.
 * @return int
 */
function category_order( $term ) {
	$term_id = $term instanceof WP_Term ? $term->term_id : (int) $term;
	return (int) get_term_meta( $term_id, TERM_ORDER, true );
}

/**
 * Save a category's color and order.
 *
 * @param int    $term_id Term ID.
 * @param string $color   mint | yellow.
 * @param int    $order   Chip order.
 */
function save_category_fields( $term_id, $color, $order ) {
	update_term_meta( $term_id, TERM_COLOR, sanitize_color( $color ) );
	update_term_meta( $term_id, TERM_ORDER, (int) $order );
}

/**
 * Store explicit color/order on categories created without the category
 * screen (e.g. "Añadir categoría" inside the block editor): mint, order 0.
 * The category screen saves first (priority 10), so its values win.
 *
 * @param int $term_id Term ID.
 */
function materialize_category_meta( $term_id ) {
	if ( ! metadata_exists( 'term', $term_id, TERM_COLOR ) ) {
		update_term_meta( $term_id, TERM_COLOR, COLOR_MINT );
	}
	if ( ! metadata_exists( 'term', $term_id, TERM_ORDER ) ) {
		update_term_meta( $term_id, TERM_ORDER, 0 );
	}
}
add_action( 'created_' . TAXONOMY, __NAMESPACE__ . '\\materialize_category_meta', 20 );

/**
 * All categories, in chip order (order meta, then name).
 *
 * @param bool $hide_empty Only categories with events.
 * @return WP_Term[]
 */
function categories( $hide_empty = false ) {
	$terms = get_terms(
		array(
			'taxonomy'   => TAXONOMY,
			'hide_empty' => $hide_empty,
		)
	);
	if ( ! is_array( $terms ) ) {
		return array();
	}
	usort( $terms, __NAMESPACE__ . '\\compare_categories' );
	return $terms;
}

/**
 * Chip order comparison: order meta, then name, then ID.
 *
 * @param WP_Term $a Term.
 * @param WP_Term $b Term.
 * @return int
 */
function compare_categories( $a, $b ) {
	$by_order = category_order( $a ) <=> category_order( $b );
	if ( 0 !== $by_order ) {
		return $by_order;
	}
	$by_name = strcoll( $a->name, $b->name );
	return 0 !== $by_name ? $by_name : $a->term_id <=> $b->term_id;
}

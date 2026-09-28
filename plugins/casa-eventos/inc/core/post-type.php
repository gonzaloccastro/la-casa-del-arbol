<?php
/**
 * Post type casa_evento: one post = one dated occurrence.
 *
 * Public single URL /evento/{slug}/. No archive: the Agenda page is the
 * listing. Frontend presentation is not part of Event Core.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Event post type.
 */
function register_post_type() {
	\register_post_type(
		POST_TYPE,
		array(
			'labels'              => array(
				'name'                  => __( 'Eventos', 'casa-eventos' ),
				'singular_name'         => __( 'Evento', 'casa-eventos' ),
				'menu_name'             => __( 'Eventos', 'casa-eventos' ),
				'all_items'             => __( 'Todos los eventos', 'casa-eventos' ),
				'add_new'               => __( 'Añadir evento', 'casa-eventos' ),
				'add_new_item'          => __( 'Añadir evento', 'casa-eventos' ),
				'edit_item'             => __( 'Editar evento', 'casa-eventos' ),
				'new_item'              => __( 'Nuevo evento', 'casa-eventos' ),
				'view_item'             => __( 'Ver evento', 'casa-eventos' ),
				'view_items'            => __( 'Ver eventos', 'casa-eventos' ),
				'search_items'          => __( 'Buscar eventos', 'casa-eventos' ),
				'not_found'             => __( 'No hay eventos.', 'casa-eventos' ),
				'not_found_in_trash'    => __( 'No hay eventos en la papelera.', 'casa-eventos' ),
				'featured_image'        => __( 'Afiche', 'casa-eventos' ),
				'set_featured_image'    => __( 'Elegir afiche', 'casa-eventos' ),
				'remove_featured_image' => __( 'Quitar afiche', 'casa-eventos' ),
				'use_featured_image'    => __( 'Usar como afiche', 'casa-eventos' ),
				'item_published'        => __( 'Evento publicado.', 'casa-eventos' ),
				'item_scheduled'        => __( 'Evento programado.', 'casa-eventos' ),
				'item_updated'          => __( 'Evento actualizado.', 'casa-eventos' ),
				'item_reverted_to_draft' => __( 'Evento pasado a borrador.', 'casa-eventos' ),
			),
			'public'              => true,
			'show_in_rest'        => true,
			'has_archive'         => false,
			'rewrite'             => array(
				'slug'       => 'evento',
				'with_front' => false,
				'feeds'      => false,
				'pages'      => false,
			),
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-calendar-alt',
			// Own capabilities (capabilities.php): generic post permissions
			// never grant access to events, and vice versa.
			'capability_type'     => array( 'casa_evento', 'casa_eventos' ),
			'capabilities'        => event_capabilities(),
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
			'delete_with_user'    => false,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_post_type', 5 );

<?php
/**
 * Events list (Eventos → Todos los eventos): columns, views, sorting and
 * filters. Reads events only through the read model and the query API.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Admin;

use CasaEventos\Core;
use CasaEventos\Core\Event;
use CasaEventos\Core\Queries;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VIEW_VAR  = 'casa_vista';
const MONTH_VAR = 'casa_mes';

/**
 * Whether the current request is the Events list screen.
 *
 * @return bool
 */
function is_event_list_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && 'edit-' . Core\POST_TYPE === $screen->id;
}

/**
 * Current value of a list query var.
 *
 * @param string $name    Query var.
 * @param array  $allowed Allowed values; empty = any 'YYYY-MM'.
 * @return string
 */
function request_value( $name, array $allowed = array() ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
	$value = isset( $_GET[ $name ] ) ? sanitize_key( wp_unslash( $_GET[ $name ] ) ) : '';
	if ( $allowed ) {
		return in_array( $value, $allowed, true ) ? $value : '';
	}
	return (string) Core\parse_month( $value );
}

/**
 * Columns.
 *
 * @param array $columns Default columns.
 * @return array
 */
function columns( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$out['casa_poster'] = __( 'Afiche', 'casa-eventos' );
			$out[ $key ]        = $label;
			$out['casa_start']  = __( 'Fecha', 'casa-eventos' );
			continue;
		}
		if ( 'date' === $key ) {
			// The publication date is not the event date; the state column
			// shows when a scheduled event goes public.
			continue;
		}
		$out[ $key ] = $label;
	}
	$out['casa_state']  = __( 'Estado', 'casa-eventos' );
	$out['casa_access'] = __( 'Modalidad', 'casa-eventos' );
	$out['casa_flags']  = __( 'Visibilidad', 'casa-eventos' );
	return $out;
}
add_filter( 'manage_' . Core\POST_TYPE . '_posts_columns', __NAMESPACE__ . '\\columns' );

/**
 * Column output.
 *
 * @param string $column  Column key.
 * @param int    $post_id Event ID.
 */
function column_content( $column, $post_id ) {
	$event = Event::get( $post_id );
	if ( ! $event ) {
		return;
	}

	switch ( $column ) {
		case 'casa_poster':
			$poster = $event->poster_id();
			echo $poster ? wp_get_attachment_image( $poster, array( 48, 60 ), false, array( 'class' => 'casa-eventos-thumb' ) ) : '<span class="casa-eventos-thumb casa-eventos-thumb--empty" aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated markup.
			break;

		case 'casa_start':
			$start = $event->start();
			if ( ! $start ) {
				echo '<span class="casa-eventos-muted">' . esc_html__( 'Sin fecha', 'casa-eventos' ) . '</span>';
				break;
			}
			$tz = Core\timezone_object( $event->timezone() );
			echo esc_html( wp_date( 'D j/m/Y · H:i', $start->getTimestamp(), $tz ) );
			if ( $event->has_declared_end() ) {
				$end = $event->end();
				echo '<br><span class="casa-eventos-muted">' . esc_html(
					sprintf(
						/* translators: %s: end date/time. */
						__( 'hasta %s', 'casa-eventos' ),
						wp_date( 'D j/m · H:i', $end->getTimestamp(), $tz )
					)
				) . '</span>';
			}
			break;

		case 'casa_state':
			$state = $event->effective_state();
			printf(
				'<span class="casa-eventos-badge casa-eventos-badge--%1$s">%2$s</span>',
				esc_attr( $state ),
				esc_html( Core\state_label( $state ) )
			);
			if ( Core\STATE_SCHEDULED === $state ) {
				echo '<br><span class="casa-eventos-muted">' . esc_html(
					sprintf(
						/* translators: %s: publication date. */
						__( 'se publica %s', 'casa-eventos' ),
						get_the_date( 'j/m/Y H:i', $event->post() )
					)
				) . '</span>';
			}
			if ( in_array( $event->post_status(), array( 'publish', 'future' ), true ) ) {
				$errors = $event->validation()['errors'];
				if ( $errors ) {
					printf(
						'<br><span class="casa-eventos-warning" title="%1$s">%2$s</span>',
						esc_attr( implode( ' ', $errors ) ),
						esc_html__( '⚠ Datos incompletos', 'casa-eventos' )
					);
				}
			}
			break;

		case 'casa_access':
			$labels = Core\access_mode_labels();
			$mode   = $event->access_mode();
			echo '' !== $mode ? esc_html( $labels[ $mode ] ) : '<span class="casa-eventos-muted">—</span>';
			$label = $event->entry_label();
			if ( '' === $label && '' !== $event->entry_kind() ) {
				$label = Core\entry_kind_labels()[ $event->entry_kind() ];
			}
			if ( '' !== $label ) {
				echo '<br><span class="casa-eventos-muted">' . esc_html( $label ) . '</span>';
			}
			break;

		case 'casa_flags':
			echo $event->is_listed()
				? esc_html__( 'En Agenda', 'casa-eventos' )
				: '<span class="casa-eventos-muted">' . esc_html__( 'Oculto', 'casa-eventos' ) . '</span>';
			if ( $event->is_featured() ) {
				echo '<br><span class="casa-eventos-featured">' . esc_html__( '★ Destacado', 'casa-eventos' ) . '</span>';
			}
			break;
	}
}
add_action( 'manage_' . Core\POST_TYPE . '_posts_custom_column', __NAMESPACE__ . '\\column_content', 10, 2 );

/**
 * Sortable columns.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function sortable_columns( $columns ) {
	$columns['casa_start'] = array( 'start', false, __( 'Fecha', 'casa-eventos' ), __( 'Ordenado por fecha del evento.', 'casa-eventos' ) );
	return $columns;
}
add_filter( 'manage_edit-' . Core\POST_TYPE . '_sortable_columns', __NAMESPACE__ . '\\sortable_columns' );

/**
 * Views (Próximos · Pasados · Pausados · Cancelados) after the native ones.
 *
 * @param array $views Native views.
 * @return array
 */
function views( $views ) {
	$current = request_value( VIEW_VAR, array_keys( Queries::admin_views() ) );
	$base    = admin_url( 'edit.php?post_type=' . Core\POST_TYPE );

	if ( '' !== $current ) {
		// "Todos" must not look active while a custom view is selected.
		foreach ( $views as $key => $html ) {
			$views[ $key ] = str_replace( array( ' class="current"', ' aria-current="page"' ), '', $html );
		}
	}

	foreach ( Queries::admin_views() as $key => $label ) {
		$is_current    = $current === $key;
		$views[ 'casa_' . $key ] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
			esc_url( add_query_arg( VIEW_VAR, $key, $base ) ),
			$is_current ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			Queries::admin_view_count( $key )
		);
	}
	return $views;
}
add_filter( 'views_edit-' . Core\POST_TYPE, __NAMESPACE__ . '\\views' );

/**
 * Apply view, month filter and ordering to the list query.
 *
 * @param WP_Query $query Query.
 */
function filter_list_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || Core\POST_TYPE !== $query->get( 'post_type' ) ) {
		return;
	}
	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}

	$orderby = (string) $query->get( 'orderby' );
	$vars    = Queries::admin_list_query_vars(
		array(
			'view'    => request_value( VIEW_VAR, array_keys( Queries::admin_views() ) ),
			'month'   => request_value( MONTH_VAR ),
			'orderby' => '' === $orderby ? '' : ( 'start' === $orderby ? 'start' : 'other' ),
			'order'   => (string) $query->get( 'order' ),
		)
	);
	foreach ( $vars as $key => $value ) {
		$query->set( $key, $value );
	}
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\filter_list_query' );

/**
 * Replace the post-date month dropdown with an event-month dropdown.
 *
 * @param bool   $disable   Whether to disable.
 * @param string $post_type Post type.
 * @return bool
 */
function disable_months_dropdown( $disable, $post_type ) {
	return Core\POST_TYPE === $post_type ? true : $disable;
}
add_filter( 'disable_months_dropdown', __NAMESPACE__ . '\\disable_months_dropdown', 10, 2 );

/**
 * Month (by event start) and category filters.
 *
 * @param string $post_type Post type.
 */
function filters( $post_type ) {
	if ( Core\POST_TYPE !== $post_type ) {
		return;
	}

	$current = request_value( MONTH_VAR );
	echo '<label for="casa-eventos-mes" class="screen-reader-text">' . esc_html__( 'Filtrar por mes del evento', 'casa-eventos' ) . '</label>';
	echo '<select name="' . esc_attr( MONTH_VAR ) . '" id="casa-eventos-mes">';
	echo '<option value="">' . esc_html__( 'Todos los meses', 'casa-eventos' ) . '</option>';
	foreach ( Queries::event_months() as $ym ) {
		$label = wp_date( 'F Y', ( new \DateTimeImmutable( $ym . '-15 12:00:00', new \DateTimeZone( 'UTC' ) ) )->getTimestamp(), new \DateTimeZone( 'UTC' ) );
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $ym ), selected( $current, $ym, false ), esc_html( $label ) );
	}
	echo '</select>';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
	$category = isset( $_GET[ Core\TAXONOMY ] ) ? sanitize_title( wp_unslash( $_GET[ Core\TAXONOMY ] ) ) : '';
	wp_dropdown_categories(
		array(
			'taxonomy'        => Core\TAXONOMY,
			'name'            => Core\TAXONOMY,
			'value_field'     => 'slug',
			'selected'        => $category,
			'show_option_all' => __( 'Todas las categorías', 'casa-eventos' ),
			'hide_empty'      => false,
			'hierarchical'    => false,
			'orderby'         => 'name',
		)
	);

	// Keep the selected view when filtering.
	$view = request_value( VIEW_VAR, array_keys( Queries::admin_views() ) );
	if ( '' !== $view ) {
		echo '<input type="hidden" name="' . esc_attr( VIEW_VAR ) . '" value="' . esc_attr( $view ) . '">';
	}
}
add_action( 'restrict_manage_posts', __NAMESPACE__ . '\\filters' );

/**
 * Admin styles for the list and the editor panels.
 *
 * @param string $hook Admin page hook.
 */
function enqueue_admin_styles( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || Core\POST_TYPE !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'casa-eventos-admin', CASA_EVENTOS_URL . 'assets/admin/admin.css', array(), CASA_EVENTOS_VERSION );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_styles' );

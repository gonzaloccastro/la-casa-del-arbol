<?php
/**
 * Category screen (Eventos → Categorías): color and chip order fields, and
 * list columns. Values are read and written through the core taxonomy API.
 *
 * @package CasaEventos
 */

namespace CasaEventos\Admin;

use CasaEventos\Core;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CATEGORY_NONCE = 'casa_eventos_category';

/**
 * Color select markup.
 *
 * @param string $current Current color.
 */
function category_color_select( $current ) {
	$labels = array(
		Core\COLOR_MINT   => __( 'Menta', 'casa-eventos' ),
		Core\COLOR_YELLOW => __( 'Amarillo', 'casa-eventos' ),
	);
	echo '<select name="casa_eventos_color" id="casa-eventos-color">';
	foreach ( Core\colors() as $color ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $color ), selected( $current, $color, false ), esc_html( $labels[ $color ] ?? $color ) );
	}
	echo '</select>';
}

/**
 * Fields on the "Añadir categoría" form.
 */
function category_add_fields() {
	wp_nonce_field( CATEGORY_NONCE, CATEGORY_NONCE . '_nonce' );
	?>
	<div class="form-field">
		<label for="casa-eventos-color"><?php esc_html_e( 'Color de la etiqueta', 'casa-eventos' ); ?></label>
		<?php category_color_select( Core\COLOR_MINT ); ?>
		<p><?php esc_html_e( 'Color de la etiqueta de categoría en tarjetas y en la página del evento.', 'casa-eventos' ); ?></p>
	</div>
	<div class="form-field">
		<label for="casa-eventos-order"><?php esc_html_e( 'Orden', 'casa-eventos' ); ?></label>
		<input type="number" name="casa_eventos_order" id="casa-eventos-order" value="0" step="1">
		<p><?php esc_html_e( 'Orden de los filtros de la Agenda (menor primero; a igual orden, alfabético).', 'casa-eventos' ); ?></p>
	</div>
	<?php
}
add_action( Core\TAXONOMY . '_add_form_fields', __NAMESPACE__ . '\\category_add_fields' );

/**
 * Fields on the "Editar categoría" form.
 *
 * @param WP_Term $term Term.
 */
function category_edit_fields( $term ) {
	?>
	<tr class="form-field">
		<th scope="row"><label for="casa-eventos-color"><?php esc_html_e( 'Color de la etiqueta', 'casa-eventos' ); ?></label></th>
		<td>
			<?php wp_nonce_field( CATEGORY_NONCE, CATEGORY_NONCE . '_nonce' ); ?>
			<?php category_color_select( Core\category_color( $term ) ); ?>
			<p class="description"><?php esc_html_e( 'Color de la etiqueta de categoría en tarjetas y en la página del evento.', 'casa-eventos' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="casa-eventos-order"><?php esc_html_e( 'Orden', 'casa-eventos' ); ?></label></th>
		<td>
			<input type="number" name="casa_eventos_order" id="casa-eventos-order" value="<?php echo esc_attr( (string) Core\category_order( $term ) ); ?>" step="1">
			<p class="description"><?php esc_html_e( 'Orden de los filtros de la Agenda (menor primero; a igual orden, alfabético).', 'casa-eventos' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( Core\TAXONOMY . '_edit_form_fields', __NAMESPACE__ . '\\category_edit_fields' );

/**
 * Save the fields (term created or edited from the category screens).
 *
 * @param int $term_id Term ID.
 */
function save_category( $term_id ) {
	if ( ! isset( $_POST[ CATEGORY_NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ CATEGORY_NONCE . '_nonce' ] ) ), CATEGORY_NONCE ) ) {
		return;
	}
	if ( ! Core\can_manage_categories() ) {
		return;
	}
	$color = isset( $_POST['casa_eventos_color'] ) ? sanitize_key( wp_unslash( $_POST['casa_eventos_color'] ) ) : Core\COLOR_MINT;
	$order = isset( $_POST['casa_eventos_order'] ) ? (int) wp_unslash( $_POST['casa_eventos_order'] ) : 0;
	Core\save_category_fields( (int) $term_id, $color, $order );
}
add_action( 'created_' . Core\TAXONOMY, __NAMESPACE__ . '\\save_category' );
add_action( 'edited_' . Core\TAXONOMY, __NAMESPACE__ . '\\save_category' );

/**
 * Category list columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function category_columns( $columns ) {
	unset( $columns['description'] );
	$columns['casa_color'] = __( 'Color', 'casa-eventos' );
	$columns['casa_order'] = __( 'Orden', 'casa-eventos' );
	return $columns;
}
add_filter( 'manage_edit-' . Core\TAXONOMY . '_columns', __NAMESPACE__ . '\\category_columns' );

/**
 * Category list column content.
 *
 * @param string $content Content.
 * @param string $column  Column.
 * @param int    $term_id Term ID.
 * @return string
 */
function category_column_content( $content, $column, $term_id ) {
	if ( 'casa_color' === $column ) {
		$color  = Core\category_color( $term_id );
		$labels = array(
			Core\COLOR_MINT   => __( 'Menta', 'casa-eventos' ),
			Core\COLOR_YELLOW => __( 'Amarillo', 'casa-eventos' ),
		);
		return sprintf( '<span class="casa-eventos-swatch casa-eventos-swatch--%1$s"></span> %2$s', esc_attr( $color ), esc_html( $labels[ $color ] ) );
	}
	if ( 'casa_order' === $column ) {
		return esc_html( (string) Core\category_order( $term_id ) );
	}
	return $content;
}
add_filter( 'manage_' . Core\TAXONOMY . '_custom_column', __NAMESPACE__ . '\\category_column_content', 10, 3 );

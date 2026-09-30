<?php
/**
 * Empty state of the Agenda wall, printed instead of the grid (decision
 * N1): no events in the month, or none of the selected category.
 *
 * Args: view (lcda_agenda_view()).
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lcda_view = $args['view'] ?? null;
if ( ! $lcda_view ) {
	return;
}
?>
<p class="lcda-agenda-empty">
	<?php
	if ( $lcda_view['events'] ) {
		esc_html_e( 'No hay eventos de esta categoría para este mes.', 'la-casa-del-arbol' );
	} else {
		esc_html_e( 'No hay eventos programados para este mes.', 'la-casa-del-arbol' );
	}
	?>
</p>

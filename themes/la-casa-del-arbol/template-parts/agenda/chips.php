<?php
/**
 * Category chips of the Agenda (the items of ul.lcda-filter-chips; the
 * list element itself is the page's block). "Todos" + the chip categories
 * of the view, in plugin order (decision N9 and its E2.2 exception: a
 * selected category without events this month keeps its chip). Server-side
 * links; the active one has is-active and aria-current="page". Links keep a
 * non-current month; "Todos" drops the category.
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

$lcda_chips = array(
	array(
		'label'  => __( 'Todos', 'la-casa-del-arbol' ),
		'url'    => lcda_agenda_url( $lcda_view['month'] ),
		'active' => null === $lcda_view['category'],
	),
);
foreach ( $lcda_view['chips'] as $lcda_term ) {
	$lcda_chips[] = array(
		'label'  => $lcda_term->name,
		'url'    => lcda_agenda_url( $lcda_view['month'], $lcda_term ),
		'active' => $lcda_view['category'] && $lcda_view['category']->term_id === $lcda_term->term_id,
	);
}

foreach ( $lcda_chips as $lcda_chip ) :
	?>
<li class="lcda-filter-chip<?php echo $lcda_chip['active'] ? ' is-active' : ''; ?>"><a href="<?php echo esc_url( $lcda_chip['url'] ); ?>"<?php echo $lcda_chip['active'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $lcda_chip['label'] ); ?></a></li>
	<?php
endforeach;

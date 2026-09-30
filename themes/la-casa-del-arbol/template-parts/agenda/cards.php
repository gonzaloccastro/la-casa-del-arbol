<?php
/**
 * Cards of the Agenda wall (the children of .lcda-event-grid--agenda; the
 * grid element itself is the page's block): the shared event card, full
 * variant, <h2> titles under the month <h1>. DOM order is the plugin's
 * chronological order; the CSS masonry only places the cards.
 *
 * Args: view (lcda_agenda_view()).
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( $args['view']['shown'] ?? array() as $lcda_agenda_event ) {
	get_template_part(
		'template-parts/event/card',
		null,
		array(
			'event'   => $lcda_agenda_event,
			'variant' => 'full',
			'heading' => 'h2',
		)
	);
}

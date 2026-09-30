<?php
/**
 * "También en la agenda": up to 3 compact cards of related events
 * (single-event-contract.md). Selection is casa-eventos's
 * (Queries::related_events: other upcoming listed events, neither paused
 * nor cancelled, nearest first); the theme does not filter again. With no
 * related events the whole section is left out.
 *
 * Args: event (CasaEventos\Core\Event) required.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lcda_event = $args['event'] ?? null;
if ( ! $lcda_event ) {
	return;
}

$lcda_related = \CasaEventos\Core\Queries::related_events( $lcda_event, array( 'limit' => 3 ) );
if ( ! $lcda_related ) {
	return;
}
?>
<section class="lcda-section lcda-event-related">
	<div class="lcda-container">
		<div class="lcda-section-heading">
			<div class="lcda-section-heading__text">
				<h2 class="lcda-section-heading__title"><?php esc_html_e( 'También en la agenda', 'la-casa-del-arbol' ); ?></h2>
			</div>
		</div>
		<div class="lcda-event-grid lcda-event-grid--related">
			<?php
			foreach ( $lcda_related as $lcda_related_event ) {
				get_template_part(
					'template-parts/event/card',
					null,
					array(
						'event'   => $lcda_related_event,
						'variant' => 'compact',
						'heading' => 'h3',
					)
				);
			}
			?>
		</div>
	</div>
</section>

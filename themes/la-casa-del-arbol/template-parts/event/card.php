<?php
/**
 * Event card (event-markup-contract.md "Event card").
 *
 * Args:
 * - event   (CasaEventos\Core\Event) required.
 * - variant 'featured' | 'full' | 'compact' (default 'compact').
 * - heading heading tag for the title: 'h2' (Agenda) or 'h3' (default).
 *
 * The card's only link is the title's, stretched over the whole card by
 * CSS. Empty optional parts are left out of the markup. A cancelled event's
 * full (Agenda) card carries a red "Cancelado" tag in its header (E2.2,
 * decision N2); the poster stamp stays for the entry only. The featured
 * (Home) card's meta also shows the date, "Sáb 03/10 · 21:00" (E2.3).
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lcda_event   = $args['event'] ?? null;
$lcda_variant = in_array( $args['variant'] ?? '', array( 'featured', 'full', 'compact' ), true ) ? $args['variant'] : 'compact';
$lcda_heading = 'h2' === ( $args['heading'] ?? '' ) ? 'h2' : 'h3';
if ( ! $lcda_event ) {
	return;
}

$lcda_poster   = lcda_event_poster_html( $lcda_event, 'full' === $lcda_variant ? 'wall' : 'card' );
$lcda_day      = lcda_event_burst_day( $lcda_event );
$lcda_meta     = lcda_event_card_meta_html( $lcda_event, 'featured' === $lcda_variant );
$lcda_category = 'compact' === $lcda_variant ? null : $lcda_event->category();
$lcda_stamp    = 'full' === $lcda_variant ? lcda_event_stamp_text( $lcda_event ) : '';
$lcda_subtitle = 'full' === $lcda_variant ? trim( $lcda_event->subtitle() ) : '';
$lcda_entrada  = 'compact' === $lcda_variant ? '' : lcda_event_entry_text( $lcda_event );
$lcda_venue    = 'full' === $lcda_variant ? $lcda_event->venue_name() : '';
// Agenda (full) only: Related and Home never list cancelled events.
$lcda_cancelled = 'full' === $lcda_variant && $lcda_event->is_cancelled();
?>
<article class="lcda-event-card lcda-event-card--<?php echo esc_attr( $lcda_variant ); ?>">
	<div class="lcda-event-card__poster">
		<?php
		echo $lcda_poster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated <img>.
		if ( '' !== $lcda_day ) :
			?>
			<span class="lcda-burst lcda-event-card__burst" aria-hidden="true"><?php echo esc_html( $lcda_day ); ?></span>
			<?php
		endif;
		if ( '' !== $lcda_stamp ) :
			?>
			<span class="lcda-stamp lcda-event-card__stamp" aria-hidden="true"><?php echo esc_html( $lcda_stamp ); ?></span>
		<?php endif; ?>
	</div>
	<div class="lcda-event-card__body">
		<?php if ( 'compact' === $lcda_variant ) : ?>
			<?php if ( '' !== $lcda_meta ) : ?>
				<p class="lcda-event-card__meta"><?php echo $lcda_meta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in lcda_event_card_meta_html(). ?></p>
			<?php endif; ?>
		<?php else : ?>
			<div class="lcda-event-card__header">
				<?php if ( '' !== $lcda_meta ) : ?>
					<p class="lcda-event-card__meta"><?php echo $lcda_meta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in lcda_event_card_meta_html(). ?></p>
				<?php endif; ?>
				<?php if ( $lcda_cancelled ) : ?>
					<p class="lcda-tag lcda-tag--cancelled"><?php esc_html_e( 'Cancelado', 'la-casa-del-arbol' ); ?></p>
				<?php endif; ?>
				<?php if ( $lcda_category ) : ?>
					<p class="<?php echo esc_attr( lcda_event_tag_class( $lcda_event ) ); ?>"><?php echo esc_html( $lcda_category->name ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<<?php echo $lcda_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2|h3 whitelist. ?> class="lcda-event-card__title"><a class="lcda-event-card__link" href="<?php echo esc_url( $lcda_event->permalink() ); ?>"><?php echo esc_html( $lcda_event->title() ); ?></a></<?php echo $lcda_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2|h3 whitelist. ?>>
		<?php if ( '' !== $lcda_subtitle ) : ?>
			<p class="lcda-event-card__subtitle"><?php echo esc_html( $lcda_subtitle ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $lcda_venue || '' !== $lcda_entrada ) : ?>
			<div class="lcda-event-card__footer">
				<?php if ( '' !== $lcda_venue ) : ?>
					<span class="lcda-event-card__venue"><?php echo esc_html( $lcda_venue ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $lcda_entrada ) : ?>
					<span class="lcda-event-card__entrada"><?php echo esc_html( $lcda_entrada ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</article>

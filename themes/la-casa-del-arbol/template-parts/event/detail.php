<?php
/**
 * Single Event main section: back link + poster + info
 * (single-event-contract.md "Reference markup").
 *
 * Args: event (CasaEventos\Core\Event) required.
 *
 * Optional parts are left out when their value is missing. There is no
 * "Compartir" (not decided) and no ticket selector (no ticketing yet). The
 * primary action or state note comes from lcda_event_action(), which only
 * words the plugin's CTA decision.
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

$lcda_back      = lcda_event_back_url( $lcda_event );
$lcda_poster    = lcda_event_poster_html( $lcda_event, 'detail' );
$lcda_day       = lcda_event_burst_day( $lcda_event );
$lcda_category  = $lcda_event->category();
$lcda_when      = lcda_event_when_html( $lcda_event );
$lcda_where     = $lcda_event->venue_address();
$lcda_entrada   = lcda_event_entry_text( $lcda_event );
$lcda_secondary = trim( $lcda_event->subtitle() );
$lcda_has_body  = '' !== trim( (string) get_post_field( 'post_content', $lcda_event->post() ) );
$lcda_action    = lcda_event_action( $lcda_event );
?>
<section class="lcda-section lcda-event-main">
	<div class="lcda-container">
		<?php if ( '' !== $lcda_back ) : ?>
			<p class="lcda-back-link"><a href="<?php echo esc_url( $lcda_back ); ?>"><span aria-hidden="true">← </span><?php esc_html_e( 'Volver a la agenda', 'la-casa-del-arbol' ); ?></a></p>
		<?php endif; ?>
		<article class="lcda-event-detail">
			<div class="lcda-event-detail__poster">
				<?php
				echo $lcda_poster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated <img>.
				if ( '' !== $lcda_day ) :
					?>
					<span class="lcda-burst lcda-event-detail__burst" aria-hidden="true"><?php echo esc_html( $lcda_day ); ?></span>
				<?php endif; ?>
			</div>
			<div class="lcda-event-detail__info">
				<?php if ( $lcda_category ) : ?>
					<p class="<?php echo esc_attr( lcda_event_tag_class( $lcda_event, 'lcda-tag--large' ) ); ?>"><?php echo esc_html( $lcda_category->name ); ?></p>
				<?php endif; ?>
				<h1 class="lcda-event-detail__title"><?php echo esc_html( $lcda_event->title() ); ?></h1>
				<dl class="lcda-event-meta">
					<?php if ( '' !== $lcda_when ) : ?>
						<div class="lcda-event-meta__item"><dt class="lcda-event-meta__label"><?php esc_html_e( 'Cuándo', 'la-casa-del-arbol' ); ?></dt><dd class="lcda-event-meta__value"><?php echo $lcda_when; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in lcda_event_when_html(). ?></dd></div>
					<?php endif; ?>
					<?php if ( '' !== $lcda_where ) : ?>
						<div class="lcda-event-meta__item"><dt class="lcda-event-meta__label"><?php esc_html_e( 'Dónde', 'la-casa-del-arbol' ); ?></dt><dd class="lcda-event-meta__value"><?php echo esc_html( $lcda_where ); ?></dd></div>
					<?php endif; ?>
					<?php if ( '' !== $lcda_entrada ) : ?>
						<div class="lcda-event-meta__item"><dt class="lcda-event-meta__label"><?php esc_html_e( 'Entrada', 'la-casa-del-arbol' ); ?></dt><dd class="lcda-event-meta__value"><?php echo esc_html( $lcda_entrada ); ?></dd></div>
					<?php endif; ?>
				</dl>
				<?php if ( $lcda_has_body ) : ?>
					<div class="lcda-event-detail__description">
						<?php the_content(); ?>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $lcda_secondary ) : ?>
					<p class="lcda-event-detail__secondary"><?php echo esc_html( $lcda_secondary ); ?></p>
				<?php endif; ?>
				<?php if ( $lcda_action && 'link' === $lcda_action['type'] ) : ?>
					<div class="lcda-event-cta">
						<a class="lcda-btn" <?php echo $lcda_action['attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in lcda_event_action(). ?>><?php echo esc_html( $lcda_action['label'] ); ?></a>
					</div>
				<?php elseif ( $lcda_action ) : ?>
					<p class="lcda-event-status"><?php echo esc_html( $lcda_action['text'] ); ?></p>
				<?php endif; ?>
			</div>
		</article>
	</div>
</section>

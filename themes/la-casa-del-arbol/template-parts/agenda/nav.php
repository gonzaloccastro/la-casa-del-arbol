<?php
/**
 * Month navigation of the Agenda: "← Agosto" / "Octubre →", secondary to
 * the month heading (decision N6). The targets are the nearest months with
 * events (of the selected category, when there is one), as chosen by
 * Queries::adjacent_event_month(). A missing side is left out; with neither
 * side there is no nav.
 *
 * Args: view (lcda_agenda_view()).
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lcda_view = $args['view'] ?? null;
if ( ! $lcda_view || ( null === $lcda_view['prev'] && null === $lcda_view['next'] ) ) {
	return;
}
?>
<nav class="lcda-agenda-nav" aria-label="<?php esc_attr_e( 'Meses de la agenda', 'la-casa-del-arbol' ); ?>">
	<?php if ( null !== $lcda_view['prev'] ) : ?>
		<a class="lcda-agenda-nav__link lcda-agenda-nav__link--prev" href="<?php echo esc_url( lcda_agenda_url( $lcda_view['prev'], $lcda_view['category'] ) ); ?>" rel="prev"><span aria-hidden="true">← </span><?php echo esc_html( lcda_agenda_month_label( $lcda_view['prev'], $lcda_view['current'] ) ); ?></a>
	<?php endif; ?>
	<?php if ( null !== $lcda_view['next'] ) : ?>
		<a class="lcda-agenda-nav__link lcda-agenda-nav__link--next" href="<?php echo esc_url( lcda_agenda_url( $lcda_view['next'], $lcda_view['category'] ) ); ?>" rel="next"><?php echo esc_html( lcda_agenda_month_label( $lcda_view['next'], $lcda_view['current'] ) ); ?><span aria-hidden="true"> →</span></a>
	<?php endif; ?>
</nav>

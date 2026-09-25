<?php
/**
 * Title: Home — Eventos destacados del mes
 * Slug: la-casa-del-arbol/home-featured-events
 * Categories: lcda
 * Keywords: eventos, destacados, agenda, tarjetas, afiches, home, inicio
 * Description: Sección "Eventos destacados del mes": volanta con el mes, título, enlace "Ver agenda completa →" (solo en computadora), 4 tarjetas de evento y botón "Ver toda la agenda". Las tarjetas son contenido de demostración: en cada una elegí el afiche, reemplazá los textos y enlazá el título a la página del evento. Actualizá la volanta cada mes.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * Presentational until casa-eventos exists: the cards are the demo grid
 * (patterns/demo-event-grid-featured.php, included below so the demo
 * markup lives in one file). casa-eventos later outputs real events into
 * the same lcda-event-grid--featured markup (event-markup-contract.md).
 *
 * The Agenda links point at the page with the slug "agenda" when it
 * exists, resolved when the pattern is inserted; otherwise they are left
 * without a destination for the editor to link.
 *
 * @package LaCasaDelArbol
 */

$lcda_agenda      = get_page_by_path( 'agenda' );
$lcda_agenda_href = ( $lcda_agenda && 'publish' === $lcda_agenda->post_status ) ? ' href="' . esc_url( get_permalink( $lcda_agenda ) ) . '"' : '';
?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section"} -->
<section class="wp-block-group alignfull lcda-section"><!-- wp:group {"className":"lcda-container"} -->
<div class="wp-block-group lcda-container"><!-- wp:group {"className":"lcda-section-heading"} -->
<div class="wp-block-group lcda-section-heading"><!-- wp:group {"className":"lcda-section-heading__text"} -->
<div class="wp-block-group lcda-section-heading__text"><!-- wp:paragraph {"className":"lcda-eyebrow"} -->
<p class="lcda-eyebrow">Septiembre 2026</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"lcda-section-heading__title"} -->
<h2 class="wp-block-heading lcda-section-heading__title">Eventos destacados del mes</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"lcda-section-heading__action lcda-hide-on-mobile"} -->
<div class="wp-block-buttons lcda-section-heading__action lcda-hide-on-mobile"><!-- wp:button {"className":"is-style-lcda-text-link"} -->
<div class="wp-block-button is-style-lcda-text-link"><a class="wp-block-button__link wp-element-button"<?php echo $lcda_agenda_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>Ver agenda completa →</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<?php require __DIR__ . '/demo-event-grid-featured.php'; ?>

<!-- wp:buttons {"className":"lcda-section-actions","layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons lcda-section-actions"><!-- wp:button {"className":"is-style-lcda-dark"} -->
<div class="wp-block-button is-style-lcda-dark"><a class="wp-block-button__link wp-element-button"<?php echo $lcda_agenda_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>Ver toda la agenda</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

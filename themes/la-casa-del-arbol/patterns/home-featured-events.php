<?php
/**
 * Title: Home — Eventos destacados
 * Slug: la-casa-del-arbol/home-featured-events
 * Categories: lcda
 * Keywords: eventos, destacados, agenda, tarjetas, afiches, home, inicio
 * Description: Sección "Eventos destacados": volanta, título, enlace "Ver agenda completa →" (solo en computadora), hasta 4 tarjetas de evento y botón "Ver toda la agenda". Las tarjetas se generan solas con los próximos eventos marcados como destacados en Eventos: el texto que se ve acá en el editor no se publica. Si no hay eventos destacados próximos, la sección entera no se muestra.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * The lcda-event-grid--featured group is a render slot (inc/home.php,
 * E2.3): at render time its content is replaced by the featured event
 * cards from casa-eventos, and the whole section is left out when there
 * are none (or casa-eventos is inactive). The paragraph inside is an
 * editor-only note and is never published. Pages built from Home 0.4.x
 * still hold the 4 demo cards in this group; the slot replaces them too
 * (home-contract.md). The eyebrow and heading are page content.
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
<p class="lcda-eyebrow">Próximas fechas</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"lcda-section-heading__title"} -->
<h2 class="wp-block-heading lcda-section-heading__title">Eventos destacados</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"lcda-section-heading__action lcda-hide-on-mobile"} -->
<div class="wp-block-buttons lcda-section-heading__action lcda-hide-on-mobile"><!-- wp:button {"className":"is-style-lcda-text-link"} -->
<div class="wp-block-button is-style-lcda-text-link"><a class="wp-block-button__link wp-element-button"<?php echo $lcda_agenda_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>Ver agenda completa →</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"lcda-event-grid lcda-event-grid--featured"} -->
<div class="wp-block-group alignwide lcda-event-grid lcda-event-grid--featured"><!-- wp:paragraph -->
<p>Los eventos destacados se cargan automáticamente desde Eventos. Este texto no se publica.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"lcda-section-actions","layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons lcda-section-actions"><!-- wp:button {"className":"is-style-lcda-dark"} -->
<div class="wp-block-button is-style-lcda-dark"><a class="wp-block-button__link wp-element-button"<?php echo $lcda_agenda_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>Ver toda la agenda</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

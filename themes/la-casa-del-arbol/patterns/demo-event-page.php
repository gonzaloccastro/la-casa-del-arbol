<?php
/**
 * Title: Evento (página de demostración)
 * Slug: la-casa-del-arbol/demo-event-page
 * Categories: lcda-demo
 * Keywords: evento, página de evento, afiche, entradas, reservar, demo
 * Description: Solo para revisar el diseño de la página de un evento (una única página de prueba, con la plantilla "La Casa — Lienzo"). No es la forma de cargar eventos: cuando exista casa-eventos, cada evento se carga en Eventos y su página se arma sola con este mismo diseño; esta página de prueba se borra. Trae afiche con estrella de fecha, categoría, título, Cuándo / Dónde / Entrada, descripción, bajada, botón principal ("Comprar entradas" o "Reservar") y "Compartir" (solo visuales, sin destino), y la fila "También en la agenda".
 * Viewport Width: 1400
 * Inserter: yes
 *
 * Demo content only (single-event-contract.md). It renders the approved
 * Single Event screen from core blocks so the design can be reviewed in
 * WordPress. It is deliberately not a starter pattern for new pages: events
 * are not authored as pages. casa-eventos later renders the same markup
 * (with <dl>, <time datetime>, aria-hidden decorations and real links) from
 * the Event entity, and this file is deleted.
 *
 * The back link points at the published page with the slug "agenda",
 * resolved when the pattern is inserted; otherwise it has no destination.
 * The primary button and "Compartir" have no destination on purpose: they
 * are presentation only until casa-eventos provides the purchase or
 * reservation action.
 *
 * @package LaCasaDelArbol
 */

$lcda_agenda      = get_page_by_path( 'agenda' );
$lcda_agenda_href = ( $lcda_agenda && 'publish' === $lcda_agenda->post_status ) ? ' href="' . esc_url( get_permalink( $lcda_agenda ) ) . '"' : '';
?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-event-main"} -->
<section class="wp-block-group alignfull lcda-section lcda-event-main"><!-- wp:group {"className":"lcda-container"} -->
<div class="wp-block-group lcda-container"><!-- wp:paragraph {"className":"lcda-back-link"} -->
<p class="lcda-back-link"><a<?php echo $lcda_agenda_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>← Volver a la agenda</a></p>
<!-- /wp:paragraph -->

<!-- wp:group {"tagName":"article","className":"lcda-event-detail"} -->
<article class="wp-block-group lcda-event-detail"><!-- wp:group {"className":"lcda-event-detail__poster"} -->
<div class="wp-block-group lcda-event-detail__poster"><!-- wp:image -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"className":"lcda-burst lcda-event-detail__burst"} -->
<p class="lcda-burst lcda-event-detail__burst">00</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"lcda-event-detail__info"} -->
<div class="wp-block-group lcda-event-detail__info"><!-- wp:paragraph {"className":"lcda-tag lcda-tag--large"} -->
<p class="lcda-tag lcda-tag--large">Categoría</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"lcda-event-detail__title"} -->
<h1 class="wp-block-heading lcda-event-detail__title">Título del evento</h1>
<!-- /wp:heading -->

<!-- wp:group {"className":"lcda-event-meta"} -->
<div class="wp-block-group lcda-event-meta"><!-- wp:group {"className":"lcda-event-meta__item"} -->
<div class="wp-block-group lcda-event-meta__item"><!-- wp:paragraph {"className":"lcda-event-meta__label"} -->
<p class="lcda-event-meta__label">Cuándo</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"lcda-event-meta__value"} -->
<p class="lcda-event-meta__value">Día 00/00 · 00:00hs</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"lcda-event-meta__item"} -->
<div class="wp-block-group lcda-event-meta__item"><!-- wp:paragraph {"className":"lcda-event-meta__label"} -->
<p class="lcda-event-meta__label">Dónde</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"lcda-event-meta__value"} -->
<p class="lcda-event-meta__value">Lugar</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"lcda-event-meta__item"} -->
<div class="wp-block-group lcda-event-meta__item"><!-- wp:paragraph {"className":"lcda-event-meta__label"} -->
<p class="lcda-event-meta__label">Entrada</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"lcda-event-meta__value"} -->
<p class="lcda-event-meta__value">Entrada</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"lcda-event-detail__description"} -->
<div class="wp-block-group lcda-event-detail__description"><!-- wp:paragraph -->
<p>Descripción del evento: dos o tres líneas que cuentan la propuesta, quiénes participan y qué va a pasar en la casa esa noche. Puede tener varios párrafos, títulos, listas y enlaces.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"lcda-event-detail__secondary"} -->
<p class="lcda-event-detail__secondary">Bajada del evento.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"lcda-event-cta"} -->
<div class="wp-block-buttons lcda-event-cta"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Comprar entradas</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-lcda-outline"} -->
<div class="wp-block-button is-style-lcda-outline"><a class="wp-block-button__link wp-element-button">Compartir</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></article>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-event-related"} -->
<section class="wp-block-group alignfull lcda-section lcda-event-related"><!-- wp:group {"className":"lcda-container"} -->
<div class="wp-block-group lcda-container"><!-- wp:group {"className":"lcda-section-heading"} -->
<div class="wp-block-group lcda-section-heading"><!-- wp:group {"className":"lcda-section-heading__text"} -->
<div class="wp-block-group lcda-section-heading__text"><!-- wp:heading {"className":"lcda-section-heading__title"} -->
<h2 class="wp-block-heading lcda-section-heading__title">También en la agenda</h2>
<!-- /wp:heading --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<?php require __DIR__ . '/demo-event-grid-related.php'; ?></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

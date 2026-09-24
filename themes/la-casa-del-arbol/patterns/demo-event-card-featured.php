<?php
/**
 * Title: Tarjeta de evento — Destacada (demo)
 * Slug: la-casa-del-arbol/demo-event-card-featured
 * Categories: lcda-demo
 * Keywords: evento, tarjeta, destacado, afiche
 * Description: Contenido de demostración. Tarjeta "Destacados" de la Home: afiche, fecha, día y hora, categoría, título y entrada. Elegí el afiche, reemplazá los textos y enlazá el título a la página del evento (toda la tarjeta queda clickeable). Categoría amarilla: color de fondo Amarillo.
 * Viewport Width: 400
 * Inserter: yes
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"article","className":"lcda-event-card lcda-event-card--featured"} -->
<article class="wp-block-group lcda-event-card lcda-event-card--featured"><!-- wp:group {"className":"lcda-event-card__poster"} -->
<div class="wp-block-group lcda-event-card__poster"><!-- wp:image -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"className":"lcda-burst lcda-event-card__burst"} -->
<p class="lcda-burst lcda-event-card__burst">00</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"lcda-event-card__body"} -->
<div class="wp-block-group lcda-event-card__body"><!-- wp:group {"className":"lcda-event-card__header"} -->
<div class="wp-block-group lcda-event-card__header"><!-- wp:paragraph {"className":"lcda-event-card__meta"} -->
<p class="lcda-event-card__meta">Día · 00:00</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"lcda-tag"} -->
<p class="lcda-tag">Categoría</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading {"level":3,"className":"lcda-event-card__title"} -->
<h3 class="wp-block-heading lcda-event-card__title">Título del evento</h3>
<!-- /wp:heading -->

<!-- wp:group {"className":"lcda-event-card__footer"} -->
<div class="wp-block-group lcda-event-card__footer"><!-- wp:paragraph {"className":"lcda-event-card__entrada"} -->
<p class="lcda-event-card__entrada">Entrada</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></article>
<!-- /wp:group -->

<?php
/**
 * Title: Tarjeta de evento — Agenda (demo)
 * Slug: la-casa-del-arbol/demo-event-card-full
 * Categories: lcda-demo
 * Keywords: evento, tarjeta, agenda, afiche
 * Description: Contenido de demostración. Tarjeta completa de la Agenda: afiche, fecha, sello, día y hora, categoría, título, bajada, lugar y entrada. El sello circular va solo si la entrada es "Entrada libre" o "A la gorra"; si no, borralo. Enlazá el título a la página del evento (toda la tarjeta queda clickeable).
 * Viewport Width: 440
 * Inserter: yes
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"article","className":"lcda-event-card lcda-event-card--full"} -->
<article class="wp-block-group lcda-event-card lcda-event-card--full"><!-- wp:group {"className":"lcda-event-card__poster"} -->
<div class="wp-block-group lcda-event-card__poster"><!-- wp:image -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"className":"lcda-burst lcda-event-card__burst"} -->
<p class="lcda-burst lcda-event-card__burst">00</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"lcda-stamp lcda-event-card__stamp"} -->
<p class="lcda-stamp lcda-event-card__stamp">Entrada libre</p>
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

<!-- wp:paragraph {"className":"lcda-event-card__subtitle"} -->
<p class="lcda-event-card__subtitle">Bajada del evento.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"lcda-event-card__footer"} -->
<div class="wp-block-group lcda-event-card__footer"><!-- wp:paragraph {"className":"lcda-event-card__venue"} -->
<p class="lcda-event-card__venue">La Casa del Árbol</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"lcda-event-card__entrada"} -->
<p class="lcda-event-card__entrada">Entrada libre</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></article>
<!-- /wp:group -->

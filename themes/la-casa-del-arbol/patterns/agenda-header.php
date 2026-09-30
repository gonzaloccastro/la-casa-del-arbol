<?php
/**
 * Title: Agenda — Encabezado
 * Slug: la-casa-del-arbol/agenda-header
 * Categories: lcda
 * Keywords: agenda, encabezado, mes, filtros, categorías, chips
 * Description: Encabezado de la Agenda: volanta "Agenda cultural", el mes como título principal (H1), la navegación entre meses y las categorías. El mes, la navegación y las categorías se generan solos con los eventos de Eventos: el título y la lista que se ven acá en el editor se reemplazan al publicar. La volanta se edita acá.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * Composition contract: docs/implementation/agenda-contract.md.
 * Render slots (inc/agenda.php, E2.2): the heading's text becomes the
 * selected month and the month navigation is printed after it; the
 * lcda-filter-chips list gets the month's category chips (links), or
 * nothing. "Agenda" and "Todos" are neutral fallbacks for the editor (and
 * the heading's text if casa-eventos is ever inactive). Pages built from
 * Agenda 0.5.0 ("Septiembre" + six static chips) are taken over the same
 * way.
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-agenda-header"} -->
<section class="wp-block-group alignfull lcda-section lcda-agenda-header"><!-- wp:group {"className":"lcda-container"} -->
<div class="wp-block-group lcda-container"><!-- wp:paragraph {"className":"lcda-eyebrow"} -->
<p class="lcda-eyebrow">Agenda cultural</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"lcda-agenda-header__title"} -->
<h1 class="wp-block-heading lcda-agenda-header__title">Agenda</h1>
<!-- /wp:heading -->

<!-- wp:list {"className":"lcda-filter-chips"} -->
<ul class="wp-block-list lcda-filter-chips"><!-- wp:list-item {"className":"lcda-filter-chip is-active"} -->
<li class="lcda-filter-chip is-active">Todos</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

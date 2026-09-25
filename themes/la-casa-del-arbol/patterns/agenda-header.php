<?php
/**
 * Title: Agenda — Encabezado
 * Slug: la-casa-del-arbol/agenda-header
 * Categories: lcda
 * Keywords: agenda, encabezado, mes, filtros, categorías, chips
 * Description: Encabezado de la Agenda: volanta "Agenda cultural", el mes como título principal (H1) y las categorías. Cambiá el mes cada mes. Las categorías son solo visuales por ahora: no filtran. Cuando exista casa-eventos las va a generar y a hacer funcionar; mientras tanto, la categoría resaltada en amarillo es "Todos".
 * Viewport Width: 1400
 * Inserter: yes
 *
 * Composition contract: docs/implementation/agenda-contract.md.
 * The chips are a plain list (not links or buttons), so they don't claim
 * to filter anything. casa-eventos later replaces the list with its own
 * output in the same lcda-filter-chips markup.
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
<h1 class="wp-block-heading lcda-agenda-header__title">Septiembre</h1>
<!-- /wp:heading -->

<!-- wp:list {"className":"lcda-filter-chips"} -->
<ul class="wp-block-list lcda-filter-chips"><!-- wp:list-item {"className":"lcda-filter-chip is-active"} -->
<li class="lcda-filter-chip is-active">Todos</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"className":"lcda-filter-chip"} -->
<li class="lcda-filter-chip">Música en vivo</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"className":"lcda-filter-chip"} -->
<li class="lcda-filter-chip">Cine</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"className":"lcda-filter-chip"} -->
<li class="lcda-filter-chip">Recreación</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"className":"lcda-filter-chip"} -->
<li class="lcda-filter-chip">Fiesta</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"className":"lcda-filter-chip"} -->
<li class="lcda-filter-chip">Festival</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<?php
/**
 * Title: Encabezado de sección
 * Slug: la-casa-del-arbol/section-heading
 * Categories: lcda
 * Keywords: encabezado, título, volanta, sección
 * Description: Volanta roja + título + acción opcional a la derecha (en mobile va debajo). Ocupa el ancho del contenedor del sitio. Si la sección no tiene acción, borrá el bloque Botones. Para una sección completa (fondo, márgenes y ritmo) usá el patrón "Sección".
 * Viewport Width: 1200
 * Inserter: yes
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"align":"wide","className":"lcda-section-heading"} -->
<div class="wp-block-group alignwide lcda-section-heading"><!-- wp:group {"className":"lcda-section-heading__text"} -->
<div class="wp-block-group lcda-section-heading__text"><!-- wp:paragraph {"className":"lcda-eyebrow"} -->
<p class="lcda-eyebrow">Volanta</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"lcda-section-heading__title"} -->
<h2 class="wp-block-heading lcda-section-heading__title">Título de sección</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"lcda-section-heading__action"} -->
<div class="wp-block-buttons lcda-section-heading__action"><!-- wp:button {"className":"is-style-lcda-text-link"} -->
<div class="wp-block-button is-style-lcda-text-link"><a class="wp-block-button__link wp-element-button">Ver más →</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

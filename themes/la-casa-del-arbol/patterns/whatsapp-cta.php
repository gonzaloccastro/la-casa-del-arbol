<?php
/**
 * Title: ¡Festejá en el Árbol! (WhatsApp)
 * Slug: la-casa-del-arbol/whatsapp-cta
 * Categories: lcda
 * Keywords: whatsapp, festejá, cumpleaños, eventos privados, reservas, alquilá, cta, franja
 * Description: Franja roja para cumpleaños y eventos privados: volanta, título, botón "Escribinos por WhatsApp" y la estrella "A la gorra". Textos editables. El destino del botón es el mismo del header: el primer ítem del menú "La Casa — CTA WhatsApp" (Apariencia → Menús); no hace falta enlazarlo acá.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * The shared WhatsApp CTA, rebuilt as the approved Festejá band (Handoff
 * §2.3). Same slug as the pre-V1 pattern. The button's class
 * "lcda-whatsapp-link" makes inc/blocks.php fill its link from the CTA menu
 * location at render time.
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-section--band lcda-festeja","backgroundColor":"red","textColor":"paper"} -->
<section class="wp-block-group alignfull lcda-section lcda-section--band lcda-festeja has-paper-color has-red-background-color has-text-color has-background"><!-- wp:group {"className":"lcda-container lcda-sticker-host"} -->
<div class="wp-block-group lcda-container lcda-sticker-host"><!-- wp:group {"className":"lcda-section-heading lcda-section-heading--band"} -->
<div class="wp-block-group lcda-section-heading lcda-section-heading--band"><!-- wp:group {"className":"lcda-section-heading__text"} -->
<div class="wp-block-group lcda-section-heading__text"><!-- wp:paragraph {"className":"lcda-eyebrow lcda-eyebrow--on-red"} -->
<p class="lcda-eyebrow lcda-eyebrow--on-red">Cumpleaños · Eventos privados</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"lcda-section-heading__title"} -->
<h2 class="wp-block-heading lcda-section-heading__title">¡Festejá en el Árbol!</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"lcda-section-heading__action"} -->
<div class="wp-block-buttons lcda-section-heading__action"><!-- wp:button {"className":"is-style-lcda-dark lcda-whatsapp-link"} -->
<div class="wp-block-button is-style-lcda-dark lcda-whatsapp-link"><a class="wp-block-button__link wp-element-button">Escribinos por WhatsApp</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"is-style-lcda-sticker lcda-sticker--corner"} -->
<p class="is-style-lcda-sticker lcda-sticker--corner">A la gorra</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

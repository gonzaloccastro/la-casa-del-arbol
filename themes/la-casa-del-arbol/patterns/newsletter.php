<?php
/**
 * Title: Newsletter (Comunidad)
 * Slug: la-casa-del-arbol/newsletter
 * Categories: lcda
 * Keywords: newsletter, email, suscripción, comunidad, sumarme, home
 * Description: Franja "Comunidad": volanta, título y campo de email con el botón "Sumarme". Los textos son editables. El formulario todavía no envía ni guarda nada (se conecta más adelante): el botón está desactivado y lo anuncia a lectores de pantalla. No modifiques el bloque HTML del formulario.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * Presentation only (V1 decision), like the footer "Sumate" form: not a
 * <form>, button type="button" + aria-disabled, no name/action, so nothing
 * is submitted, stored or faked. A real provider integration replaces the
 * Custom HTML block later. Insert this pattern once per page (fixed ids).
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-section--band lcda-newsletter"} -->
<section class="wp-block-group alignfull lcda-section lcda-section--band lcda-newsletter"><!-- wp:group {"className":"lcda-container"} -->
<div class="wp-block-group lcda-container"><!-- wp:group {"className":"lcda-section-heading lcda-section-heading--band"} -->
<div class="wp-block-group lcda-section-heading lcda-section-heading--band"><!-- wp:group {"className":"lcda-section-heading__text"} -->
<div class="wp-block-group lcda-section-heading__text"><!-- wp:paragraph {"className":"lcda-eyebrow"} -->
<p class="lcda-eyebrow">Comunidad</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"lcda-section-heading__title"} -->
<h2 class="wp-block-heading lcda-section-heading__title">Enterate primero de la agenda</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:html -->
<div class="lcda-newsletter-form lcda-newsletter-form--band">
<label class="screen-reader-text" for="lcda-newsletter-email">Tu email</label>
<input id="lcda-newsletter-email" class="lcda-newsletter-form__input" type="email" autocomplete="email" placeholder="tu@email.com">
<button type="button" class="lcda-newsletter-form__button" aria-disabled="true" aria-describedby="lcda-newsletter-status">Sumarme</button>
<span id="lcda-newsletter-status" class="screen-reader-text">La suscripción estará disponible próximamente.</span>
</div>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

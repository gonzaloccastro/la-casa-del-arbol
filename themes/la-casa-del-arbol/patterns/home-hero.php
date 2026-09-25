<?php
/**
 * Title: Home — Carrusel de portada
 * Slug: la-casa-del-arbol/home-hero
 * Categories: lcda
 * Keywords: portada, carrusel, hero, slider, imágenes, home, inicio
 * Description: Carrusel de fotos a ancho completo, sin textos encima: Agenda, Saberes +60, Reservas, Alquilá y La Casa. Para cambiar una foto, seleccioná la imagen, usá "Reemplazar" y escribí su texto alternativo. Para sumar o quitar fotos, duplicá o borrá imágenes. Las flechas y los puntos aparecen solos en la página publicada (sin reproducción automática). Las imágenes grises con rótulo son provisorias.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * Slides are plain image blocks inside the lcda-hero__track group; their
 * number comes from the markup. The visually hidden H1 is the page title
 * for assistive technology (the approved Home shows no visible title).
 * The images are temporary fixtures from assets/images/fixtures/ (see
 * docs/implementation/home-contract.md).
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-section--flush lcda-hero"} -->
<section class="wp-block-group alignfull lcda-section lcda-section--flush lcda-hero"><!-- wp:heading {"level":1,"className":"screen-reader-text"} -->
<h1 class="wp-block-heading screen-reader-text">La Casa del Árbol</h1>
<!-- /wp:heading -->

<!-- wp:group {"className":"lcda-hero__track"} -->
<div class="wp-block-group lcda-hero__track"><!-- wp:image {"className":"lcda-hero__slide"} -->
<figure class="wp-block-image lcda-hero__slide"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/hero-agenda.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"className":"lcda-hero__slide"} -->
<figure class="wp-block-image lcda-hero__slide"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/hero-saberes.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"className":"lcda-hero__slide"} -->
<figure class="wp-block-image lcda-hero__slide"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/hero-reservas.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"className":"lcda-hero__slide"} -->
<figure class="wp-block-image lcda-hero__slide"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/hero-alquila.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"className":"lcda-hero__slide"} -->
<figure class="wp-block-image lcda-hero__slide"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/hero-lacasa.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<?php
/**
 * Title: Seguinos en Instagram
 * Slug: la-casa-del-arbol/instagram
 * Categories: lcda
 * Keywords: instagram, redes, seguinos, fotos, posts, reels, home
 * Description: Sección blanca con la cuenta, el título, el botón "Seguir" y 6 imágenes cuadradas (6 columnas; 3 en tablet y mobile). Reemplazá cada imagen por un post real (recorte cuadrado) y escribí su texto alternativo; opcionalmente enlazala al post. Para los reels elegí el estilo de imagen "Reel de Instagram" (agrega el ▶). Imágenes fijas: no se conecta con Instagram.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * Presentational only: no API, feed plugin or external script. The images
 * are temporary fixtures from assets/images/fixtures/ (see
 * docs/implementation/home-contract.md).
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-instagram","backgroundColor":"white"} -->
<section class="wp-block-group alignfull lcda-section lcda-instagram has-white-background-color has-background"><!-- wp:group {"className":"lcda-container"} -->
<div class="wp-block-group lcda-container"><!-- wp:group {"className":"lcda-section-heading"} -->
<div class="wp-block-group lcda-section-heading"><!-- wp:group {"className":"lcda-section-heading__text"} -->
<div class="wp-block-group lcda-section-heading__text"><!-- wp:paragraph {"className":"lcda-eyebrow"} -->
<p class="lcda-eyebrow">@_lacasadelarbol_</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"lcda-section-heading__title"} -->
<h2 class="wp-block-heading lcda-section-heading__title">Seguinos en Instagram</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"lcda-section-heading__action"} -->
<div class="wp-block-buttons lcda-section-heading__action"><!-- wp:button {"className":"is-style-lcda-outline lcda-inline"} -->
<div class="wp-block-button is-style-lcda-outline lcda-inline"><a class="wp-block-button__link wp-element-button" href="https://www.instagram.com/_lacasadelarbol_/">Seguir</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"lcda-instagram-grid"} -->
<div class="wp-block-group lcda-instagram-grid"><!-- wp:image -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/ig-sept.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/ig-alquila.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"className":"is-style-lcda-reel"} -->
<figure class="wp-block-image is-style-lcda-reel"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/ig-pacto.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/ig-canto.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/ig-cosmo.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image {"className":"is-style-lcda-reel"} -->
<figure class="wp-block-image is-style-lcda-reel"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fixtures/ig-flamencos-ig.svg' ) ); ?>" alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

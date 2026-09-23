<?php
/**
 * Site footer (Handoff §1.18).
 *
 * Desktop: 4 columns (logo · Navegación · Visitanos · Sumate) + legal row.
 * Mobile: logo / Navegación | Visitanos / Sumate / stacked legal row.
 *
 * Navegación and Visitanos come from menu locations. Column labels, the
 * Sumate line and the legal row are interface copy.
 *
 * The Sumate form is presentation only in V1: it is not a <form> and its
 * button is type="button", so it can never submit or reload the page. A real
 * newsletter integration replaces this block later.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="lcda-footer">
	<div class="lcda-footer__main">
		<div class="lcda-footer__brand">
			<?php
			echo lcda_get_logo( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file, attributes escaped.
				'full',
				array(
					'class'      => 'lcda-footer__logo',
					'role'       => 'img',
					'aria-label' => get_bloginfo( 'name' ),
					'focusable'  => 'false',
				)
			);
			?>
		</div>

		<?php if ( has_nav_menu( 'lcda-footer' ) ) : ?>
			<nav class="lcda-footer__col lcda-footer__nav" aria-labelledby="lcda-footer-nav-title">
				<h2 id="lcda-footer-nav-title" class="lcda-footer__title"><?php esc_html_e( 'Navegación', 'la-casa-del-arbol' ); ?></h2>
				<?php
				lcda_nav_menu(
					'lcda-footer',
					array(
						'menu_id'    => 'lcda-footer-menu',
						'menu_class' => 'lcda-footer__list lcda-footer__list--nav',
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<?php if ( has_nav_menu( 'lcda-contact' ) ) : ?>
			<div class="lcda-footer__col lcda-footer__contact">
				<h2 class="lcda-footer__title"><?php esc_html_e( 'Visitanos', 'la-casa-del-arbol' ); ?></h2>
				<address class="lcda-footer__address">
					<?php
					lcda_nav_menu(
						'lcda-contact',
						array(
							'menu_id'    => 'lcda-contact-menu',
							'menu_class' => 'lcda-footer__list lcda-footer__list--contact',
						)
					);
					?>
				</address>
			</div>
		<?php endif; ?>

		<div class="lcda-footer__col lcda-footer__newsletter">
			<h2 class="lcda-footer__title"><?php esc_html_e( 'Sumate', 'la-casa-del-arbol' ); ?></h2>
			<p class="lcda-footer__lead"><?php esc_html_e( 'Enterate primero de la agenda del mes.', 'la-casa-del-arbol' ); ?></p>
			<div class="lcda-newsletter-form lcda-newsletter-form--footer">
				<label class="screen-reader-text" for="lcda-footer-email"><?php esc_html_e( 'Tu email', 'la-casa-del-arbol' ); ?></label>
				<input id="lcda-footer-email" class="lcda-newsletter-form__input" type="email" autocomplete="email" placeholder="<?php esc_attr_e( 'tu@email.com', 'la-casa-del-arbol' ); ?>">
				<button type="button" class="lcda-newsletter-form__button" aria-disabled="true" aria-describedby="lcda-footer-newsletter-status"><?php esc_html_e( 'OK', 'la-casa-del-arbol' ); ?></button>
				<span id="lcda-footer-newsletter-status" class="screen-reader-text"><?php esc_html_e( 'La suscripción estará disponible próximamente.', 'la-casa-del-arbol' ); ?></span>
			</div>
		</div>
	</div>

	<div class="lcda-footer__legal">
		<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
		<span><?php esc_html_e( 'Palermo, Buenos Aires', 'la-casa-del-arbol' ); ?></span>
	</div>
</footer>

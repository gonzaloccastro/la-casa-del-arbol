<?php
/**
 * Mobile navigation dialog (Handoff §1.7).
 *
 * Full-screen red overlay opened by the header "Menú" button. A native
 * <dialog> shown with showModal() (assets/js/mobile-menu.js) gives focus
 * containment, Esc to close and an inert page behind it.
 *
 * @package LaCasaDelArbol
 *
 * @var array $args { @type array|null $cta WhatsApp link from the lcda-cta location. }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lcda_cta = isset( $args['cta'] ) ? $args['cta'] : null;
?>
<dialog id="lcda-mobile-menu" class="lcda-mobile-menu" aria-label="<?php esc_attr_e( 'Menú', 'la-casa-del-arbol' ); ?>">
	<div class="lcda-mobile-menu__top">
		<?php
		echo lcda_get_logo( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file, attributes escaped.
			'symbol',
			array(
				'class'       => 'lcda-mobile-menu__logo',
				'aria-hidden' => 'true',
				'focusable'   => 'false',
			)
		);
		?>
		<button type="button" class="lcda-mobile-menu__close" data-lcda-menu-close>
			<?php esc_html_e( 'Cerrar', 'la-casa-del-arbol' ); ?> <span aria-hidden="true">✕</span>
		</button>
	</div>

	<?php if ( has_nav_menu( 'lcda-primary' ) ) : ?>
		<nav class="lcda-mobile-menu__nav" aria-label="<?php esc_attr_e( 'Principal', 'la-casa-del-arbol' ); ?>">
			<?php
			lcda_nav_menu(
				'lcda-primary',
				array(
					'menu_id'    => 'lcda-mobile-menu-list',
					'menu_class' => 'lcda-mobile-nav',
				)
			);
			?>
		</nav>
	<?php endif; ?>

	<?php if ( $lcda_cta ) : ?>
		<a class="lcda-mobile-menu__cta" <?php echo lcda_link_attributes( $lcda_cta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>><?php esc_html_e( 'Escribinos por WhatsApp', 'la-casa-del-arbol' ); ?></a>
	<?php endif; ?>
</dialog>

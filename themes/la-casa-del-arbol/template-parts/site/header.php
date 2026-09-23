<?php
/**
 * Site header (Handoff §1.6).
 *
 * Desktop (>=768px): logo symbol · primary nav · WhatsApp CTA.
 * Mobile (<=767px): logo symbol · "Menú" button that opens the mobile dialog.
 * Both layouts share this markup; CSS switches between them.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lcda_cta = lcda_get_location_link( 'lcda-cta' );
?>
<header class="lcda-header">
	<div class="lcda-header__inner lcda-container">
		<a class="lcda-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			echo lcda_get_logo( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file, attributes escaped.
				'symbol',
				array(
					'class'       => 'lcda-header__logo-mark',
					'aria-hidden' => 'true',
					'focusable'   => 'false',
				)
			);
			?>
			<span class="screen-reader-text"><?php bloginfo( 'name' ); ?></span>
		</a>

		<?php if ( has_nav_menu( 'lcda-primary' ) ) : ?>
			<nav class="lcda-header__nav" aria-label="<?php esc_attr_e( 'Principal', 'la-casa-del-arbol' ); ?>">
				<?php
				lcda_nav_menu(
					'lcda-primary',
					array(
						'menu_id'    => 'lcda-header-menu',
						'menu_class' => 'lcda-nav',
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<?php if ( $lcda_cta ) : ?>
			<a class="lcda-header__cta" <?php echo lcda_link_attributes( $lcda_cta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>><?php esc_html_e( 'WhatsApp', 'la-casa-del-arbol' ); ?></a>
		<?php endif; ?>

		<button type="button" class="lcda-header__toggle" aria-haspopup="dialog" aria-expanded="false" aria-controls="lcda-mobile-menu" data-lcda-menu-open>
			<span><?php esc_html_e( 'Menú', 'la-casa-del-arbol' ); ?></span>
			<span class="lcda-header__toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>
		</button>
	</div>
</header>
<?php
get_template_part( 'template-parts/site/mobile-menu', null, array( 'cta' => $lcda_cta ) );

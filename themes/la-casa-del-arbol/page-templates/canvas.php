<?php
/**
 * Template Name: La Casa — Lienzo
 * Template Post Type: page
 *
 * Full-bleed canvas: no Astra title, sidebar or content width. The page is
 * built entirely from Gutenberg blocks and patterns; each section controls
 * its own width and gutters. Used by Home, Agenda and the demo event page.
 *
 * @package LaCasaDelArbol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main lcda-canvas">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<div id="post-<?php the_ID(); ?>" <?php post_class( 'lcda-canvas__content' ); ?>>
			<?php the_content(); ?>
		</div>
		<?php
	endwhile;
	?>
</main>

<?php
get_footer();

<?php
/**
 * Single Event (/evento/{slug}/): the approved Single Event design
 * rendered from the casa-eventos Event (single-event-contract.md).
 *
 * Same page frame as the Lienzo template (page-templates/canvas.php), and
 * lcda_is_canvas() covers event singles, so the page gets the La Casa
 * header and footer, the full-width layout and no Astra title, featured
 * image or sidebar. Astra's single-post loop (navigation, comments, author
 * box) is never called. If casa-eventos is unavailable the post's content
 * is printed as is (the post type is normally unregistered then, so this
 * template is not reached).
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
		$lcda_event = lcda_events_available() ? \CasaEventos\Core\Event::get( get_post() ) : null;
		?>
		<div id="post-<?php the_ID(); ?>" <?php post_class( 'lcda-canvas__content' ); ?>>
			<?php
			if ( $lcda_event ) {
				get_template_part( 'template-parts/event/detail', null, array( 'event' => $lcda_event ) );
				get_template_part( 'template-parts/event/related', null, array( 'event' => $lcda_event ) );
			} else {
				the_content();
			}
			?>
		</div>
		<?php
	endwhile;
	?>
</main>

<?php
get_footer();

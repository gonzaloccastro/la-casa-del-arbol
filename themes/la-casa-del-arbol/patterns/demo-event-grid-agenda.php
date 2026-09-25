<?php
/**
 * Title: Grilla de eventos — Agenda (demo)
 * Slug: la-casa-del-arbol/demo-event-grid-agenda
 * Categories: lcda-demo
 * Keywords: evento, tarjeta, grilla, afiche, agenda, mosaico
 * Description: Contenido de demostración. Mosaico de la Agenda con 9 tarjetas: 3 columnas, 2 en tablet y una columna en mobile; cada tarjeta respeta la proporción de su afiche. Los eventos reales se van a cargar desde Eventos (casa-eventos) y este mosaico se reemplaza entero. Solo para revisar el diseño: en cada tarjeta elegí el afiche, reemplazá los textos y enlazá el título. El sello circular va solo si la entrada es "Entrada libre" o "A la gorra"; si no, borralo. Categoría amarilla: color de fondo Amarillo.
 * Viewport Width: 1200
 * Inserter: yes
 *
 * Demo content only (event-markup-contract.md, agenda-contract.md). The
 * Agenda full-page pattern includes this file, so the demo card markup
 * lives in one place. casa-eventos later outputs real events into the same
 * lcda-event-grid--agenda markup and this file is deleted.
 *
 * The 9 cards follow the design's sequence: category color, the stamp only
 * where the entrada is free, and one- or two-line subtitles, so the masonry
 * shows its rhythm even before posters are chosen. The text is placeholder
 * copy, not event data. Card titles are <h2>: they sit directly under the
 * Agenda <h1>.
 *
 * @package LaCasaDelArbol
 */

// Per card: yellow category tag, entrada stamp, two-line subtitle.
$lcda_demo_cards = array(
	array( false, false, true ),
	array( true, false, false ),
	array( true, false, false ),
	array( false, true, false ),
	array( true, false, false ),
	array( true, true, false ),
	array( true, false, false ),
	array( false, false, true ),
	array( false, true, true ),
);
?>
<!-- wp:group {"align":"wide","className":"lcda-event-grid lcda-event-grid--agenda"} -->
<div class="wp-block-group alignwide lcda-event-grid lcda-event-grid--agenda"><?php
// The PHP tags are placed so the output is exactly the block serialization
// (no stray whitespace between blocks).
foreach ( $lcda_demo_cards as $lcda_demo_i => list( $lcda_demo_yellow, $lcda_demo_stamp, $lcda_demo_long ) ) :
	echo $lcda_demo_i > 0 ? "

" : '';
	?><!-- wp:group {"tagName":"article","className":"lcda-event-card lcda-event-card--full"} -->
<article class="wp-block-group lcda-event-card lcda-event-card--full"><!-- wp:group {"className":"lcda-event-card__poster"} -->
<div class="wp-block-group lcda-event-card__poster"><!-- wp:image -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"className":"lcda-burst lcda-event-card__burst"} -->
<p class="lcda-burst lcda-event-card__burst">00</p>
<!-- /wp:paragraph --><?php if ( $lcda_demo_stamp ) : ?>


<!-- wp:paragraph {"className":"lcda-stamp lcda-event-card__stamp"} -->
<p class="lcda-stamp lcda-event-card__stamp">Entrada libre</p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:group {"className":"lcda-event-card__body"} -->
<div class="wp-block-group lcda-event-card__body"><!-- wp:group {"className":"lcda-event-card__header"} -->
<div class="wp-block-group lcda-event-card__header"><!-- wp:paragraph {"className":"lcda-event-card__meta"} -->
<p class="lcda-event-card__meta">Día · 00:00</p>
<!-- /wp:paragraph -->

<?php if ( $lcda_demo_yellow ) : ?>
<!-- wp:paragraph {"backgroundColor":"yellow","className":"lcda-tag"} -->
<p class="lcda-tag has-yellow-background-color has-background">Categoría</p>
<!-- /wp:paragraph --><?php else : ?>
<!-- wp:paragraph {"className":"lcda-tag"} -->
<p class="lcda-tag">Categoría</p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:heading {"className":"lcda-event-card__title"} -->
<h2 class="wp-block-heading lcda-event-card__title">Título del evento</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"lcda-event-card__subtitle"} -->
<p class="lcda-event-card__subtitle"><?php echo $lcda_demo_long ? 'Bajada del evento: una o dos líneas que presentan la propuesta de la fecha.' : 'Bajada del evento.'; ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"lcda-event-card__footer"} -->
<div class="wp-block-group lcda-event-card__footer"><!-- wp:paragraph {"className":"lcda-event-card__venue"} -->
<p class="lcda-event-card__venue">La Casa del Árbol</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"lcda-event-card__entrada"} -->
<p class="lcda-event-card__entrada"><?php echo $lcda_demo_stamp ? 'Entrada libre' : 'Entrada'; ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></article>
<!-- /wp:group --><?php endforeach; ?></div>
<!-- /wp:group -->

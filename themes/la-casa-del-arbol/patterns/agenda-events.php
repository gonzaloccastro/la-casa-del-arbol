<?php
/**
 * Title: Agenda — Mosaico de eventos
 * Slug: la-casa-del-arbol/agenda-events
 * Categories: lcda
 * Keywords: agenda, eventos, mosaico, afiches, tarjetas
 * Description: Sección del mosaico de eventos de la Agenda (3 columnas, 2 en tablet, una en mobile; cada afiche con su proporción). Las tarjetas se generan solas con los eventos del mes cargados en Eventos: el texto que se ve acá en el editor no se publica. Si el mes (o la categoría elegida) no tiene eventos, se muestra un aviso en lugar del mosaico.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * The lcda-event-grid--agenda group is a render slot (inc/agenda.php,
 * E2.2): at render time its content is replaced by the month's event cards
 * from casa-eventos, or by the empty state. The paragraph inside is an
 * editor-only note and is never published. Pages built from Agenda 0.5.0
 * still hold the 9 demo cards in this group; the slot replaces them too
 * (agenda-contract.md).
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-agenda-events"} -->
<section class="wp-block-group alignfull lcda-section lcda-agenda-events"><!-- wp:group {"className":"lcda-container"} -->
<div class="wp-block-group lcda-container"><!-- wp:group {"align":"wide","className":"lcda-event-grid lcda-event-grid--agenda"} -->
<div class="wp-block-group alignwide lcda-event-grid lcda-event-grid--agenda"><!-- wp:paragraph -->
<p>Los eventos del mes se cargan automáticamente desde Eventos. Este texto no se publica.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

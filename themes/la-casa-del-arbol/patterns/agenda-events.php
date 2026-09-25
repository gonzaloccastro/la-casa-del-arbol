<?php
/**
 * Title: Agenda — Mosaico de eventos
 * Slug: la-casa-del-arbol/agenda-events
 * Categories: lcda
 * Keywords: agenda, eventos, mosaico, afiches, tarjetas
 * Description: Sección del mosaico de eventos de la Agenda (3 columnas, 2 en tablet, una en mobile; cada afiche con su proporción). Las 9 tarjetas son contenido de demostración, no el lugar donde se cargan los eventos: cuando exista casa-eventos, los eventos se administran desde Eventos y este mosaico se reemplaza por la salida automática, sin cambiar la sección.
 * Viewport Width: 1400
 * Inserter: yes
 *
 * Presentational until casa-eventos exists: the cards are the demo grid
 * (patterns/demo-event-grid-agenda.php, included below so the demo markup
 * lives in one file). casa-eventos later outputs real events into the same
 * lcda-event-grid--agenda markup inside this section (agenda-contract.md).
 *
 * @package LaCasaDelArbol
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"lcda-section lcda-agenda-events"} -->
<section class="wp-block-group alignfull lcda-section lcda-agenda-events"><!-- wp:group {"className":"lcda-container"} -->
<div class="wp-block-group lcda-container"><?php require __DIR__ . '/demo-event-grid-agenda.php'; ?></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

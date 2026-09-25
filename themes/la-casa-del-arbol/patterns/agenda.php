<?php
/**
 * Title: Agenda (página completa)
 * Slug: la-casa-del-arbol/agenda
 * Categories: lcda
 * Keywords: agenda, eventos, mes, página, mosaico
 * Description: La Agenda aprobada completa: encabezado (volanta, mes y categorías) y el mosaico de eventos. Usar en la página Agenda con la plantilla "La Casa — Lienzo" (el header y el footer los pone la plantilla). El mes se edita acá. Las tarjetas son de demostración: los eventos reales se van a cargar desde Eventos (casa-eventos), que reemplaza el mosaico automáticamente.
 * Viewport Width: 1400
 * Block Types: core/post-content
 * Post Types: page
 * Inserter: yes
 *
 * Composition contract: docs/implementation/agenda-contract.md. The
 * sections are the section patterns themselves, included in the approved
 * order, so each section's markup lives in one file. "Block Types:
 * core/post-content" also offers this pattern when a new page is created.
 *
 * @package LaCasaDelArbol
 */

require __DIR__ . '/agenda-header.php';
require __DIR__ . '/agenda-events.php';

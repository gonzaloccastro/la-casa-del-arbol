<?php
/**
 * Title: Agenda (página completa)
 * Slug: la-casa-del-arbol/agenda
 * Categories: lcda
 * Keywords: agenda, eventos, mes, página, mosaico
 * Description: La Agenda completa: encabezado (volanta, mes, navegación entre meses y categorías) y el mosaico de eventos. Usar en la página Agenda (slug "agenda") con la plantilla "La Casa — Lienzo" (el header y el footer los pone la plantilla). El mes, las categorías y las tarjetas se generan solos con los eventos cargados en Eventos; en la página solo se edita la volanta.
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

<?php
/**
 * Title: Home (página completa)
 * Slug: la-casa-del-arbol/home
 * Categories: lcda
 * Keywords: home, inicio, portada, página de inicio
 * Description: La Home aprobada completa: carrusel de portada, Eventos destacados del mes, ¡Festejá en el Árbol!, Seguinos en Instagram y Newsletter. Usar en una página con la plantilla "La Casa — Lienzo" (el header y el footer los pone la plantilla). Después se edita cada sección como cualquier bloque; cada una también está disponible como patrón suelto.
 * Viewport Width: 1400
 * Block Types: core/post-content
 * Post Types: page
 * Inserter: yes
 *
 * Composition contract: docs/implementation/home-contract.md. The sections
 * are the section patterns themselves, included in the approved order, so
 * each section's markup lives in one file. "Block Types: core/post-content"
 * also offers this pattern when a new page is created.
 *
 * @package LaCasaDelArbol
 */

require __DIR__ . '/home-hero.php';
require __DIR__ . '/home-featured-events.php';
require __DIR__ . '/whatsapp-cta.php';
require __DIR__ . '/instagram.php';
require __DIR__ . '/newsletter.php';

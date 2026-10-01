# La Casa del Árbol — Astra Child Theme

Child theme de Astra para lacasadelarbol.org. Implementa el Design V1 aprobado
(`docs/design/` en el repositorio).

## Requisitos
- WordPress 6.5+
- Astra instalado como parent theme (verificado contra Astra 4.13.x)

## Instalación
1. Apariencia → Temas → Añadir nuevo → Subir tema.
2. Subir `la-casa-del-arbol.zip`.
3. Activar **La Casa del Árbol**.
4. Mantener Astra instalado.

## Estructura
- `functions.php` — solo carga los módulos de `inc/`.
- `inc/setup.php` — soportes del theme y estilos del editor.
- `inc/menus.php` — ubicaciones de menú del header/footer.
- `inc/template-tags.php` — helpers de presentación (logo, enlaces de menú).
- `inc/assets.php` — CSS/JS del front.
- `inc/astra.php` — integración con Astra (solo mediante filtros/acciones públicos de Astra).
- `inc/patterns.php` — categorías de patterns y variantes de estilo (botones, sticker, reel de Instagram).
- `inc/blocks.php` — comportamiento al renderizar: carga el script del carrusel solo donde hay carrusel; el botón de
  WhatsApp de la franja Festejá toma su destino del menú "La Casa — CTA WhatsApp". También los helpers de bloques que
  comparten la Agenda y la Home (`lcda_block_has_class()`, `lcda_replace_block_inner()`).
- `theme.json` — tokens: colores, tipografías, tamaños, escala de espaciados, anchos (lectura 760px, contenedor 1376px + márgenes = 1440px).
- `assets/css/base.css` — fundamentos visuales (tokens, resets de Astra, tipografía, botones).
- `assets/css/components.css` — componentes compartidos: encabezado de sección, estrella de fecha y sticker,
  sello, etiqueta de categoría, tarjeta de evento, grillas de eventos, afiche y metadata del evento.
- `assets/css/home.css` + `assets/js/hero-carousel.js` — secciones de la Home: carrusel de portada, franja
  Festejá, Instagram y newsletter. Contrato: `docs/implementation/home-contract.md`.
- `assets/css/agenda.css` — secciones de la Agenda (encabezado con el mes y mosaico de eventos). Los chips de
  categoría y el mosaico son componentes compartidos de `components.css`. Contrato: `docs/implementation/agenda-contract.md`.
- `assets/css/event.css` — secciones de la página de evento (principal y "También en la agenda"). El detalle
  (afiche, datos, descripción, botones) es componente compartido de `components.css`. Contrato:
  `docs/implementation/single-event-contract.md`.
- `assets/css/chrome.css` + `assets/js/mobile-menu.js` — header, menú mobile y footer.
- `assets/images/fixtures/` — **imágenes provisorias** (rectángulos grises rotulados) del carrusel y de Instagram
  hasta que haya fotos reales. Se borran cuando ninguna página las use (ver `home-contract.md`).
- `assets/images/logo-*.svg` — logo canónico (símbolo y completo), tomado sin cambios de
  `docs/design/Website Direction.dc.html`; solo el relleno pasa a `currentColor`.
- `template-parts/site/` — header, menú mobile (`<dialog>`) y footer.
- `single-casa_evento.php` + `template-parts/event/` (detalle, relacionados, tarjeta) + `inc/events.php`:
  - la página de cada evento (`/evento/…`), con los datos reales de `casa-eventos` (E2.1);
  - el theme solo usa la API pública del plugin (`Event`, `Queries`) y decide markup y textos;
  - estados, botón y relacionados los decide el plugin;
  - ver `docs/implementation/single-event-contract.md`.
- `inc/agenda.php` + `template-parts/agenda/` (navegación, chips, tarjetas, aviso vacío):
  - la Agenda dinámica (E2.2): el mes, la navegación entre meses, los chips y las tarjetas salen de `casa-eventos`;
  - se completan al mostrar la página, sobre los bloques de los patrones de la Agenda (también en páginas armadas
    con la versión 0.5.0);
  - `?mes=AAAA-MM` y `?categoria=slug`; ver `docs/implementation/agenda-contract.md` "Dynamic Agenda".
- `inc/home.php` — Eventos destacados de la Home (E2.3):
  - hasta 4 tarjetas con los próximos eventos destacados de `casa-eventos` (`Queries::featured_events()`), con la
    tarjeta compartida (variante destacada, fecha "Sáb 03/10 · 21:00");
  - se completan al mostrar la página, sobre la grilla `lcda-event-grid--featured` (también en Homes armadas con 0.4.x);
  - sin eventos destacados próximos, o sin `casa-eventos`, la sección entera no se muestra;
  - ver `docs/implementation/home-contract.md` "Eventos destacados: dynamic section".
- `page-templates/canvas.php` — plantilla **La Casa — Lienzo** (full-bleed, sin título ni sidebar).
- `patterns/` — patterns de Gutenberg. Los patrones de demostración de eventos (`demo-*`) se borraron en E2.4: los
  eventos salen de `casa-eventos`.

## Uso en WordPress
- Páginas diseñadas (Home, Agenda, páginas informativas): Atributos de página → Plantilla → **La Casa — Lienzo**.
- **Home**: página con plantilla Lienzo + patrón **La Casa del Árbol → Home (página completa)** (también se ofrece al
  crear una página nueva). Después: reemplazar las fotos del carrusel (seleccionar imagen → Reemplazar, y escribir
  el texto alternativo) y las imágenes de Instagram (estilo "Reel de Instagram" para los reels). Las tarjetas de
  Eventos destacados se arman solas con los eventos marcados como destacados en **Eventos**. Una Home armada antes
  de E2.3 solo necesita un cambio de texto, una vez: volanta "Septiembre 2026" → "Próximas fechas" y título
  "Eventos destacados del mes" → "Eventos destacados". Ajustes → Lectura → página de inicio estática = Home. Pasos completos en
  `docs/implementation/home-contract.md`.
- **Agenda**: página `agenda` con plantilla Lienzo + patrón **La Casa del Árbol → Agenda (página completa)**.
  El mes, la navegación entre meses, las categorías y las tarjetas se arman solos con los eventos de **Eventos**
  (`casa-eventos`); en la página solo se edita la volanta. Una página armada con la Agenda 0.5.0 (mes escrito a mano,
  categorías fijas, 9 tarjetas de demostración) no hace falta rehacerla: se reemplaza igual al mostrarla.
  Detalle: `docs/implementation/agenda-contract.md`.
- **Página de evento**: cada evento se carga en **Eventos** (`casa-eventos`) y su página (`/evento/…`) se arma sola con el
  diseño aprobado.
  - El botón es "Reservar" (WhatsApp: wa.me/5491140385603 con el mensaje "Hola! Me interesaba la actividad {título del evento}", en pestaña nueva) o "Comprar entradas" (venta externa).
  - Un evento pausado, cancelado o pasado muestra un aviso en lugar del botón.
  - No hay "Compartir".
  - El patrón de demostración "Evento (página de demostración)" se borró en E2.4. Si en un sitio quedó una página de
    prueba armada con él, se borra a mano (ya no recibe los estilos de la página de evento).
- Botones: estilo por defecto = Primario rojo. Variantes en la barra lateral del bloque: Oscuro, Contorno, Enlace de texto.
  En mobile los botones pasan a ancho completo; agregar la clase `lcda-inline` para mantener uno en línea.
- Armado de páginas (plantilla Lienzo): la página se compone con **secciones**. Cada sección trae el ancho,
  los márgenes laterales y el ritmo vertical del diseño; no hace falta ajustar márgenes a mano. Contrato
  completo: `docs/implementation/layout-contract.md`.
- Patterns (Insertar → Patrones):
  - **La Casa del Árbol → Sección**: franja de ancho completo con encabezado; reemplazar el párrafo por el
    contenido (tarjetas, botones, texto). Color de fondo opcional.
  - **La Casa del Árbol → Sección de texto**: igual, con texto a ancho de lectura (páginas informativas).
  - **La Casa del Árbol → Encabezado de sección**: volanta + título + acción opcional (borrar el bloque
    Botones si no hace falta). Ocupa el ancho del contenedor.
- Espaciados: el editor ofrece solo la escala del diseño (XS 8 · S 16 · M 24 · L 32 · XL 48 · 2XL 64;
  L, XL y 2XL se achican en mobile). No hay valores libres en píxeles.
- Secciones de la Home, también sueltas en **La Casa del Árbol**: Carrusel de portada · Eventos destacados ·
  ¡Festejá en el Árbol! (WhatsApp) · Seguinos en Instagram · Newsletter (Comunidad). El newsletter todavía no
  envía nada (botón desactivado); Instagram son imágenes fijas, sin conexión con Instagram.
- Secciones de la Agenda, también sueltas en **La Casa del Árbol**: Agenda — Encabezado · Agenda — Mosaico de eventos.
- Párrafo → estilo **Sticker (estrella)**: estrella menta con un texto corto (por ejemplo "A la gorra").
- Los afiches se muestran completos, con su proporción original (nunca se recortan).

## Menús (Apariencia → Menús)
El theme solo registra ubicaciones: no crea páginas, menús ni ítems. Una ubicación sin
menú asignado no muestra nada.

| Ubicación | Uso |
|---|---|
| La Casa — Navegación principal | Header desktop y menú mobile |
| La Casa — Footer: Navegación | Columna "Navegación" del footer |
| La Casa — Footer: Visitanos | Columna "Visitanos": dirección, teléfono, Instagram (enlaces personalizados). Si el menú asignado no tiene ítems, la columna muestra solo el título: cargar los ítems en Apariencia → Menús |
| La Casa — CTA WhatsApp | Solo se usa el **primer ítem**: su URL es el destino del botón WhatsApp (header, menú mobile y franja ¡Festejá en el Árbol!) |

Los textos visibles del CTA ("WhatsApp" en el header, "Escribinos por WhatsApp" en el menú
mobile) son textos de interfaz del theme; del menú solo se toma el destino (URL, pestaña nueva).

El botón "volver arriba" es el de Astra (se activa en Personalizar → Scroll to Top); el theme solo le da el
estilo del diseño (cuadrado tinta con flecha papel, 44px).

El header, el menú mobile y el footer V1 se muestran en páginas con la plantilla
**La Casa — Lienzo**; el resto de las páginas mantiene el header/footer de Astra por ahora.

## Tipografías
V1 usa Anton 400, Archivo 800/900, Jost 600 y Work Sans 400, auto-alojadas en
`assets/fonts/` (WOFF2, subset latin) y declaradas como `fontFace` en `theme.json`.
WordPress imprime los `@font-face`; no hay pedidos a Google Fonts.

| Archivo | Familia / pesos | Fuente de descarga | Licencia |
|---|---|---|---|
| `anton/anton-400-latin.woff2` | Anton 400 | Google Fonts (fonts.gstatic.com, `anton/v27`) | SIL OFL 1.1 — `anton/OFL.txt` |
| `archivo/archivo-800-900-latin.woff2` | Archivo 800–900 (un archivo variable, eje wght) | Google Fonts (fonts.gstatic.com, `archivo/v25`) | SIL OFL 1.1 — `archivo/OFL.txt` |
| `jost/jost-600-latin.woff2` | Jost 600 | Google Fonts (fonts.gstatic.com, `jost/v20`) | SIL OFL 1.1 — `jost/OFL.txt` |
| `work-sans/work-sans-400-latin.woff2` | Work Sans 400 | Google Fonts (fonts.gstatic.com, `worksans/v24`) | SIL OFL 1.1 — `work-sans/OFL.txt` |

- Archivos tal cual los distribuye Google Fonts, sin modificar.
- Cada `OFL.txt` es el texto de licencia del repositorio oficial `google/fonts` (`ofl/<familia>/OFL.txt`).
  Ninguna de las cuatro declara Reserved Font Names.
- Autores: Anton — Vernon Adams; Archivo — Omnibus-Type; Jost — Owen Earl (indestructible type*);
  Work Sans — Wei Huang.
- No usar GT Walsheim (tipografía comercial de los documentos institucionales): no forma parte de V1.

## Filosofía
- Gutenberg para contenido editorial.
- `theme.json`, patterns y CSS para consistencia.
- Sin lógica de eventos/ticketing en el theme: eso vive en el plugin `casa-eventos`. El theme presenta los eventos con la API pública del plugin, sin leer metadatos ni hacer consultas de eventos (lo controla la regla 9 de `plugins/casa-eventos/tests/check-architecture.php`).
  Contrato de markup compartido: `docs/implementation/event-markup-contract.md`.
- WooCommerce y Payway se integrarán después.
- Datos que no son del theme: eventos (entidad Evento de `casa-eventos`, única fuente), feed de Instagram (plugin
  externo) y newsletter (proveedor externo). Ver `docs/implementation/content-ownership.md`.

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
- `inc/patterns.php` — categorías de patterns y variantes de estilo de botones.
- `theme.json` — tokens: colores, tipografías, tamaños, espaciados, ancho 1440px.
- `assets/css/base.css` — fundamentos visuales (tokens, resets de Astra, tipografía, botones).
- `assets/css/chrome.css` + `assets/js/mobile-menu.js` — header, menú mobile y footer.
- `assets/images/logo-*.svg` — logo canónico (símbolo y completo), tomado sin cambios de
  `docs/design/Website Direction.dc.html`; solo el relleno pasa a `currentColor`.
- `template-parts/site/` — header, menú mobile (`<dialog>`) y footer.
- `page-templates/canvas.php` — plantilla **La Casa — Lienzo** (full-bleed, sin título ni sidebar).
- `patterns/` — patterns de Gutenberg.

## Uso en WordPress
- Páginas diseñadas (Home, Agenda, eventos de demo): Atributos de página → Plantilla → **La Casa — Lienzo**.
- Botones: estilo por defecto = Primario rojo. Variantes en la barra lateral del bloque: Oscuro, Contorno, Enlace de texto.
  En mobile los botones pasan a ancho completo; agregar la clase `lcda-inline` para mantener uno en línea.

## Menús (Apariencia → Menús)
El theme solo registra ubicaciones: no crea páginas, menús ni ítems. Una ubicación sin
menú asignado no muestra nada.

| Ubicación | Uso |
|---|---|
| La Casa — Navegación principal | Header desktop y menú mobile |
| La Casa — Footer: Navegación | Columna "Navegación" del footer |
| La Casa — Footer: Visitanos | Columna "Visitanos": dirección, teléfono, Instagram (enlaces personalizados) |
| La Casa — CTA WhatsApp | Solo se usa el **primer ítem**: su URL es el destino del botón WhatsApp |

Los textos visibles del CTA ("WhatsApp" en el header, "Escribinos por WhatsApp" en el menú
mobile) son textos de interfaz del theme; del menú solo se toma el destino (URL, pestaña nueva).

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
- Sin lógica de eventos/ticketing en el theme: eso vive en el plugin `casa-eventos`.
  Contrato de markup compartido: `docs/implementation/event-markup-contract.md`.
- WooCommerce y Payway se integrarán después.

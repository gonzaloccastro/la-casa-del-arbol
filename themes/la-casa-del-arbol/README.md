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
- `inc/assets.php` — CSS/JS del front.
- `inc/astra.php` — integración con Astra (solo mediante filtros/acciones públicos de Astra).
- `inc/patterns.php` — categorías de patterns y variantes de estilo de botones.
- `theme.json` — tokens: colores, tipografías, tamaños, espaciados, ancho 1440px.
- `assets/css/base.css` — fundamentos visuales (tokens, resets de Astra, tipografía, botones).
- `page-templates/canvas.php` — plantilla **La Casa — Lienzo** (full-bleed, sin título ni sidebar).
- `patterns/` — patterns de Gutenberg.

## Uso en WordPress
- Páginas diseñadas (Home, Agenda, eventos de demo): Atributos de página → Plantilla → **La Casa — Lienzo**.
- Botones: estilo por defecto = Primario rojo. Variantes en la barra lateral del bloque: Oscuro, Contorno, Enlace de texto.
  En mobile los botones pasan a ancho completo; agregar la clase `lcda-inline` para mantener uno en línea.

## Tipografías
V1 usa Anton 400, Archivo 800/900, Jost 600 y Work Sans 400 (todas SIL Open Font License).
Por ahora **no se incluyen archivos de fuentes**: `theme.json` declara las familias con
stacks de fallback. Cuando se aprueben los archivos, se agregan como `fontFace` en
`theme.json` sin cambiar el resto de la arquitectura.

## Filosofía
- Gutenberg para contenido editorial.
- `theme.json`, patterns y CSS para consistencia.
- Sin lógica de eventos/ticketing en el theme: eso vive en el plugin `casa-eventos`.
  Contrato de markup compartido: `docs/implementation/event-markup-contract.md`.
- WooCommerce y Payway se integrarán después.

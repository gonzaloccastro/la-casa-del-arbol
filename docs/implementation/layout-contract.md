# Layout contract (widths, gutters, spacing, sections)

**Status:** v1, Step 3.1. CSS in `themes/la-casa-del-arbol/assets/css/base.css` (tokens, Lienzo column, `lcda-section`, `lcda-container`, `lcda-reading`) and `theme.json` (layout sizes, spacing presets).
**Design source:** `docs/design/Final Design Handoff.md` §1.3 (spacing), §1.4 (gutters and widths), §1.21 (breakpoints).

Editors compose pages from sections and patterns. Widths, gutters and vertical rhythm come from these rules, not from per-block margins.

## Widths

| Concept | Value | Where it comes from | How to get it |
|---|---|---|---|
| Full bleed | viewport width | §1.4 (bands, hero, footer) | `alignfull`, or any `lcda-section` |
| Container | 1440px including 32px gutters (16px on mobile), so a **1376px content column** | §1.4 | `lcda-container` inside a section; `alignwide` directly on the page |
| Reading measure | 760px | theme.json `contentSize` (Step 1, for undesigned text pages) | plain blocks directly on the page; `lcda-reading` inside a container |

- `alignwide` and `lcda-container` always produce the **same column**. So a section heading, an event grid and the content of every section share one left edge at every width.
- theme.json `wideSize` is **1376px**, the container's content column, so the block editor shows wide blocks at the front-end width.
- Posters keep their own ratio inside whatever column holds them. The layout never crops or re-sizes artwork beyond the column width.

## Gutters

| Breakpoint | Gutter |
|---|---|
| desktop ≥ 1024 | 32px |
| tablet 768–1023 | 32px (desktop layout, §1.21) |
| mobile ≤ 767 | 16px |

The token is `--lcda-gutter`. Mobile card scrollers bleed into the gutter so the row scrolls to the viewport edges (§1.4), both inside a section and directly on the page.

## Spacing scale

theme.json spacing presets. The **same variables** are used by the component CSS, so the editor and the front end share one scale. L, XL and 2XL step down at the mobile breakpoint, as the approved screens do.

| Preset (slug) | Desktop / tablet | Mobile | Design use |
|---|---|---|---|
| XS (10) | 8px | 8px | small internal gaps |
| S (20) | 16px | 16px | |
| M (30) | 24px | 24px | card grid gap; block gap between stacked blocks |
| L (40) | 32px | 18px | section heading → content |
| XL (50) | 48px | 32px | Agenda header top, Related top |
| 2XL (60) | 64px | 40px | content section bottom |

- CSS tokens: `--lcda-space-10` … `--lcda-space-60`. Presets: `var(--wp--preset--spacing--N)`.
- The editor offers **only** these presets (`customSpacingSize: false`): no free pixel values.
- The root block gap (space between stacked blocks inside a group) is preset M.

## Sections

`lcda-section` is a full-bleed band that ends with the 2px ink rule and carries the vertical rhythm. It contains an `lcda-container`.

| Class | Padding desktop | Padding mobile | Design use (§1.3) |
|---|---|---|---|
| `lcda-section` | 64 / 64 | 36 / 40 | content sections (Destacados, Instagram, text sections) |
| `lcda-section lcda-section--band` | 56 / 56 | 40 / 40 | colored bands (Festejá, newsletter) |
| `lcda-section lcda-section--flush` | 0 | 0 | sections whose content sets its own spacing (hero carousel) |

- Side padding is always 0: the container provides the gutter, also when the section has a background color. Core's padding for colored groups is overridden.
- Consecutive sections touch; there is no margin between them.
- Page-specific values (Agenda header 48/32 · 32/24, Agenda grid 40/64 · 28/40, Event main 28/64 · 20/40, Related 48/64 · 32/40) belong to the Step 5–6 patterns. They are set there with the presets above or with a modifier added then.
- An editor can still override a section's padding from the block sidebar, but only with presets.

## Top level of a Lienzo page

| Block | Result |
|---|---|
| plain block (paragraph, list, image…) | reading column, centered |
| `alignwide` (section heading pattern, grid patterns) | container column |
| `alignfull` / `lcda-section` | full bleed |

Components set only `margin-block`, never `margin-inline`, so they never undo this centering. That was the cause of the section heading touching the viewport edge before Step 3.1. The block editor root gets the same column rules (editor-only selectors).

## Patterns (category "La Casa del Árbol")

| Pattern | Structure | Use |
|---|---|---|
| Sección | `section.lcda-section.alignfull` > `.lcda-container` > section heading + content | any designed section; replace the paragraph with grids, buttons… |
| Sección de texto | same + `.lcda-reading` with paragraphs | informative pages (Reservas, Alquilá, La Casa, Contacto) |
| Encabezado de sección | `alignwide` section heading | inside a section, or directly on a page (container column) |

The demo event grids (category "Demo") are `alignwide` grids with the right card variant: `lcda-event-grid--featured`, `--agenda`, `--related`. See `event-markup-contract.md`.

## Rules

1. New sections are built as `lcda-section` > `lcda-container`, never as groups with hand-set padding or margins.
2. Components never set `margin-inline` or the `margin` shorthand on their outer element.
3. New spacing values come from the scale. A value that isn't on it needs a design reason, documented next to the rule.

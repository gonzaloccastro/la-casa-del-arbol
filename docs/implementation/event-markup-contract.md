# Event markup contract (theme ↔ casa-eventos)

**Status:** v1.1, E2 complete (theme 0.7.0 with casa-eventos 0.2.0, 2026-09-29): Single, Agenda and Home render real Events through one shared card; E2.4 deleted the demo patterns and moved the render-slot helper to `inc/blocks.php`. E2.3 (v1.0, 2026-09-29): the Home "Eventos destacados" renders real featured events (render slots in `inc/home.php`, the shared card, featured variant); the section hides itself with no eligible events. E2.2 (v0.9, 2026-09-28): the Agenda renders real events (render slots in `inc/agenda.php`, the shared card). E2.1 (v0.8): the theme renders real events; the Single Event is live (`single-casa_evento.php` + `template-parts/event/`). Before that: v0.7. Step 3 (shared components) fixed the inner markup; Step 3.1 added the layout contract (`layout-contract.md`) and replaced the single demo cards with demo grids; Step 4 uses the featured demo grid inside the Home section (`home-contract.md`); 0.4.1 recorded the data-ownership decisions (`content-ownership.md`); Step 5 (0.5.0) built the Agenda on the agenda demo grid and the filter chips (`agenda-contract.md`); Step 6 (0.6.0) built the Single Event detail (`single-event-contract.md`, which holds the full page markup). The CSS lives in `themes/la-casa-del-arbol/assets/css/components.css`; button classes are in `base.css`.
**Visual source of truth:** `docs/design/Final Design Handoff.md` §1.8–1.15, §2.2, §3, §4.

## Why this exists

- The **theme** owns presentation: markup shape, class names, CSS, responsive behavior.
- The **casa-eventos** plugin owns the event domain: CPT, fields, WooCommerce mapping, ticket/access modes, status and visibility, eligibility, queries, URLs, and whether a call to action exists (`Event::cta()`).

**Rendering responsibility (closed, E2):** the **theme renders** the event views from the plugin's documented public API, and the plugin supplies data and rules.
- The theme may call only `CasaEventos\Core\Event` (read model), `CasaEventos\Core\Queries` (selections) and `CasaEventos\Core\POST_TYPE` (template conditions).
- The theme ships `single-casa_evento.php` (E2.1) and later the Agenda/Home integration (E2.2 / E2.3).
- The theme owns markup, classes, formatting and wording. The WhatsApp destination comes from its CTA menu location.

The theme never:
- registers event CPTs, meta or taxonomies;
- reads `_casa_*` meta or any event meta directly;
- runs event `WP_Query` / `get_posts`;
- names the schema strings;
- re-derives an Event Core rule (finished, sales cutoff, CTA availability, eligibility).

The plugin's architecture check (`plugins/casa-eventos/tests/check-architecture.php`, rule 9) enforces this. It fails on:
- registration calls;
- `_casa_` keys;
- direct meta access;
- hard-coded `'casa_evento'` / `'casa_categoria'`;
- non-public plugin symbols;
- event queries;
- calls to rule-level read-model methods (sales close, GMT/effective end, `is_finished`, `is_actionable`, `capacity`, `validation`, `external_url`, `access_mode`, `status`).

During the visual phase, Agenda, Single Event and the Home "destacados" cards were **static demo content** built from core Gutenberg blocks in `lcda-demo` patterns. The real views render **into the same classes**, so the visual layer was not rebuilt. The demo patterns were deleted in E2.4; pages that still store demo cards (Home 0.4.x, Agenda 0.5.0) are taken over at render time ("Structural class contracts" below).

## Rules both sides follow

1. **Style by `lcda-*` classes only.** Theme CSS never targets `wp-block-*` internals of event components, so core-block demo markup and plugin PHP markup are styled identically. (The one exception is an editor-only rule that disables the stretched card link inside the block editor.)
2. **Element-agnostic where possible.** A class may sit on a `div` (core group block) or a semantic element (plugin). Required semantics are listed per component.
3. **Presentation rules stay in CSS, not data:**
   - Date-burst color alternates by position inside an `lcda-event-grid`: 1st card mint, 2nd yellow, 3rd mint… (`:nth-child`). The data carries no color for it.
   - The Single Event hero burst is always mint.
   - The poster ratio comes from the image's intrinsic `width`/`height` attributes. The poster is always shown complete: no crop, no recolor, no overlay other than burst and stamp. A poster frame without an image keeps a 4:5 placeholder.
4. **One poster asset per event**, reused in every context.
5. **No inline styles** for components.

## Shared primitives (not event-specific)

| Class | Element | Notes |
|---|---|---|
| `lcda-section-heading` | block | Flex row: `__text` (eyebrow + title) left, `__action` right, baseline-aligned. Mobile: stacked. |
| `lcda-section-heading__text` | block | Holds `lcda-eyebrow` + `lcda-section-heading__title`. |
| `lcda-section-heading__title` | `<h2>` (usually) | Size comes from the heading level (base.css) or a font-size preset. |
| `lcda-section-heading__action` | block | Optional. Usually one text-link button. |
| `lcda-eyebrow` (+ `--on-red`) | `<p>` | Already in base.css. |
| `lcda-btn` + `--dark` / `--outline` / `--text` | `<a>` or `<button>` | Template/plugin buttons, identical to the core/button block styles (default = Primary red). |
| `lcda-back-link` | `<a>`, or a block that contains the link | "← Volver a la agenda". |
| `lcda-hide-on-mobile` | any | Hidden ≤ 767px (e.g. "Ver agenda completa →" on the mobile Home). |
| `lcda-burst` (+ `--mint` / `--yellow`) | any | Star with the day of the month. Size from the context. |
| `lcda-sticker` / paragraph style "Sticker (estrella)" (`is-style-lcda-sticker`) | `<p>` | Star with a short label ("A la gorra"). `lcda-sticker--corner` places it over the top-right edge of an `lcda-sticker-host`. |
| `lcda-stamp` | any | Black rotated circle ("Entrada libre" / "A la gorra"). |
| `lcda-tag` + `--mint` (default) / `--yellow`, `--large` | any | Category label. In the editor, the block background color (Menta / Amarillo) also works. |

## Components and classes

### Containers

| Class | Context | Layout (desktop → mobile) |
|---|---|---|
| `lcda-event-grid lcda-event-grid--featured` | Home destacados | 4 cols → horizontal snap scroller (card 68%) |
| `lcda-event-grid lcda-event-grid--agenda` | Agenda | 3-col CSS-columns masonry → single-column feed |
| `lcda-event-grid lcda-event-grid--related` | Single Event "También en la agenda" | 3 cols → horizontal snap scroller (card 62%) |

Tablet (768–1023): featured 2 cols, agenda 2 cols, related 2 cols. The cards must be the grid's **direct children** (burst alternation and scroller sizing depend on it). A grid sits either inside an `lcda-container` or directly on a Lienzo page as `alignwide`; in both cases it takes the container column, and on mobile the scrollers bleed into the gutter (see `layout-contract.md`).

The masonry places cards top-to-bottom per column, so the visual order (columns) differs from the DOM order (chronological). Tab and screen-reader order follow the DOM. That is how the approved design works. Why CSS columns (and not grid masonry or JS) and how it behaves with 1–25 cards and mixed ratios: `agenda-contract.md`.

### Event card: `lcda-event-card`

Variants: `lcda-event-card--featured` · `lcda-event-card--full` · `lcda-event-card--compact`.

| Part (class) | featured | full | compact | Notes |
|---|---|---|---|---|
| `lcda-event-card` | ✓ | ✓ | ✓ | `<article>`. |
| `lcda-event-card__poster` | ✓ | ✓ | ✓ | Relative frame, `#e7e3d9` while loading/empty, 4:5 when there is no image. Holds the image, burst and stamp. |
| `lcda-event-card__image` | ✓ | ✓ | ✓ | `<img>` with real `width`/`height`, `alt=""` (the title follows), `loading="lazy"` below the fold. Not styled by class: any `img` in the poster is. `sizes`: the scroller cards' hint for featured/compact; the wall hint for full (E2.2: full gutter width on mobile, 2 then 3 columns). |
| `lcda-burst lcda-event-card__burst` | ✓ | ✓ | ✓ | Day of month, 2 digits. `aria-hidden="true"` (the date is also given as text). |
| `lcda-stamp lcda-event-card__stamp` | — | conditional | — | Only when entrada is "Entrada libre" or "A la gorra". `aria-hidden="true"` (duplicates entrada). |
| `lcda-event-card__body` | ✓ | ✓ | ✓ | |
| `lcda-event-card__header` | ✓ | ✓ | — | Row: meta left, tag right. Compact has the meta directly in the body. |
| `lcda-event-card__meta` | ✓ | ✓ | ✓ | "WEEKDAY · HH:MM" inside `<time datetime>` + visually hidden full date (`screen-reader-text`). Featured (Home, E2.3): "SÁB 03/10 · 21:00" (short weekday + dd/mm, because the Home spans months; `lcda_event_card_meta_html( $event, true )`); full and compact unchanged. |
| `lcda-tag` (+ `lcda-tag--yellow`) | ✓ | ✓ | — | Category label. |
| `lcda-tag lcda-tag--cancelled` | — | conditional | — | E2.2. "Cancelado" on a cancelled event's Agenda card: real text (not `aria-hidden`), red with ink text, in the header between the meta and the category tag (both pushed right). Never the poster stamp. |
| `lcda-event-card__title` | ✓ | ✓ | ✓ | Heading element. Level follows the page outline: `<h3>` under a section `<h2>` (Home, Related), `<h2>` directly under the Agenda `<h1>`. Contains the card's only link. |
| `lcda-event-card__link` | ✓ | ✓ | ✓ | The `<a>` inside the title. Stretched (`::after` covers the card), so the whole card is clickable. No other links inside the card. Any `<a>` inside the title gets the same treatment, so core headings with a plain link work too. |
| `lcda-event-card__subtitle` | — | ✓ | — | `<p>`. |
| `lcda-event-card__footer` | ✓ | ✓ | — | Above a 1px dashed rule. Featured: pinned to the card bottom (equal-height row). |
| `lcda-event-card__venue` | — | ✓ | — | "La Casa del Árbol". |
| `lcda-event-card__entrada` | ✓ | ✓ | — | Red text. |

Empty optional parts render nothing: leave them out of the markup (an empty element inside `__body` is hidden, but don't rely on it).

Keyboard focus: the focus ring is drawn around the whole card (`:has(:focus-visible)`), not only the title text.

Reference markup (full variant; drop the parts the other variants don't use):

```html
<article class="lcda-event-card lcda-event-card--full">
  <div class="lcda-event-card__poster">
    <img class="lcda-event-card__image" src="…" srcset="…" sizes="…" width="1080" height="1350" alt="" loading="lazy">
    <span class="lcda-burst lcda-event-card__burst" aria-hidden="true">04</span>
    <span class="lcda-stamp lcda-event-card__stamp" aria-hidden="true">Entrada libre</span>
  </div>
  <div class="lcda-event-card__body">
    <div class="lcda-event-card__header">
      <p class="lcda-event-card__meta">
        <time datetime="2026-09-04T21:00-03:00"><span aria-hidden="true">Viernes · 21:00</span><span class="screen-reader-text">Viernes 4 de septiembre, 21:00</span></time>
      </p>
      <p class="lcda-tag lcda-tag--yellow">Música en vivo</p>
    </div>
    <h2 class="lcda-event-card__title"><a class="lcda-event-card__link" href="…">Título del evento</a></h2>
    <p class="lcda-event-card__subtitle">Bajada.</p>
    <div class="lcda-event-card__footer">
      <span class="lcda-event-card__venue">La Casa del Árbol</span>
      <span class="lcda-event-card__entrada">Entrada libre</span>
    </div>
  </div>
</article>
```

Core-block equivalent (the deleted demo patterns; still stored in Home 0.4.x / Agenda 0.5.0 pages, where the render slots replace it): the same classes on core blocks: group (`tagName: article`), group, image, paragraphs, heading, group. Differences, accepted for demo content only: the burst and stamp can't carry `aria-hidden`, the meta has no `<time>` or hidden full date, and the image block puts no class on the `<img>`. WordPress adds `width`/`height` to Media Library images when it renders the page, so the native ratio still applies.

### Single Event: `lcda-event-detail`

| Class | Notes | Built in |
|---|---|---|
| `lcda-event-detail` | `<article>`. 2-col `.85fr 1.15fr`, gap 56 (desktop and tablet) → stacked, poster first, gap 24 | Step 6 |
| `lcda-event-detail__poster` | Native-ratio poster, 2px ink frame, 4:5 placeholder when empty | Step 3 |
| `lcda-burst lcda-event-detail__burst` | Always mint, breaks out of the poster corner (−14px; mobile −12px). Child of `__poster`. | Step 3 |
| `lcda-event-detail__info` | Tag, title, meta, description, secondary, [`lcda-ticket-types`], CTA row. Each part sets only its space above, so optional parts can be left out | Step 6 |
| `lcda-tag lcda-tag--large` + color modifier | | Step 3 |
| `lcda-event-detail__title` | The page's single `<h1>` | Step 6 |
| `lcda-event-meta` | Cuándo / Dónde / Entrada: equal columns → label/value rows | Step 3 |
| `lcda-event-meta__item`, `__label`, `__value` | `<dl>` > `<div class="…__item">` > `<dt class="…__label">` + `<dd class="…__value">` | Step 3 |
| `lcda-event-detail__description` | The Event's editorial block content (paragraphs, h2–h6, lists, links, emphasis). Work Sans 16.5/1.7, max 56ch | Step 6 |
| `lcda-event-detail__secondary` | Secondary paragraph (bajada), `#5a5a5a` | Step 6 |
| `lcda-event-cta` | CTA row: primary + "Compartir". Plugin: `<a class="lcda-btn">` (Comprar entradas / Reservar, same red style for both) + `<a\|button class="lcda-btn lcda-btn--outline">`. Editor: class on a Buttons block. Mobile: stacked, full width (primary 14px, Compartir 13px since Step 6). | Step 3 |
| `lcda-back-link` | "← Volver a la agenda" | Step 3 |
| `lcda-event-status` | E2.1. Non-interactive state note (`<p>`) in the CTA position when there is no action: "Evento cancelado", "Reservas pausadas", "Este evento ya pasó", "Entradas a la venta próximamente". Replaces the CTA row, never shown with it | E2.1 |
| `lcda-ticket-types` | **Reserved.** Not part of V1 visuals. Future ticket selector (ticket mode only), placed right before `lcda-event-cta` in the info column; spacing already defined, look not designed (`single-event-contract.md`) | — |

Metadata reference markup:

```html
<dl class="lcda-event-meta">
  <div class="lcda-event-meta__item"><dt class="lcda-event-meta__label">Cuándo</dt><dd class="lcda-event-meta__value"><time datetime="2026-09-04T21:00-03:00">Viernes 04/09 · 21:00hs</time></dd></div>
  <div class="lcda-event-meta__item"><dt class="lcda-event-meta__label">Dónde</dt><dd class="lcda-event-meta__value">Av. Córdoba 5217</dd></div>
  <div class="lcda-event-meta__item"><dt class="lcda-event-meta__label">Entrada</dt><dd class="lcda-event-meta__value">Por Passline</dd></div>
</dl>
```

Leave out an item whose value is missing. The remaining items share the width equally.

### Agenda header and filters

| Class | Notes |
|---|---|
| `lcda-agenda-header` | On the header `lcda-section`: eyebrow + month `<h1 class="lcda-agenda-header__title">` + month navigation + chips (Step 5; navigation E2.2) |
| `lcda-agenda-nav` | E2.2. `<nav aria-label="Meses de la agenda">` right after the H1: `<a class="lcda-agenda-nav__link lcda-agenda-nav__link--prev" rel="prev">← Mes</a>` and `…--next" rel="next">Mes →</a>`; a missing side is left out, no nav without either. Text-link style, secondary to the H1 |
| `lcda-agenda-events` | On the event-wall `lcda-section` that holds `lcda-event-grid--agenda` (Step 5) |
| `lcda-filter-chips` / `lcda-filter-chip` | `<ul>` / `<li>`. Since E2.2 each item holds a server-side link (`<li class="lcda-filter-chip is-active"><a href aria-current="page">Todos</a></li>`); the active chip has `is-active` and its link `aria-current="page"`; 44px hit area, focus ring. No list at all for a month without events. No JS filtering. See `agenda-contract.md` "Dynamic Agenda" |
| `lcda-agenda-empty` | E2.2. `<p>` printed instead of the grid: "No hay eventos programados para este mes." / "No hay eventos de esta categoría para este mes." |

## Source of truth and consumers

The **Event entity (casa-eventos) is the single source of truth** for every event field below. The theme's contexts are views of it, never separate copies:

| Consumer | Selection (implemented in casa-eventos, not the theme) | Card variant |
|---|---|---|
| Home "Eventos destacados" | `Queries::featured_events()`: published, listed, featured, active (neither paused nor cancelled), not finished, nearest first; not month-bound; `limit` 4 (E2.3, live). The whole section is omitted when the result is empty or casa-eventos is inactive | `--featured` |
| Agenda | `Queries::month_events()`: published, listed, local start in the selected month, including finished, paused and cancelled; chronological (E2.2, live). The theme only picks the selected category's cards (`Event::category()`) | `--full` |
| Single Event | the Event itself (`Event::get()`), rendered by `single-casa_evento.php` (E2.1, live) | detail components |
| "También en la agenda" | `Queries::related_events()`: other upcoming listed events, neither paused nor cancelled, nearest first, max 3 (E2.1, live) | `--compact` |

Editors never re-enter event data on the Home or Agenda pages. Details and the other integration boundaries (Instagram, newsletter) are in `content-ownership.md`.

### Card variants (one implementation)

`template-parts/event/card.php` is the only event card in the theme: `featured` (Home), `full` (Agenda), `compact` (Related). Differences are data-driven inside that file: the featured meta shows "Sáb 03/10 · 21:00", full and compact show "Sábado · 21:00"; only full shows the stamp, subtitle, venue and the "Cancelado" tag; compact shows no category tag and no entry footer. Every variant carries the ISO `<time datetime>` and the same screen-reader date ("Sábado 3 de octubre, 21:00"). The Single's Cuándo uses "Sábado 03/10 · 21:00hs" (with its declared end, if any).

## Structural class contracts (render slots)

The Home and Agenda stay ordinary Gutenberg pages. The theme fills these blocks at render time, recognized **only by their classes** (no page ID, slug or front-page check). Removing a class in the editor disconnects that slot; moving a block changes what it fills.

| Page | Block | Class | Filled with | Without casa-eventos |
|---|---|---|---|---|
| Agenda | core/heading | `lcda-agenda-header__title` | the selected month (text only) + `nav.lcda-agenda-nav` after it | authored text, no nav |
| Agenda | core/list | `lcda-filter-chips` | "Todos" + the month's categories (links) | nothing |
| Agenda | core/group | `lcda-event-grid--agenda` | `full` cards, or `p.lcda-agenda-empty` | nothing |
| Home | core/group | `lcda-event-grid--featured` | up to 4 `featured` cards | nothing |
| Home | core/group | `lcda-section` whose blocks contain the featured grid | kept as is; removed entirely with 0 eligible events | removed entirely |

- The slots keep each block's own element and attributes and replace only its inner HTML (`lcda_replace_block_inner()` in `inc/blocks.php`, shared by `inc/agenda.php` and `inc/home.php`).
- Whatever the page stores inside those blocks (editor-only notes, or the demo cards of Home 0.4.x / Agenda 0.5.0 pages) is never published. No pattern file is read at render time.
- The Agenda canonical (`?mes`) applies only to the viewed page that holds the Agenda heading or wall class.

## Data casa-eventos supplies per event (view data)

Derived from the handoff's design data model (§5):

| Field | Used for |
|---|---|
| start date/time (ISO) | burst day, meta weekday/time, Cuándo, screen-reader date, `<time datetime>` |
| title | card/detail title |
| subtitle | full card, detail secondary paragraph |
| description | detail body |
| category label + tag color (`mint` \| `yellow`) | tag |
| entrada label | card footer, Entrada meta |
| entrada libre / a la gorra (bool) | stamp visibility |
| poster (attachment: src, srcset, width, height, alt) | poster, all contexts |
| permalink | card link |
| CTA type + label ("Comprar entradas" \| "Reservar") + destination | primary CTA (same red style for both) |
| featured on Home (bool, e.g. `featured_on_home`) | Home selection, combined with "not past" |

Not needed from data: burst color (CSS), aspect ratio (intrinsic image size), venue text in V1 ("La Casa del Árbol" / "Av. Córdoba 5217").

## Demo content registry (deleted in E2.4)

Theme patterns that held static demo events. casa-eventos made them obsolete, and E2.4 deleted all four files and the "La Casa del Árbol — Demo" inserter category. Pages built from them keep their stored blocks: the Home and Agenda grids are replaced at render time; a test page built from the Single Event demo is deleted by hand.

| Pattern (category "La Casa del Árbol — Demo") | File (deleted) | Since |
|---|---|---|
| Grilla de eventos — Destacados (demo): 4 featured cards. No longer included by `home-featured-events.php` since E2.3 (the Home grid is a render slot); Home 0.4.x pages still store this markup and the slot replaces it | `patterns/demo-event-grid-featured.php` | Step 3.1 |
| Grilla de eventos — Agenda (demo): 9 full cards, `<h2>` titles (Step 5; 3 cards with `<h3>` before). Also included by `agenda-events.php` (Agenda) | `patterns/demo-event-grid-agenda.php` | Step 3.1 |
| Grilla de eventos — Relacionados (demo): 3 compact cards. Also included by `demo-event-page.php` | `patterns/demo-event-grid-related.php` | Step 3.1 |
| Evento (página de demostración): the whole Single Event screen for visual QA (main section + related section). Not a starter pattern: events are never authored as pages | `patterns/demo-event-page.php` | Step 6 |

They held placeholder text only ("Título del evento", "Día · 00:00", "00") and empty image slots. No event data and no artwork ship in the theme. Step 3's single-card demo patterns (`demo-event-card-*`) were removed in Step 3.1: a card inserted on its own landed in the reading column, which is not a layout the design uses. Cards already inserted from them keep working, because styling depends only on classes.

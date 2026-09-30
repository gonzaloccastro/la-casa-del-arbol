# Agenda contract (composition, event wall, filters, fixture boundary)

**Status:** v2.1, E2 complete (theme 0.7.0 with casa-eventos 0.2.0, 2026-09-29): dynamic Agenda since E2.2 (2026-09-28); the demo grid pattern was deleted in E2.4. v1 was Step 5 (theme 0.5.0), the static Agenda. Patterns in `themes/la-casa-del-arbol/patterns/` (`agenda.php`, `agenda-header.php`, `agenda-events.php`). Render slots: `inc/agenda.php` + `template-parts/agenda/` (`nav`, `chips`, `cards`, `empty`); cards: the shared `template-parts/event/card.php`. CSS: `assets/css/agenda.css` (page sections, month navigation, empty state) and `assets/css/components.css` §4 (tags, incl. "Cancelado"), §6 (masonry grid) and §10 (filter chips). No JavaScript.
**Design source:** `docs/design/Final Design Handoff.md` §3 (Agenda), §1.3, §1.8–1.15, §1.20–1.21; `Website Direction.dc.html` screens "02 Agenda" (desktop and mobile). Ownership: `content-ownership.md`. Markup: `event-markup-contract.md`. Layout: `layout-contract.md`. This document covers only what the implementation decides; it does not restate the handoff.

## Composition

Agenda is an ordinary WordPress page (slug `agenda`) with the **La Casa — Lienzo** template. Its content is Gutenberg blocks inserted from patterns. The theme renders the header and footer, and since E2.2 fills three of the page's blocks at render time from casa-eventos ("Dynamic Agenda" below). There is no archive template, no Agenda PHP template, no shortcode and no event query in the theme.

| # | Section | Pattern (slug `la-casa-del-arbol/…`) | Structure |
|---|---|---|---|
| — | Header | template | unchanged ("Agenda" active through the WordPress menu) |
| 1 | Agenda header | `agenda-header` | `section.lcda-section.lcda-agenda-header` > `.lcda-container` > `p.lcda-eyebrow` + `h1.lcda-agenda-header__title` + `ul.lcda-filter-chips` |
| 2 | Event wall | `agenda-events` | `section.lcda-section.lcda-agenda-events` > `.lcda-container` > `.lcda-event-grid.lcda-event-grid--agenda` > one `article.lcda-event-card--full` per event (or `p.lcda-agenda-empty` instead of the grid) |
| — | Footer | template | unchanged |

- **`agenda`** ("Agenda (página completa)") includes the two section files in order, so each section's markup exists once. Like the Home pattern, it declares `Block Types: core/post-content` + `Post Types: page`, so it is offered when a new page is created. Inserting it produces plain, fully editable blocks: the editor never assembles the composition by hand.
- `agenda-events` holds an empty `lcda-event-grid--agenda` group with one editor-only note ("Los eventos del mes se cargan automáticamente desde Eventos. Este texto no se publica."). The demo grid pattern (`demo-event-grid-agenda.php`) was deleted in E2.4. Pages that still store its 9 demo cards are taken over by the render slot ("Dynamic Agenda"), which never needs the pattern file.
- **Page outline:** one visible `<h1>` (the month), the month navigation (`<nav>`), then one `<h2>` per event card. The eyebrow is a paragraph. The event wall has no heading of its own, as in the design. Card titles are `<h2>` because they sit directly under the page `<h1>` (`event-markup-contract.md`); the demo Agenda grid was changed from `<h3>` to `<h2>` for this.

### Vertical rhythm

| Section | Class | Desktop / tablet | Mobile | Source of the values |
|---|---|---|---|---|
| Agenda header | `lcda-section lcda-agenda-header` | 48 / 32 | 32 / 24 | top: preset XL (48/32); bottom: preset L on desktop (32), preset M on mobile (24) |
| Event wall | `lcda-section lcda-agenda-events` | 40 / 64 | 28 / 40 | bottom: preset 2XL (64/40); top 40/28 is a design value not on the scale |

- The two classes only set `--lcda-section-space-top` / `--lcda-section-space-bottom`, the variables `lcda-section` already uses. No section sets its own padding, and an editor can still override padding from the block sidebar (presets only).
- Inside the header: eyebrow → H1 10px (mobile 8), H1 → chips 28px (mobile 22). These are the design's values; they are not on the scale and are commented in `agenda.css`.
- H1: Anton, the "Display" font-size preset `clamp(3rem, 7vw, 6rem)`, line-height .88; mobile 2.6rem, line-height .9.
- Both sections end with the 2px ink rule and touch each other. The first and last child of each container sit exactly on the section padding (tested at 11 widths).

## Event wall: layout strategy

**Chosen: CSS multi-column masonry** (`column-count` 3 / 2 / 1, `column-gap` 24px, each card `break-inside: avoid`, 24px between stacked cards, 22px on mobile). This is the handoff's specified technique (§3.2) and the Step 3 shared implementation (`components.css` §6). Step 5 did not need to change it; it was tested harder.

Why it is the robust choice for dynamic output:

- **Count-agnostic:** no `nth-child` placement, no row spans, no fixed number of items. Tested with 1, 2, 3, 8, 9, 20 and 25 cards: every column is used when there are enough cards, cards never split across columns, no overlap, columns balanced within one card height.
- **Native poster ratios without JavaScript:** a card's height is its poster's intrinsic ratio (the `<img>` `width`/`height`) plus its text. Tested with 4:5, 3:4, 1:1, 2:3, 16:9 and 1:2 posters mixed in one wall. Each poster stays complete (no crop) at the full card width.
- **No layout shift from images:** the ratio is known before the image loads (the width/height attributes), so lazy-loaded posters don't make columns rebalance.
- **Order stays meaningful:** the DOM order is chronological, and tab order and screen readers follow it. Visually, cards fill column 1 top to bottom, then column 2 (column-major), which is how the approved design behaves.
- **No trailing gap:** margins at column breaks are truncated by the browser, so the grid box ends at the lowest card. Measured 0px slack everywhere except one case: 2 cards in 2 tablet columns at 1023px, where column balancing leaves 3px. That is not visible.
- **Same classes for demo and plugin output.** Styling depends only on `lcda-*` classes, so the core-block demo cards and future casa-eventos PHP cards render identically. Both were mixed in the tests.

Rejected alternatives:

- **CSS Grid masonry** (`grid-template-rows: masonry` / grid-lanes): not supported across browsers yet. As a progressive enhancement it would give a different visual order in different browsers.
- **JavaScript masonry** (absolute positioning, or row spans computed per card): extra JS and resize/lazy-load recalculation for no visual gain, and it breaks the "minimal JS" rule.
- **Server-side distribution into N column wrappers:** fixes the column count in the markup, so the 3 → 2 → 1 responsive change would need duplicate markup or a DOM order that no longer matches the chronology.

Known trade-off: the column-major reading order. With many events, column 1 holds the first third of the month. That is the approved design; if the order must become row-major later, the fix is a different grid, not a change to the cards.

## Responsive behavior

| Range | Header | Chips | Event wall |
|---|---|---|---|
| ≥ 1024 | 48/32, H1 `clamp(3rem,7vw,6rem)` | wrap, gap 10, 12px / padding 8 16 | 3 columns, burst 54, stamp 58, title 19 |
| 768–1023 | desktop values | wrap | **2 columns** (handoff §1.21 fallback), desktop card sizes |
| ≤ 767 | 32/24, H1 2.6rem | one row, `nowrap`, gap 8, 11.5px / 8 14, scrolls sideways, bleeds to both viewport edges with the first chip on the 16px gutter, scrollbar hidden | 1-column feed at full gutter width, gap 22, burst 48, stamp 50, title 17 |

- Gutters and the content column are the layout contract's (32px; 16px on mobile; 1376px column). Header, chips, grid and footer share one left edge.
- The mobile chip row is a scroll container like the Home rail; only the row scrolls, never the page (no page-level horizontal overflow at any tested width). At phone widths a partly visible chip at the edge is the scroll cue.
- Tablet is the conservative derivation the handoff allows: desktop layout with the 2-column wall. No tablet-specific design was invented.

## Filters (v1 static record; superseded by "Dynamic Agenda")

- **Markup:** a core List block (`ul.wp-block-list.lcda-filter-chips`) with one List item per category (`li.lcda-filter-chip`); the active one adds `is-active` (yellow). V1 labels: Todos · Música en vivo · Cine · Recreación · Fiesta · Festival. "Todos" is active.
- **Semantics:** the chips are plain list items with text. They are not links, buttons, tabs or checkboxes. They have no `role`, `tabindex`, `aria-pressed` or `aria-current`, and they are not focusable. Assistive technology reads "list, 6 items" with the category names; nothing claims to filter. There are no touch-target requirements, because nothing is interactive.
- The earlier contract wording "Active chip gets `is-active` + `aria-current="true"`" is changed: in the static version, "current" would announce a filter state that doesn't exist, and a core List item can't carry the attribute anyway. The active state is visual only until there is real filtering.
- **No JavaScript, no query strings, no persisted state, no taxonomy.** Clicking a chip does nothing, by design.
- **Future integration boundary (casa-eventos):** the plugin replaces the whole `ul.lcda-filter-chips` with its own output in the same classes, with a real link inside each item:
  ```html
  <ul class="lcda-filter-chips">
    <li class="lcda-filter-chip is-active"><a href="…/agenda/" aria-current="page">Todos</a></li>
    <li class="lcda-filter-chip"><a href="…/agenda/?categoria=cine">Cine</a></li>
  </ul>
  ```
  The theme already styles that case (tested with a fixture): the link inherits the chip's look, its hit area is at least 44px tall without changing the look (Handoff §1.20), and keyboard focus draws a ring around the chip. Categories, URLs, the active state and filtering (server-side links or progressive enhancement) are the plugin's decision. The theme adds no filtering logic.

## Fixture boundary and editing model (v1 record; see "Dynamic Agenda")

Historical record of the 0.5.0 static Agenda. The demo grid pattern file was deleted in E2.4; the bullets below still describe the content that pages built from 0.5.0 store (and that the slots replace at render time).

- **The 9 cards are demonstration content, not the event editing interface.** The Event entity (future `casa-eventos`) is the single source of truth (`content-ownership.md`). Editors must not maintain events on the Agenda page. Nothing entered in the demo cards carries over to casa-eventos.
- The demo cards hold placeholder copy only ("Título del evento", "Día · 00:00", "00", "Categoría", "Entrada"), with empty poster slots that show the 4:5 placeholder. They follow the design's sequence of category colors, stamps (cards 4, 6 and 9) and one- or two-line subtitles, so the masonry rhythm is visible before posters exist.
- **No artwork ships with the Agenda.** None of the 9 V1 posters exist (Image Asset Manifest: category B, missing), and none were downloaded, generated or recreated. The existing theme fixtures (hero and Instagram SVGs) are not posters, so none were reused. The September flyers in `docs/references/agenda/` are reference material, not production assets.
- `demo-event-grid-agenda.php` (deleted in E2.4) generated the 9 cards with a small PHP loop over a presentation table (yellow tag / stamp / long subtitle per card). The output is exactly the block serialization, checked with Gutenberg's own validator. Inserted content is plain blocks, independent of the loop.

### casa-eventos replacement point (v1 plan; implemented in E2.2)

- **What is replaced:** the `.lcda-event-grid--agenda` group inside `section.lcda-agenda-events`, and the `ul.lcda-filter-chips` list inside the header. casa-eventos outputs current/upcoming published Events, chronological, as `article.lcda-event-card.lcda-event-card--full` cards into the same grid class (`event-markup-contract.md`: `<time datetime>`, `aria-hidden` burst/stamp, one stretched link per card, `<h2>` titles, poster `<img>` with real width/height).
- **What stays:** both sections, their rhythm, the eyebrow, the chip and card styling, and the masonry CSS. No visual redesign is needed.
- The month H1 is editorial copy until casa-eventos provides the month (see "Dynamic month behavior" below).
- Done in E2.4: `demo-event-grid-agenda.php` is deleted from the theme. A page that still stores the demo grid needs no edit: the slot replaces it at render time. Nothing is migrated from it.
- Empty state (no upcoming events), month grouping, paging for very long months, and the actual filtering are casa-eventos decisions and are not designed in V1.

### What editors can change now (wp-admin)

| Element | How |
|---|---|
| Eyebrow "Agenda cultural" | edit the paragraph |
| Month H1, month navigation, chips, cards | nothing to edit on the page: they come from Eventos (casa-eventos). The heading text and the list/grid content seen in the editor are fallbacks, replaced at render |
| Categories, their order and color | Eventos → Categorías (administrators) |
| Section spacing | block sidebar, presets only |

### What will be controlled through Events (casa-eventos)

Title, subtitle, date/time (burst day, meta, `<time>`), category and its color, entrada and the free-entry stamp, poster, permalink, visibility (published, upcoming), order, and the category filter list. See `event-markup-contract.md` "Data casa-eventos supplies".

## Accessibility decisions

- One `<h1>` (the month); card titles `<h2>`; no skipped levels.
- Cards: one link per card (the title), stretched over the whole card, so the link's accessible name is the event title; focus ring around the whole card. In the demo cards the title has no link until an editor adds one.
- Posters: `alt=""`, because the title follows and the poster repeats it. A poster with essential information not in the text needs a real alt (editor / plugin decision).
- Burst (day) and stamp duplicate text information. In plugin output they are `aria-hidden="true"`; core paragraph blocks can't carry that attribute. That is the known, accepted demo-content difference (`event-markup-contract.md`).
- Filters: honest non-interactive list (above). No false control semantics.
- No motion, no animation and no JavaScript on the Agenda. Nothing to adapt for reduced motion.
- Touch targets: the only interactive elements are the card links (the whole card) and the chrome, which is already approved. Future link chips get a 44px hit area.

## Dynamic month behavior (product decision, 2026-09-25; implemented in E2.2)

Recorded after the Agenda V1 live QA, for the casa-eventos implementation. The V1 page is unchanged.

- The Agenda shows **one month at a time**.
- The selected month is the page's large `<h1>` (today's `lcda-agenda-header__title`).
- In the dynamic Agenda, casa-eventos **selects the current month automatically**; editors no longer edit the month by hand.
- **Previous / next month navigation** may appear as secondary, visually subordinate information around or below the main month. It needs a small design treatment when it is built; it must not compete with the H1.
- Month navigation is useful but **not a V1 blocker**. If it adds unnecessary complexity during the casa-eventos implementation, automatic current-month rendering takes priority, and navigation comes later.
- Implication for the replacement boundary: in the dynamic version the H1 text is also plugin output, rendered into the same `lcda-agenda-header__title` element. The eyebrow stays editorial or template text.

## Deviations and assumptions

- **Order:** the handoff says "chronological by date within the month (V1 source order)", but the prototype data lists Torneo de Metegol (18) before Encuentros Flamencos (12). The demo cards carry no dates, so this doesn't show. The rule to implement is chronological (casa-eventos).
- **Poster ratios:** the handoff says all V1 posters are standardized to 4:5, while the older asset manifest lists mixed native ratios. The implementation supports any ratio (tested), so both are satisfied.
- **Chip scrollbar:** the prototype doesn't specify it. It is hidden on mobile, as the Home rail was after live QA (0.4.1).
- **`aria-current` on the active chip:** removed from the static version (see Filters).
- **Stamp positions in the demo** follow the prototype's free-entry events (Kitty, Sindicato del Cuero, Parri) after chronological sorting: cards 4, 6 and 9.

## Activation after deployment (wp-admin)

The theme never creates pages or changes settings.

1. **Pages → Agenda** (the existing page, slug `agenda`).
2. **Page → Template → La Casa — Lienzo.**
3. Replace the old content with **Patterns → La Casa del Árbol → Agenda (página completa)**. Save as draft or preview first if the page is live.
4. Edit the month. Leave the demo cards as placeholders, or put real posters and texts in them only for visual review. They are not the event data.
5. Preview at desktop and mobile widths, then publish.

## E2 decisions (closed 2026-09-28; implemented in E2.2)

Recorded in `casa-eventos-contract.md` §15:
- Empty month: "No hay eventos programados para este mes." Empty category: "No hay eventos de esta categoría para este mes." (N1)
- Cancelled cards show a visible "Cancelado" marker; paused and finished cards get no special treatment. (N2)
- H1: month only in the current year, month + year otherwise. (N5)
- Secondary month links "← Agosto" / "Octubre →" between the H1 and the chips. (N6)
- Chips: "Todos" + the categories represented in the selected month, in plugin chip order. (N9)
- Rendering: the theme renders from `Queries::month_events()` and the other public queries; no event query in the theme. `?mes=YYYY-MM` is also used by the Single Event back link (E2.1).
- E2.2 decisions (2026-09-28): U1 category-aware month navigation; U2 a valid selected category without events keeps its active chip; U3 no chip list for a month without events; U4 "Cancelado" as a red real-text tag in the card header (never the poster stamp); U5 month-aware canonical.

## Dynamic Agenda (E2.2)

### Render slots (`inc/agenda.php`)

The page keeps its blocks. Three of them, recognized only by their Agenda-specific classes, are filled at render time (`render_block_core/*` filters):

| Block | Class | Output |
|---|---|---|
| core/heading | `lcda-agenda-header__title` | the heading element and its attributes are kept; its text becomes the month label; the month navigation is printed right after it |
| core/list | `lcda-filter-chips` | the list element is kept; its items become the chips. Nothing when the month has no events |
| core/group | `lcda-event-grid--agenda` | the group element is kept; its content becomes the cards. `p.lcda-agenda-empty` instead of the whole group when there is nothing to show |

- **Existing 0.5.0 pages are taken over as they are:** the "Septiembre" heading, the six static chips and the 9 demo cards are replaced by the same slots; the page does not need to be rebuilt. New insertions of the patterns carry neutral fallbacks ("Agenda", one "Todos" item, the editor-only note).
- **casa-eventos inactive:** the chips and the grid render nothing (no demo card, no inert filter), the heading keeps its authored text, and there is no navigation or empty-state message. No fatal: every plugin call is guarded by `lcda_events_available()`.
- The request is read once per page view (`lcda_agenda_view()`): one unfiltered `Queries::month_events()`, `Queries::categories()` and two `Queries::adjacent_event_month()` lookups (`LIMIT 1` each), so exactly 3 Event queries per view whatever the number of cards or the category; no query per card (re-verified in the E2 final review).
- **Full-page cache:** the default view follows the current venue month and the events' live states. A full-page cache on the Agenda can keep showing the previous month after a month change, or stale states (a newly cancelled event without its tag), until it expires. Review the production cache TTL or bypass before release (`docs/deployment.md`, "E2 release checklist").

### Request and URLs

| URL | View |
|---|---|
| `/agenda/` | current venue month (`Queries::current_month()`), all categories |
| `/agenda/?mes=2026-10` | that month |
| `/agenda/?categoria=cine` | current month, that category |
| `/agenda/?mes=2026-10&categoria=cine` | both |

- `mes`: string only, `wp_unslash`, validated by `Queries::parse_month()`. Invalid, malformed, impossible (2026-13), array or out-of-range (1969-12, 9999-12) → the current month, HTTP 200, no redirect. A duplicated parameter follows PHP (the last value wins).
- `categoria`: string only, `sanitize_title( wp_unslash() )`, valid only when it equals the slug of a term in `Queries::categories()`. Otherwise it is ignored (Todos) and never propagated to links. Percent-encoded slugs (non-Latin names) work both encoded and decoded.
- Generated links: `?mes` only for a month other than the current one, then `?categoria`; built on the permalink of the page being rendered. The current month is never redirected: `/agenda/?mes={current}` renders the same view.
- **Canonical (U5):** on a page holding the Agenda heading or wall, the page permalink for the current month or an invalid `mes`; permalink + `?mes=YYYY-MM` for a valid other month. `categoria` is never part of it. No noindex on variants, no month URLs in the sitemap. Other pages and Event singles keep WordPress's canonical.

### Header

- H1: the month in the site locale; month only in the current year ("Septiembre"), month + year otherwise ("Diciembre 2025", "Enero 2027").
- Month navigation (`nav.lcda-agenda-nav`, `aria-label` "Meses de la agenda"): "← Agosto" (`rel="prev"`) and "Octubre →" (`rel="next"`), same year rule. Targets: `Queries::adjacent_event_month()`, i.e. the nearest months with listed events (empty months skipped, history reachable); with a valid selected category, the nearest months with events of that category, and the links keep `?categoria`. A missing side is left out; with neither side there is no nav.
- Navigation style: the back-link text style (UI font, 12px / 11.5px mobile, 800, uppercase, ink), 44px+ hit area, at both ends of the container. 14px under the H1 (12px mobile); the chips keep their 28px (22px mobile) below it.
- Chips: "Todos" + the categories of the month's events, in plugin chip order. A valid selected category without events this month is added, active, in its own position. A month without events has no chip list (not a lone "Todos"). Each chip is a server-side link; the active one has `is-active` and `aria-current="page"`. "Todos" drops `categoria`.

### Wall

- Cards: the shared `template-parts/event/card.php`, variant `full`, `<h2>` titles; DOM order = `month_events()` order (chronological); the masonry only places them. The selected category's cards are picked by `Event::category()`, the same value the card's tag shows.
- Posters: intrinsic width/height (native ratio, never cropped), `loading="lazy"`, a `sizes` hint for the wall (`calc(100vw - 32px)` on mobile, then 2 and 3 columns up to 443px). No poster → the 4:5 placeholder frame.
- States: active, paused and finished cards are ordinary cards; a cancelled card adds a red "Cancelado" tag (`p.lcda-tag.lcda-tag--cancelled`, real text, ink on red for AA contrast) in the card header between the date and the category tag. On narrow 2-column cards the category tag then wraps to a second line, as a long category name already does. The poster stamp remains for "Entrada libre" / "A la gorra" only. No card has a CTA: the title link leads to the Single, which carries the state note or the action.
- Empty states (in place of the grid, `p.lcda-agenda-empty`): "No hay eventos programados para este mes." (month without events; no chips) and "No hay eventos de esta categoría para este mes." (valid category without events in a month that has others; chips shown).

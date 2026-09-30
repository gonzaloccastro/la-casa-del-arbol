# Single Event contract (presentation for casa-eventos)

**Status:** v2.1, E2 complete (theme 0.7.0 with casa-eventos 0.2.0, 2026-09-29): real Events render through `single-casa_evento.php` since E2.1 (2026-09-28; see "Dynamic implementation (E2.1)" below); the demo patterns were deleted in E2.4. v1 was Step 6 (theme 0.6.0). CSS: `assets/css/components.css` §7 (detail layout, info column, description), §8 (metadata), §9 (CTA row, back link), §5–6 (compact cards, related grid), and `assets/css/event.css` (the two page sections). Demo fixture (0.6.0; deleted in E2.4): `patterns/demo-event-page.php` (+ `demo-event-grid-related.php`). No JavaScript and no PHP render filter; the template is `single-casa_evento.php` + `template-parts/event/`.
**Design source:** `docs/design/Final Design Handoff.md` §4 (Single Event), §1.8, §1.10, §1.12–1.16, §1.20–1.21; `Website Direction.dc.html` screens "03 Single Event" (desktop and mobile). Ownership: `content-ownership.md`. Card and metadata markup: `event-markup-contract.md`. Future business rules: `ticketing-v1-contract.md` (context only; nothing implemented).

This document is the **presentational contract** that casa-eventos will populate. The theme owns the markup shape, the classes and the CSS. The Event entity owns every value.

## Dynamic implementation (E2.1)

`/evento/{slug}/` renders the approved design from the casa-eventos Event. The sections below keep describing the markup; this section records how the live page fills it.

**Files (theme):**
- `single-casa_evento.php`: the same frame as the Lienzo template (`<main class="site-main lcda-canvas">` > `.lcda-canvas__content`).
- `template-parts/event/detail.php`: the main section.
- `template-parts/event/related.php`: "También en la agenda".
- `template-parts/event/card.php`: the event card, variants featured, full and compact; E2.1 uses compact.
- `inc/events.php`: formatting, wording, CTA wording and destination.
- `inc/astra.php`: `lcda_is_canvas()` also covers event singles.
- `inc/assets.php`: `event.css` is scoped.

**Plugin API used (only):** `Event::get()`, the read-model getters, `Event::cta()`, `Queries::related_events()`, `Queries::current_month()`, `POST_TYPE`.

**Chrome:**
- La Casa header and footer, full-width layout (`ast-page-builder-template`), body class `lcda-canvas-page`, chrome CSS and `mobile-menu.js`.
- No Astra title, featured image, sidebar, post meta, navigation, comments or author box. The template never calls Astra's loop.
- Posters are a bare `<img class="lcda-event-card__image">`. Astra shadows images outside a `<figure>` on single views (`.ast-article-single img:not(figure img)`), so `components.css` resets `box-shadow` for poster images.

| Element | Source → output |
|---|---|
| Back link | the published page with slug `agenda`. `/agenda/?mes=YYYY-MM` when the event's month (local start) is not the current venue month, else `/agenda/`. No Agenda page → the back link is left out (no invented URL). |
| Poster | the featured image via `wp_get_attachment_image( 'full' )`: intrinsic width/height, `sizes="(max-width: 767px) 100vw, 580px"`, `fetchpriority="high"`, no `loading`. Alt = the attachment's alt (empty is fine, never the title). No poster → no `<img>`, 4:5 placeholder frame. |
| Burst | local start day, `d` ("05"), `aria-hidden`. |
| Category | `Event::category()` name in `lcda-tag lcda-tag--large`, plus `lcda-tag--yellow` when the category color is yellow. No category → no tag. |
| H1 | the event title (the page's only `<h1>`). |
| Cuándo | Start only: `Sábado 05/09 · 21:00hs`. Declared end, same day: `Sábado 05/09 · 21:00 → 23:30hs`. Declared end on another day: `Viernes 30/10 · 21:00hs → Lunes 02/11 · 03:00hs`. Each instant is a `<time datetime>` with its ISO offset. The arrow is `aria-hidden`, with "hasta" for screen readers. The derived default end (start + 3 h) is a state rule and is never shown (decision N7). Formatting: `wp_date()` in the event timezone. |
| Dónde | `Event::venue_address()` ("Av. Córdoba 5217"). |
| Entrada | the entry label, if any. Else free → "Entrada libre", gorra → "A la gorra". Else (paid, or unspecified, without a label) the item is left out: no invented price and no admin wording ("Paga"). |
| Description | the Event's block content (`the_content()`), left out when empty. |
| Secondary | the subtitle (raw excerpt), left out when empty. |
| Primary CTA / state note | see "Primary CTA" below. |
| Compartir | **omitted** (decision N3: no share control until decided; no inert control). |
| `lcda-ticket-types` | not rendered (no ticketing). |
| Related | see "También en la agenda". |

**Plugin inactive:** the post type is unregistered, so event URLs are 404. Theme pages render normally, because every call is guarded by `lcda_events_available()`.

## Page composition

| # | Section | Classes | Contents |
|---|---|---|---|
| — | Header | template | unchanged |
| 1 | Event main | `section.lcda-section.lcda-event-main` > `.lcda-container` | back link, then `article.lcda-event-detail` (poster + info) |
| 2 | También en la agenda | `section.lcda-section.lcda-event-related` > `.lcda-container` | `lcda-section-heading` with an `<h2>`, then `lcda-event-grid--related` with 3 compact cards |
| — | Footer | template | unchanged |

### Reference markup (what casa-eventos renders)

```html
<section class="lcda-section lcda-event-main">
  <div class="lcda-container">
    <p class="lcda-back-link"><a href="/agenda/">← Volver a la agenda</a></p>
    <article class="lcda-event-detail">
      <div class="lcda-event-detail__poster">
        <img class="lcda-event-card__image" src="…" srcset="…" sizes="(max-width: 767px) 100vw, 580px"
             width="1080" height="1350" alt="" fetchpriority="high">
        <span class="lcda-burst lcda-event-detail__burst" aria-hidden="true">05</span>
      </div>
      <div class="lcda-event-detail__info">
        <p class="lcda-tag lcda-tag--large lcda-tag--yellow">Música en vivo</p>
        <h1 class="lcda-event-detail__title">Lucernaria + Juana Talks + Monte Splash</h1>
        <dl class="lcda-event-meta">
          <div class="lcda-event-meta__item"><dt class="lcda-event-meta__label">Cuándo</dt>
            <dd class="lcda-event-meta__value"><time datetime="2026-09-05T21:00-03:00">Sábado 05/09 · 21:00hs</time></dd></div>
          <div class="lcda-event-meta__item"><dt class="lcda-event-meta__label">Dónde</dt>
            <dd class="lcda-event-meta__value">Av. Córdoba 5217</dd></div>
          <div class="lcda-event-meta__item"><dt class="lcda-event-meta__label">Entrada</dt>
            <dd class="lcda-event-meta__value">Por Passline</dd></div>
        </dl>
        <div class="lcda-event-detail__description"><!-- the Event's editorial content --></div>
        <p class="lcda-event-detail__secondary">Una explosión de pop.</p>
        <!-- future, ticket mode only: <div class="lcda-ticket-types">…</div> -->
        <div class="lcda-event-cta">
          <a class="lcda-btn" href="…">Comprar entradas</a>
          <!-- Compartir: see "Share" -->
        </div>
      </div>
    </article>
  </div>
</section>
<section class="lcda-section lcda-event-related">
  <div class="lcda-container">
    <div class="lcda-section-heading"><div class="lcda-section-heading__text">
      <h2 class="lcda-section-heading__title">También en la agenda</h2></div></div>
    <div class="lcda-event-grid lcda-event-grid--related"><!-- 3 × article.lcda-event-card--compact --></div>
  </div>
</section>
```

Any part of the info column may be left out when its value is missing (tag, metadata item, description, secondary paragraph). Each part sets only the space above it, so nothing leaves a gap.

### Semantic structure

- **One `<h1>`: the event title** (`lcda-event-detail__title`). Headings inside the description start at `<h2>`. "También en la agenda" is an `<h2>`; related card titles are `<h3>`. Tested: exactly one H1 and no skipped levels.
- The event is an `<article>`. Metadata is a `<dl>` of `<div>` > `<dt>` + `<dd>` pairs. **Cuándo holds `<time datetime="…">`** with a full ISO 8601 date-time and offset (tested for validity in the contract fixture).
- The category is a `<p>` label (not a link in V1). The venue is text in its `<dd>`.
- The burst repeats the day that Cuándo already gives: `aria-hidden="true"`.
- Business data lives in the text and attributes only (`datetime`, `href`, `src`/`width`/`height`), never in CSS or presentation-only classes. The CTA mode is expressed by its label and destination, not by a styling class.

### Vertical rhythm

| Section | Desktop / tablet | Mobile | Values |
|---|---|---|---|
| Event main | 24 / 64 | 18 / 40 | top: the design's back-link row (preset M; preset L on mobile); bottom: preset 2XL |
| back link → detail | 28 | 20 | design values |
| También en la agenda | 48 / 64 | 32 / 40 | presets XL and 2XL |
| related heading → cards | 24 | 18 | preset M; preset L on mobile |

The design draws the back link in its own row above the section (24px padding) and the section 28px below it. Here the back link is the section's first child, so the rendered distances are identical. The layout contract's "Event main 28/64 · 20/40" now reads as "24 + back link + 28" (recorded in `layout-contract.md`).

Info column (design values): tag → title 18 / 14 · title → metadata 24 / 20 · metadata → description 28 / 24 · description → secondary 16 / 14 · → CTA row 32 / 26.

## Poster

- **Native ratio, never cropped.** The frame (`lcda-event-detail__poster`, 2px ink) has no fixed ratio; the poster fills its width and keeps its intrinsic ratio from the `<img>` `width`/`height`. Tested with 4:5, 3:4, 1:1, 2:3, 16:9 and 1:2: the image is complete, full width and not `object-fit` cropped.
- **No layout shift:** the plugin must output `width` and `height` (WordPress adds them to Media Library images; `wp_get_attachment_image()` does too). The poster is the page's main image: `fetchpriority="high"`, no `loading="lazy"`.
- **Empty or loading:** `#e7e3d9` fill; with no image at all the frame keeps a 4:5 placeholder.
- **Burst:** always mint (CSS), 74px (mobile 58), 14px (mobile 12) outside the frame's top-left corner, above the image.
- **Column width:** poster column = .85fr of the grid, about 561px at 1440 (full column on mobile). Suggested `sizes` for the plugin: `(max-width: 767px) 100vw, 580px`.
- **Very tall posters** (e.g. 1:2) produce a tall poster column; the info column stays at the top. That is the design's rule (no crop). Revisit only if real artwork makes it a problem.
- **Alt text:** posters usually repeat the title and date, which the H1 and Cuándo already give, so the default is `alt=""`. When the artwork carries information not in the text (line-up, a notice), the editor writes a real alt text in the Media Library and the plugin outputs it. The plugin never invents alt text from the title.

## Metadata

- Cuándo / Dónde / Entrada, exactly as `event-markup-contract.md` (shared component, Step 3). Desktop and tablet: equal columns between 2px rules with dashed dividers. Mobile: label/value rows, value right-aligned.
- Cuándo display format: `WEEKDAY DD/MM · HH:MMhs` inside `<time datetime>`. Dónde: the venue (V1: "Av. Córdoba 5217"). Entrada: the access text ("Por Passline", "$8.000", "A la gorra", "Inscripción previa"…).
- A missing item is left out; the others share the width.

## Primary CTA: COMPRAR / RESERVAR

The Event's access mode (ticketing contract §3 "Modalidad de acceso", §18) decides the label and destination. **Both use the same red Primary style**: the approved design shows no visual difference (Handoff §1.13).

| Mode | Label (approved design wording) | Destination | E2.1 (live) |
|---|---|---|---|
| Ticket sales (`tickets`) | **Comprar entradas** | future purchase flow: ticket type + quantity → Comprar → direct checkout → Payway | **no purchase control yet**: the note "Entradas a la venta próximamente" (decision N11) |
| Reservation (`whatsapp`) | **Reservar** | the site's WhatsApp CTA (menu location "La Casa — CTA WhatsApp", same source as the header) | live when the plugin allows it **and** the menu location has a link; otherwise nothing |
| External sale (`external`) | **Comprar entradas** | the event's external URL (Passline…) | live when the plugin allows it (a usable URL); otherwise nothing |

The decision comes from `Event::cta()` (casa-eventos). The theme never derives it.
- **No action** in these cases, with a non-interactive `<p class="lcda-event-status">` note in the CTA position (decision N2):
  - paused → "Reservas pausadas";
  - cancelled → "Evento cancelado";
  - finished → "Este evento ya pasó";
  - tickets before commerce → "Entradas a la venta próximamente".
- **No CTA and no note** when there is no usable target: an external event without a URL, a WhatsApp event without the menu link, or no access mode.
- Never a fake control: no `href="#"`, no `javascript:`, no disabled button.
- The own ticket sales cutoff applies only to `tickets` (future commerce), never to `whatsapp` or `external` (decision N10).

- The ticketing contract calls the modes `COMPRAR` / `RESERVAR`. The button text keeps the approved design's wording ("Comprar entradas", "Reservar"); it is shown uppercase by CSS.
- **Plugin markup:** `<a class="lcda-btn" href="…">` inside `.lcda-event-cta`. One primary CTA per page; its accessible name is its label. A `<button>` is also styled, if the purchase UI needs one.
- **Demo (0.6.0, deleted in E2.4):** core Button blocks **without a link**. An `<a>` without `href` is not focusable and is not announced as a link, so nothing pretends to work: no `#`, no JavaScript, no cart, no checkout, no WhatsApp link. Tested: the demo buttons have no `href` and are not in the tab order.
- **Ticket selector (future, ticket mode only):** reserved class `lcda-ticket-types`, placed in the info column **immediately before** `.lcda-event-cta` (32 / 26 above it; the CTA row then sits 16px below it). The approved V1 screen has no ticket selector, so none is designed or built. Its design, and the states the ticketing contract implies (sold out, sales closed, paused, cancelled), need a design decision before casa-eventos builds them. The theme will style them then.
- Layout: desktop row with a 14px gap; it wraps in a narrow column (the 768px tablet info column is 373px, where "Comprar entradas" + "Compartir" don't fit: they wrap with 14px between, as the handoff allows). Mobile: stacked, full width, primary first, 10px apart, primary 14px text, Compartir 13px (the mobile screen's sizes; shared rule in `components.css` §9).

## Share ("Compartir")

- The approved design shows an outline "Compartir" button next to the primary CTA. Its behavior is not defined (Handoff §6 lists "share behavior" as out of scope), and the V1 decision is no Web Share and no share plugin.
- **0.6.0 demo:** the button is presentation only: no link, no JavaScript, no third-party script, no tracking.
- **E2 (decision N3):** the live page **omits** "Compartir". There is no share mechanism and no inert control until a share decision exists.
- **Integration point:** the second item of `.lcda-event-cta` (outline style: core block style "Contorno" or `lcda-btn lcda-btn--outline`). When the behavior is decided, options are a plain link (for example a WhatsApp share URL with the event permalink), or a small native Web Share script with a copy-link fallback. Either needs an explicit product decision first. Until then the live page omits it (N3 above).

## Description (editorial content)

- `.lcda-event-detail__description` holds **normal Gutenberg content**: in casa-eventos it is the Event post's own block content (`the_content()` of the Event), edited in the block editor. It is not a single hard-coded field.
- Supported and tested: several paragraphs, `<h2>`–`<h6>` below the H1, bulleted and numbered lists, links, bold and italic, long URLs (they wrap), images/embeds (fit the column).
- Typography: Work Sans 16.5 / 1.7 (mobile 15 / 1.65), ink, measure 56ch. Blocks are 16px apart (mobile 14). Headings sit 28px below the previous block and 10px above the next. H2 is Anton 1.6rem (mobile 1.4rem), smaller than the H1 and the related H2. H3–H6 are Archivo 900 16px uppercase. Lists are indented 1.25em (Astra's 3em is replaced). Links are red and underlined (global link style).
- `.lcda-event-detail__secondary` is the short "bajada"/subtitle paragraph after the description (Work Sans 15 / 1.6, `#5a5a5a`; mobile 13.5 / 1.55): an Event field, not editorial blocks.

## También en la agenda (related events)

- Reuses the compact card and the related grid (shared components, Step 3): 3 columns (tablet 2), mobile scroller with 62% cards and edge bleed. Burst colors alternate mint/yellow by position. No new card CSS.
- Heading: "También en la agenda", Anton, the "Título chico" size (`clamp(1.6rem, 3vw, 2.2rem)`; mobile 1.4rem), 24px (mobile 18) above the cards. This is section-scoped in `event.css`, so the generic section heading is unchanged.
- **0.6.0 demo (deleted in E2.4):** 3 demo cards from `demo-event-grid-related.php` (placeholder copy, empty posters).
- **E2.1 (live):** `Queries::related_events( $event, [ 'limit' => 3 ] )`: other upcoming (not finished) listed events, neither paused nor cancelled, nearest first (decision N8). The theme does not filter again.
  - The cards are `template-parts/event/card.php` (compact variant): posters `loading="lazy"`, `<h3>` title with the stretched link, `<time>` meta "Sábado · 21:00" + a screen-reader full date, burst `aria-hidden`.
  - Poster caches are primed by the query (no N+1).
  - No related events → the whole section is omitted.
- **v1 plan (implemented by E2.1 above):** casa-eventos supplies the events (V1 design rule: other upcoming events, e.g. the next 3 excluding the current one; the exact rule is its decision) as `article.lcda-event-card--compact` cards with a stretched link, `<h3>` title and `<time>` meta. If there are no other upcoming events, it omits the whole section.

## Responsive behavior

| Range | Detail | Metadata | CTAs | Related |
|---|---|---|---|---|
| ≥ 1024 | 2 columns .85fr / 1.15fr, gap 56, tops aligned; burst 74 at −14 | 3 columns | one row, gap 14 | 3 columns |
| 768–1023 | same 2 columns (Handoff §1.21: "Event poster/info grid remains 2-col") | 3 columns (values wrap inside their cells) | row; wraps at the narrowest widths | 2 columns |
| ≤ 767 | stacked: poster full width, then info 24px below; burst 58 at −12 | 3 label/value rows | stacked full width, gap 10 | edge-bleed scroller, cards 62% |

Gutters and the content column follow the layout contract (32 / 16px, 1376px column). The title uses the global H1 size (`clamp(2.2rem, 4.6vw, 4rem)`, line-height .95; mobile 2.1rem / .98). No page-level horizontal overflow at any tested width (1600 → 320).

## Accessibility

- One H1 (the title), logical heading order, `<article>`, `<dl>` + `<time datetime>` in plugin output.
- CTAs: real links with their label as accessible name in plugin output; demo CTAs are not focusable because they do nothing. Visible keyboard focus (2px ink outline) on real links. Mobile CTAs are at least 44px tall (tested); the back link's hit area is at least 44px (invisible extension).
- Burst `aria-hidden`; poster `alt` rules above; the "←" of the back link is read as part of the link text (acceptable; the plugin may wrap it in `aria-hidden`).
- No animation, no autoplay, no JavaScript.
- The 0.6.0 demo page's core-block differences (metadata as `<div>`/`<p>`, Cuándo without `<time>`, burst not `aria-hidden`) are gone with the demo pattern (E2.4); the live page has none of them.

## WordPress editor model and fixture boundary

**Removed in E2.4 (0.6.0 record):**
- The pattern **"Evento (página de demostración)"** (category *La Casa del Árbol — Demo*) was intended for a single test page with the Lienzo template, never as a starter pattern (events are not authored as pages). The pattern file, the related demo grid and the Demo inserter category are deleted.
- Its contents were: placeholder copy ("Título del evento", "Categoría", "Día 00/00 · 00:00hs", "Lugar", "Entrada", "00"), an empty poster slot (4:5 placeholder), buttons without destinations, and the 3 related demo cards. No artwork was added, downloaded or generated. The back link points at the published `agenda` page when the pattern is inserted.

**The theme owns:** the markup shape and classes above, all CSS (`components.css`, `event.css`), the section headings' wording ("También en la agenda", "← Volver a la agenda"), and the future single-event template's layout.

**casa-eventos populates (E2.1, through its public API):** title, poster (with alt), category and its color, date/time (burst, Cuándo, `<time>`), venue, entrada text, description (the Event's block content), secondary paragraph, CTA mode/label/destination, the ticket selector (ticket mode), the related events, and the event URL. It also supplies Event states (cancelled, sold out…) when their presentation is designed.

**Normal Gutenberg editorial content:** only the description. In casa-eventos it is edited in the Event's own block editor, never on a page.

**What disappeared with casa-eventos (E2):**
- `patterns/demo-event-page.php` and the other `demo-*` patterns: deleted in E2.4;
- the demo-only differences listed under Accessibility;
- still a wp-admin task: a test page built from the demo pattern, if one exists on a site, is deleted by hand (nothing is migrated). Such a page no longer gets `event.css`.

**Integration notes (resolved in E2.1):**
- The Event single view gets the site chrome: `lcda_is_canvas()` covers event singles.
- The theme renders the view (`single-casa_evento.php`) from the plugin's public API (`event-markup-contract.md` "Rendering responsibility").
- `event.css` loads on event singles only (since E2.4; before, also on a page built from the demo pattern).

## Deviations and assumptions

- **Back link inside the main section** (design: separate row above it). The rendered distances are identical (24 → link → 28; mobile 18 → link → 20), and it avoids a loose block at the top level of the page.
- **Button wording:** "Comprar entradas" / "Reservar" (approved design) for the ticketing contract's `COMPRAR` / `RESERVAR`.
- **Demo metadata values** (0.6.0 demo, deleted in E2.4) were placeholders ("Lugar" instead of "Av. Córdoba 5217"), consistent with the other demo patterns (no event data in the theme).
- **Description headings and lists** are not in the approved screen (it shows one paragraph). Their styling (sizes, spacing, indent) is a restrained extension of the approved type system, needed so editorial content works.
- **Mobile CTA sizes** (14 / 13px): the approved mobile screen's values, added to the shared CTA row. Home and Agenda don't use `.lcda-event-cta`.
- **Tablet:** 2 columns as the handoff says. At 768–800px the info column is narrow (373–391px): metadata values wrap inside their cells and the CTA row wraps. Flagged for design review, as §1.21 asks, rather than improvised.

# Event markup contract (theme ↔ casa-eventos)

**Status:** v0.2. Step 3 (shared components) fixed the inner markup. The CSS lives in `themes/la-casa-del-arbol/assets/css/components.css`; button classes are in `base.css`.
**Visual source of truth:** `docs/design/Final Design Handoff.md` §1.8–1.15, §2.2, §3, §4.

## Why this exists

- The **theme** owns presentation: markup shape, class names, CSS, responsive behavior.
- The future **casa-eventos** plugin owns the event domain: CPT, fields, WooCommerce mapping, ticket/access modes, status and visibility, queries, URLs, and CTA destinations.

During the visual phase, Agenda, Single Event and the Home "destacados" cards are **static demo content** built from core Gutenberg blocks in `lcda-demo` patterns. Later, casa-eventos renders real events **into the same classes**. The theme CSS styles both outputs, so the visual layer is not rebuilt, and the only theme cleanup is removing the demo patterns.

The theme never registers event CPTs, meta, taxonomies or queries, and never ships `archive-*` / `single-*` event templates.

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

Tablet (768–1023): featured 2 cols, agenda 2 cols, related 2 cols. The cards must be the grid's **direct children** (burst alternation and scroller sizing depend on it). The mobile scrollers bleed into the side gutter, so the grid must sit inside a container that has the gutter (`lcda-container` or equivalent).

The masonry places cards top-to-bottom per column, so the visual order (columns) differs from the DOM order (chronological). Tab and screen-reader order follow the DOM. That is how the approved design works.

### Event card: `lcda-event-card`

Variants: `lcda-event-card--featured` · `lcda-event-card--full` · `lcda-event-card--compact`.

| Part (class) | featured | full | compact | Notes |
|---|---|---|---|---|
| `lcda-event-card` | ✓ | ✓ | ✓ | `<article>`. |
| `lcda-event-card__poster` | ✓ | ✓ | ✓ | Relative frame, `#e7e3d9` while loading/empty, 4:5 when there is no image. Holds the image, burst and stamp. |
| `lcda-event-card__image` | ✓ | ✓ | ✓ | `<img>` with real `width`/`height`, `alt=""` (the title follows), `loading="lazy"` below the fold. Not styled by class: any `img` in the poster is. |
| `lcda-burst lcda-event-card__burst` | ✓ | ✓ | ✓ | Day of month, 2 digits. `aria-hidden="true"` (the date is also given as text). |
| `lcda-stamp lcda-event-card__stamp` | — | conditional | — | Only when entrada is "Entrada libre" or "A la gorra". `aria-hidden="true"` (duplicates entrada). |
| `lcda-event-card__body` | ✓ | ✓ | ✓ | |
| `lcda-event-card__header` | ✓ | ✓ | — | Row: meta left, tag right. Compact has the meta directly in the body. |
| `lcda-event-card__meta` | ✓ | ✓ | ✓ | "WEEKDAY · HH:MM" inside `<time datetime>` + visually hidden full date (`screen-reader-text`). |
| `lcda-tag` (+ `lcda-tag--yellow`) | ✓ | ✓ | — | Category label. |
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

Core-block equivalent (demo patterns): the same classes on core blocks: group (`tagName: article`), group, image, paragraphs, heading, group. Differences, accepted for demo content only: the burst and stamp can't carry `aria-hidden`, the meta has no `<time>` or hidden full date, and the image block puts no class on the `<img>`. WordPress adds `width`/`height` to Media Library images when it renders the page, so the native ratio still applies.

### Single Event: `lcda-event-detail`

| Class | Notes | Built in |
|---|---|---|
| `lcda-event-detail` | 2-col `.85fr 1.15fr` → stacked (poster first) | Step 6 |
| `lcda-event-detail__poster` | Native-ratio poster, 2px ink frame, 4:5 placeholder when empty | Step 3 |
| `lcda-burst lcda-event-detail__burst` | Always mint, breaks out of the poster corner (−14px; mobile −12px). Child of `__poster`. | Step 3 |
| `lcda-event-detail__info` | | Step 6 |
| `lcda-tag lcda-tag--large` + color modifier | | Step 3 |
| `lcda-event-detail__title` | The page's single `<h1>` | Step 6 |
| `lcda-event-meta` | Cuándo / Dónde / Entrada: equal columns → label/value rows | Step 3 |
| `lcda-event-meta__item`, `__label`, `__value` | `<dl>` > `<div class="…__item">` > `<dt class="…__label">` + `<dd class="…__value">` | Step 3 |
| `lcda-event-detail__description` | Body paragraph, max 56ch | Step 6 |
| `lcda-event-detail__secondary` | Secondary paragraph | Step 6 |
| `lcda-event-cta` | CTA row: primary + "Compartir". Plugin: `<a class="lcda-btn">` (Comprar entradas / Reservar, same red style for both) + `<a\|button class="lcda-btn lcda-btn--outline">`. Editor: class on a Buttons block. Mobile: stacked, full width. | Step 3 |
| `lcda-back-link` | "← Volver a la agenda" | Step 3 |
| `lcda-ticket-types` | **Reserved.** Not part of V1 visuals; for future ticket-type display | — |

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
| `lcda-agenda-header` | Eyebrow + month `<h1>` + chips (Step 5) |
| `lcda-filter-chips` / `lcda-filter-chip` | V1: static presentation (Step 5). Active chip gets `is-active` + `aria-current="true"`. No JS filtering in the theme. casa-eventos may later turn chips into links to real category URLs |

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
| featured (bool) | Home selection |

Not needed from data: burst color (CSS), aspect ratio (intrinsic image size), venue text in V1 ("La Casa del Árbol" / "Av. Córdoba 5217").

## Demo content registry

Theme patterns that hold static demo events. casa-eventos makes them obsolete, and they are deleted from the theme then.

| Pattern (category "La Casa del Árbol — Demo") | File | Since |
|---|---|---|
| Tarjeta de evento — Destacada (demo) | `patterns/demo-event-card-featured.php` | Step 3 |
| Tarjeta de evento — Agenda (demo) | `patterns/demo-event-card-full.php` | Step 3 |
| Tarjeta de evento — Relacionada (demo) | `patterns/demo-event-card-compact.php` | Step 3 |

They hold placeholder text only ("Título del evento", "Día · 00:00", "00") and an empty image slot. No event data and no artwork ship in the theme.

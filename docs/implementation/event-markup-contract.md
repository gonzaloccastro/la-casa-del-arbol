# Event markup contract (theme ↔ casa-eventos)

**Status:** v0.1, draft written in Step 1 (Foundations). Class names are fixed here. Exact inner markup is finalized when the components are built (Step 3) and this file is updated then.
**Visual source of truth:** `docs/design/Final Design Handoff.md` §1.8–1.15, §3, §4.

## Why this exists

- The **theme** owns presentation: markup shape, class names, CSS, responsive behavior.
- The future **casa-eventos** plugin owns the event domain: CPT, fields, WooCommerce mapping, ticket/access modes, status and visibility, queries, URLs, and CTA destinations.

During the visual phase, Agenda, Single Event and the Home "destacados" cards are **static demo content** built from core Gutenberg blocks in `lcda-demo` patterns. Later, casa-eventos renders real events **into the same classes**. The theme CSS styles both outputs, so the visual layer is not rebuilt, and the only theme cleanup is removing the demo patterns.

The theme never registers event CPTs, meta, taxonomies or queries, and never ships `archive-*` / `single-*` event templates.

## Rules both sides follow

1. **Style by `lcda-*` classes only.** Theme CSS never targets `wp-block-*` internals of event components, so core-block demo markup and plugin PHP markup are styled identically.
2. **Element-agnostic where possible.** A class may sit on a `div` (core group block) or a semantic element (plugin). Required semantics are listed per component.
3. **Presentation rules stay in CSS, not data:**
   - Date-burst color alternates by position: odd children mint, even children yellow (`:nth-child`). The data carries no color for it.
   - The Single Event hero burst is always mint.
   - The poster ratio comes from the image's intrinsic `width`/`height` attributes. The poster is always shown complete: no crop, no recolor, no overlay other than burst and stamp.
4. **One poster asset per event**, reused in every context.

## Components and classes

### Containers

| Class | Context | Layout (desktop → mobile) |
|---|---|---|
| `lcda-event-grid lcda-event-grid--featured` | Home destacados | 4 cols → horizontal snap scroller (card 68%) |
| `lcda-event-grid lcda-event-grid--agenda` | Agenda | 3-col CSS-columns masonry → single-column feed |
| `lcda-event-grid lcda-event-grid--related` | Single Event "También en la agenda" | 3 cols → horizontal scroller (card 62%) |

Tablet (768–1023): featured 2 cols, agenda 2 cols, related 2 cols.

### Event card: `lcda-event-card`

Variants: `lcda-event-card--featured` · `lcda-event-card--full` · `lcda-event-card--compact`.

| Part (class) | featured | full | compact | Notes |
|---|---|---|---|---|
| `lcda-event-card__poster` | ✓ | ✓ | ✓ | Relative wrapper, `#e7e3d9` while loading/empty |
| `lcda-event-card__image` | ✓ | ✓ | ✓ | `<img>` with real `width`/`height`, meaningful `alt` or `alt=""` if the title repeats it |
| `lcda-burst lcda-event-card__burst` | ✓ | ✓ | ✓ | Day of month, 2 digits. `aria-hidden="true"` (the date is also given as text) |
| `lcda-stamp lcda-event-card__stamp` | — | conditional | — | Only when entrada is "Entrada libre" or "A la gorra". `aria-hidden="true"` (duplicates entrada) |
| `lcda-event-card__body` | ✓ | ✓ | ✓ | |
| `lcda-event-card__meta` | ✓ | ✓ | ✓ | "WEEKDAY · HH:MM" + visually hidden full date (`screen-reader-text`) |
| `lcda-tag lcda-tag--mint` / `--yellow` | ✓ | ✓ | — | Category label |
| `lcda-event-card__title` | ✓ | ✓ | ✓ | Heading element. Contains the card's only link |
| `lcda-event-card__link` | ✓ | ✓ | ✓ | Stretched link (`::after` covers the card), so the whole card is clickable. No other links inside the card |
| `lcda-event-card__subtitle` | — | ✓ | — | |
| `lcda-event-card__footer` | ✓ | ✓ | — | Above a 1px dashed rule |
| `lcda-event-card__venue` | — | ✓ | — | "La Casa del Árbol" |
| `lcda-event-card__entrada` | ✓ | ✓ | — | Red text |

### Single Event: `lcda-event-detail`

| Class | Notes |
|---|---|
| `lcda-event-detail` | 2-col `.85fr 1.15fr` → stacked (poster first) |
| `lcda-event-detail__poster` | Native-ratio poster, 2px ink border |
| `lcda-burst lcda-event-detail__burst` | Mint, breaks out of the poster corner |
| `lcda-event-detail__info` | |
| `lcda-tag lcda-tag--large` + color modifier | |
| `lcda-event-detail__title` | The page's single `<h1>` |
| `lcda-event-meta` | 3-col grid (Cuándo / Dónde / Entrada) → 3 rows |
| `lcda-event-meta__item`, `__label`, `__value` | Should be a `<dl>` in plugin output |
| `lcda-event-detail__description` | Body paragraph, max 56ch |
| `lcda-event-detail__secondary` | Secondary paragraph |
| `lcda-event-cta` | CTA row: primary + "Compartir" |
| `lcda-ticket-types` | **Reserved.** Not part of V1 visuals; for future ticket-type display |

### Agenda header and filters

| Class | Notes |
|---|---|
| `lcda-agenda-header` | Eyebrow + month `<h1>` + chips |
| `lcda-filter-chips` / `lcda-filter-chip` | V1: static presentation. Active chip gets `is-active` + `aria-current="true"`. No JS filtering in the theme. casa-eventos may later turn chips into links to real category URLs |

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

_None yet (Step 1). Filled in during Steps 4–6._

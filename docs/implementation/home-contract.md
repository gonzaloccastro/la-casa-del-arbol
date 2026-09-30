# Home contract (composition, carousel, assets, activation)

**Status:** v1.3, E2 complete (theme 0.7.0 with casa-eventos 0.2.0, 2026-09-29). Step 4 (theme 0.4.0), live-QA follow-up 0.4.1, dynamic Eventos destacados E2.3, demo grid pattern deleted in E2.4. Ownership and integration boundaries: `content-ownership.md`. Patterns in `themes/la-casa-del-arbol/patterns/`, CSS in `assets/css/home.css` (+ two primitives in `components.css`), script `assets/js/hero-carousel.js`, render-time behavior in `inc/blocks.php` and `inc/home.php` (E2.3).
**Design source:** `docs/design/Final Design Handoff.md` §2 (Home), §1.16–1.20; `Website Direction.dc.html` screens "01 Home" (desktop and mobile). This document covers only what the implementation decides; it does not restate the handoff.

## Composition

The Home is an ordinary WordPress page with the **La Casa — Lienzo** template. Its content is plain Gutenberg blocks inserted from patterns. The theme renders only the header and footer, and has no Home template, front-page PHP or hard-coded copy.

| # | Section | Pattern (slug `la-casa-del-arbol/…`) | Structure |
|---|---|---|---|
| — | Header | template (`template-parts/site/header.php`) | unchanged |
| 1 | Hero carousel | `home-hero` | `section.lcda-section--flush.lcda-hero` > hidden H1 + `.lcda-hero__track` > image blocks |
| 2 | Eventos destacados | `home-featured-events` | `lcda-section` > container > section heading + `lcda-event-grid--featured` render slot (E2.3) + `lcda-section-actions` |
| 3 | ¡Festejá en el Árbol! | `whatsapp-cta` | red `lcda-section--band.lcda-festeja` > container (`lcda-sticker-host`) > `lcda-section-heading--band` + corner sticker |
| 4 | Seguinos en Instagram | `instagram` | white `lcda-section.lcda-instagram` > container > section heading + `.lcda-instagram-grid` > 6 image blocks |
| 5 | Newsletter (Comunidad) | `newsletter` | `lcda-section--band.lcda-newsletter` > container > `lcda-section-heading--band` with a Custom HTML form |
| — | Footer | template | unchanged |

- **`home`** ("Home (página completa)") includes the five section files in this order, so each section's markup exists once. It is also offered when a new page is created (`Block Types: core/post-content`).
- There is no separate WhatsApp CTA before the footer. In the approved Home, the WhatsApp CTA *is* the Festejá band. The pre-V1 `whatsapp-cta` pattern was rebuilt as that band (same slug), so there is one WhatsApp CTA component, not two.
- The Home does not include "Una casa abierta al barrio" or "Áreas de la casa". Those belong to La Casa (institutional).
- Page outline: one visually hidden `<h1>` ("La Casa del Árbol", editable, inside the hero), one `<h2>` per section, and `<h3>` card titles. The approved design shows no visible Home title.

### Vertical rhythm

Derived only from the layout contract. No section sets its own padding.

| Section | Class | Desktop / tablet | Mobile |
|---|---|---|---|
| Hero | `lcda-section--flush` | 0 (height from the carousel) | 0 |
| Destacados, Instagram | `lcda-section` | 64 / 64 | 36 / 40 |
| Festejá, Newsletter | `lcda-section--band` | 56 / 56 | 40 / 40 |

Inside sections: heading → content = preset L (32 / 18); grid → closing action = `lcda-section-actions` (36 / 24, the design's value, §2.2); band heading ↔ action = preset L (32 / 18). The first and last child of every container sit exactly on the section padding. Sections touch, and each ends with the 2px rule. These are regression-tested at 10 widths.

### New shared primitives (components.css)

| Class | Use |
|---|---|
| `lcda-section-heading--band` | The heading is the band's whole content: text and action vertically centered, gap L, no margin below; mobile stacked. Used by Festejá and Newsletter. |
| `lcda-section-actions` | Action row that closes a section (e.g. "Ver toda la agenda"), 36 / 24 above it. |

## Hero carousel

- **Markup (Gutenberg-owned):** one image block per slide inside the `lcda-hero__track` group. The slide count comes from the markup, so editors add, remove, reorder or replace images with normal block tools. No filenames live in CSS or JS.
- **Layout:** full bleed on an ink background; height `clamp(640px, 82vh, 920px)` from 768px up, `clamp(420px, 62vh, 560px)` on mobile. Images are `object-fit: cover`, centered. No text, category or CTA overlays.
- **No-JS behavior:** the track is a horizontal CSS scroll-snap row. Every image stays reachable by swiping, trackpad or the visible scrollbar. There are no controls.
- **Script (`hero-carousel.js`, 6.6 KB unminified, no dependencies, deferred):** `inc/blocks.php` enqueues it only when a page renders a group with the class `lcda-hero`. It adds:
  - Previous/next buttons (‹ ›, paper .85 squares, 36px desktop and 40px mobile, hit area at least 44px). They loop.
  - One dot button per slide. The active dot is the long red bar with `aria-current="true"`; the others are short paper bars. Each dot is 44px tall.
  - `role="region"` with `aria-roledescription="carrusel"` and a label. Each slide is `role="group"` with the label "n de N".
  - A polite live status ("n de N") after each change.
  - ← / → keys while an arrow or dot has focus. Focus follows the dots.
  - Eager loading of the neighbouring images.
  - The class `is-ready`, which hides the scrollbar.
- **Swipe:** native scrolling, and the dots follow the scroll position.
- **Motion:** no autoplay (V1 decision, overriding the prototype's 6s auto-advance). Smooth scroll only without `prefers-reduced-motion`; with it, jumps are instant.
- **Strings:** interface strings are translatable in PHP (`inc/assets.php`, `window.lcdaHeroCarousel`). JS fallbacks are in Spanish.
- **Editor:** the slides show as a grid of numbered thumbnails, and the hidden H1 shows as a small label, so every image can be selected and replaced (editor-only selectors).
- **Alt text:** real photos need an alt that describes the photo. The fixtures use `alt=""`.

## Eventos destacados: dynamic section (E2.3)

- **The cards come from casa-eventos, never from the page.** `inc/home.php` fills the group with the class `lcda-event-grid--featured` at render time with `CasaEventos\Core\Queries::featured_events( array( 'limit' => 4 ) )`: published, listed, featured, not paused, not cancelled, not finished (a running event stays until its effective end), nearest start first, ID tiebreak. The theme applies no filter, sort or eligibility rule of its own. One query per request (memoized), with meta, term and poster caches primed by the plugin.
- **Card:** the shared `template-parts/event/card.php`, variant `featured`, `<h3>` title with the card's only link (to the Event single). Meta visual text "Sáb 03/10 · 21:00" (short weekday + dd/mm + time, event timezone; CSS uppercases it), `<time datetime>` ISO, full date for screen readers. Category tag (yellow when the category is yellow; omitted without category), poster or the 4:5 placeholder, entry text (label, else "Entrada libre" / "A la gorra", else no footer). No CTA, WhatsApp or external link.
- **Whole section hidden** when there are no eligible events, or casa-eventos is inactive: the `lcda-section` group whose blocks contain the featured grid is left out entirely (eyebrow, H2, both Agenda links, grid). The hero is then followed directly by Festejá; each section owns its own bottom rule, so no double/missing rule. Never demo cards, never the editor note, never an empty frame.
- **1–3 events:** the approved grid keeps its columns (4 desktop, 2 tablet); unused columns stay empty (decision S2). More than 4: the 4 nearest.
- **The grid is recognized by its class**, not by page ID or front-page status, so a draft Home preview behaves the same. The editor shows the authored content (static blocks), not live cards: in the current pattern that is one editor-only paragraph ("…Este texto no se publica."), never published.
- **Pages built from Home 0.4.x** keep their 4 demo cards stored in that group; the slot discards them and renders the real cards in the same wrapper element. Their stored heading copy is **not** rewritten (page content stays editorial): see the one-time activation edit below.
- **Limits:** the grid must stay inside its `lcda-section` group. A grid moved out of its section hides only itself (the heading can be orphaned); a grid inside a synced pattern is filled, but its section is not hidden with zero events; removing the `lcda-event-grid--featured` class disconnects the slot. A full-page cache on the Home freezes eligibility (paused/cancelled/finished events can linger, and a newly featured event stays missing, until it expires). Review the production cache TTL or bypass for the Home before release (`docs/deployment.md`, "E2 release checklist").
- `patterns/demo-event-grid-featured.php` was deleted in E2.4. Pages built from 0.4.x still store its markup; the slot replaces it at render time and never needs the file (verified: the legacy page renders byte-identically before and after the deletion).
- The "Ver agenda completa →" and "Ver toda la agenda" links point to the published page with the slug `agenda`. The URL is resolved when the pattern is inserted. If that page doesn't exist, they have no destination until an editor links them.

## Festejá band and WhatsApp

- The button has the class `lcda-whatsapp-link`. At render time, `inc/blocks.php` gives it the URL, target and rel of the first item in the **La Casa — CTA WhatsApp** menu location, the same source as the header CTA, through `WP_HTML_Tag_Processor`.
- If no menu is assigned, or the menu URL is not a valid URL, the button keeps whatever link the editor gave it (none by default). No URL or phone number is written into patterns.
- There is no event-specific message and no reservation logic.

## Instagram

- **Integration boundary:** the feed will come from an external Instagram plugin (likely Smash Balloon class), which owns authentication, API, retrieval and feed data. The theme owns the section: heading, "Seguir", rhythm, and tile presentation. The `lcda-instagram-grid` group is the replaceable part (`content-ownership.md`).
- The images are static and there is no integration: no API, feed plugin, scraping or external script. There are 6 fixture image blocks.
- Image block style **Reel de Instagram** (`is-style-lcda-reel`) adds the ▶ marker. It only shows inside `.lcda-instagram-grid`.
- "Seguir" links to the public profile (`https://www.instagram.com/_lacasadelarbol_/`). The link is editorial and can be changed in the block.

## Newsletter

- **Integration boundary:** the provider (not chosen; Mailchimp, Brevo…) will own subscription processing, validation, API, consent records and lists. The theme owns the band and the form's look. The Custom HTML block is the replaceable part (`content-ownership.md`).
- Presentation only, like the footer "Sumate" form. It is a Custom HTML block with a labelled `type="email"` input (`autocomplete="email"`, no `name`), not a `<form>`. The button is `type="button"` with `aria-disabled="true"` and a screen-reader note: "La suscripción estará disponible próximamente".
- Nothing is submitted, stored or faked, and no success message is shown. A provider integration replaces the Custom HTML block later.
- It uses fixed ids, so insert it once per page. On multisite, only users with `unfiltered_html` can edit that HTML block.

## Assets: fixture / final boundary

Classification against `docs/design/Image Asset Manifest.md`:

| Asset | Status | In the theme |
|---|---|---|
| Logo symbol / full logo | **A. real, approved** | `assets/images/logo-*.svg` (Step 2) |
| hero-agenda, hero-saberes, hero-reservas, hero-alquila, hero-lacasa | **C. missing** (the prototype photos were never supplied) | labelled neutral fixtures `assets/images/fixtures/hero-*.svg` |
| ig-sept, ig-alquila, ig-pacto, ig-canto, ig-cosmo, ig-flamencos-ig | **C. missing** (`ig-sept` existed only in the prototype) | labelled neutral fixtures `assets/images/fixtures/ig-*.svg` |
| Posters: cine-jojo, pop, lapoana, parri (Destacados) | **C. missing** | none in the theme; since E2.3 each card shows its Event's poster (Imagen destacada), or the 4:5 placeholder without one |

- Fixtures are placeholder rectangles in the design's placeholder color (#e7e3d9), labelled with the manifest ID and "FIXTURE TEMPORAL". They are not imagery (about 0.6 KB each) and are referenced by URL from the inserted content.
- `docs/references/` holds reference material, not production assets: the Instagram profile screenshots and the September agenda flyers. The flyer is a likely source for `ig-sept` (a square crop of the real post), but that is an editorial upload, not a theme file.
- **Removal:** once the real images are in the Media Library, no inserted content references `/assets/images/fixtures/`. Then delete that folder and the fixture `src` values in `home-hero.php` and `instagram.php`. To check, search the post content for `fixtures/`.

## Activation after deployment (wp-admin)

The theme never creates pages or changes settings. After 0.4.0 is deployed:

1. **Pages → Home** (or **Add New Page** titled "Home" if none exists). Keep the published "Agenda" page (slug `agenda`) so the Destacados links resolve on insert.
2. **Page → Template → La Casa — Lienzo.**
3. Insert **Patterns → La Casa del Árbol → Home (página completa)**. On a brand-new page, the starter-pattern dialog offers it directly. On an existing page with old content, delete that content first, or insert the pattern and remove the old blocks.
4. Replace content:
   - hero images (select image → Replace, then write the alt text);
   - Instagram images (and the Reel style where it applies).
   The Eventos destacados cards are not edited here: they come from **Eventos** (mark an event as featured).
5. Check that **Appearance → Menus → La Casa — CTA WhatsApp** has its item. The Festejá button uses it.
6. Save as **draft**, preview, then publish.
7. **Settings → Reading → Your homepage displays → A static page → Homepage: Home.** Keep the password protection / noindex setup unchanged.

Safer option: build and review the Home as a draft page first. Only switch the Reading setting after the preview passes QA; it is instantly reversible.

### One-time activation edit for Home pages built before E2.3 (decision S1)

A Home page inserted from the 0.4.x pattern stores the old heading copy. After deploying E2.3, edit it once in wp-admin (Pages → Home):

- eyebrow "Septiembre 2026" → **"Próximas fechas"**;
- H2 "Eventos destacados del mes" → **"Eventos destacados"**.

Nothing else is needed: the stored demo cards are replaced automatically, and the section hides itself with no featured events. The theme deliberately does not rewrite this copy at render time (it is page content). Pages inserted from the E2.3 pattern already have the new copy.

## E2 decisions (closed 2026-09-28; implemented in E2.3)

Recorded in `casa-eventos-contract.md` §15:
- The section is "Eventos destacados" (not "del mes") with the eyebrow "Próximas fechas" (N12): the E2.3 pattern copy; existing pages via the one-time edit above (S1).
- Cards from `Queries::featured_events( array( 'limit' => 4 ) )`: listed, featured, active, not finished, not month-bound.
- Cards show a compact date + time, "Sáb 03/10 · 21:00". (N4)
- With zero eligible events (or casa-eventos inactive), the whole section is hidden. (N1)

The hero and the other sections are not affected (verified byte-identical against the pre-E2.3 render).

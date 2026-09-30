# Content ownership and integration boundaries

**Status:** v1.1 (2026-09-29). Recorded after the Home V1 live QA (theme 0.4.1). This is an architecture decision record: it says **who owns which data and behavior**. The Event ownership is implemented (casa-eventos + theme, E2 complete: see "E2 status" at the end); the Instagram and newsletter boundaries are still presentation-only placeholders.

The rule: **the theme owns presentation.** It owns section layout, headings, markup contracts, CSS and the visual integration point. It does not own event data, Instagram feeds or newsletter subscriptions. Each of those has one owner, and the theme renders what that owner provides.

## Ownership map

| Area | Single source of truth / owner | Theme owns | Status in 0.4.x |
|---|---|---|---|
| Event data (title, poster, date/time, category, entrada/access, ticket info, CTA, featured flag) | **Event entity** in the future `casa-eventos` plugin (with WooCommerce for stock/orders) | card, grid and detail markup + CSS (`event-markup-contract.md`) | presentational placeholder cards only |
| Home "Eventos destacados" | a **projection/query of Event data** (`Queries::featured_events()`, max 4) | the section: heading (page content), "Ver agenda" actions, grid container, rhythm | dynamic since E2.3: cards from casa-eventos; the whole section hidden with no eligible events (`home-contract.md`) |
| Agenda (archive) | a projection/query of Event data | page header, filter chip presentation, masonry | dynamic since E2.2: month, navigation, chips and cards from casa-eventos (`agenda-contract.md` "Dynamic Agenda") |
| Single Event | the Event entity itself; the description is the Event's own block content | detail layout and components, section headings, `single-casa_evento.php` and its template parts | **live since E2.1**: the theme renders real Events from the casa-eventos public API (`single-event-contract.md` "Dynamic implementation") |
| Instagram feed | an **external Instagram plugin** (likely Smash Balloon class), incl. authentication, API, retrieval, caching, feed data | the section: eyebrow, H2, "Seguir", outer frame and grid presentation | 6 static fixture images |
| Newsletter signup | an **external email provider** (Mailchimp, Brevo… not chosen), incl. processing, validation, API, consent records, lists/audiences | the section: eyebrow, H2, form presentation | non-functional placeholder form |
| Navigation, footer "Navegación" / "Visitanos", WhatsApp URL | **WordPress menus** (Appearance → Menus) | rendering of the menu locations | live |

## Events: single source of truth

- The **Event entity is the only place event data is edited.** Home, Agenda, Single Event and Related all read the same Event records. No page stores its own copy of an event's title, poster, date, category or access information.
- **Home featured events are a query, not content.** The intended model, to be implemented in `casa-eventos` and not in the theme:
  - an Event has a boolean such as `featured_on_home`;
  - Home shows published Events where that flag is true **and** that are current or upcoming;
  - order is chronological, nearest first;
  - past events drop out automatically, even if the flag stays on; nobody has to un-feature them;
  - the number shown and the empty state (no featured upcoming events) are decided with that implementation.
- **Agenda** consumes the same Event data (published, listed, chronological, one local month at a time, history included), not a separate list. Since E2.2 the theme fills the `lcda-event-grid--agenda` group, the `lcda-filter-chips` list and the month heading from casa-eventos at render time (also on pages still holding the 0.5.0 demo cards); the sections stay (`agenda-contract.md`).
- **Home (E2.3):** only the event grid output inside the Home section (`lcda-event-grid--featured` and its cards) is rendered from casa-eventos, with the shared card markup (`event-markup-contract.md`); the section around it stays as the approved design (`lcda-section`, heading, "Ver agenda completa →", "Ver toda la agenda") and is left out entirely when there is nothing to show. Demo cards stored in Home 0.4.x pages are discarded at render time; nothing is migrated from them. The demo grid pattern files were deleted from the theme in E2.4.
- The theme never registers event CPTs, meta, taxonomies or queries (CLAUDE.md, `event-markup-contract.md`).

## Instagram: external-plugin boundary

- **Plugin side (future):** Instagram authentication and tokens, API calls, feed retrieval and caching, and post data (images, captions, links, reel flags).
- **Theme side:** the `lcda-instagram` section, i.e. eyebrow with the account, H2 "Seguinos en Instagram", the "Seguir" button, section rhythm, white background, and the framed square-tile presentation.
- **Replaceable part:** the `lcda-instagram-grid` group (6 fixture image blocks) is a placeholder. The plugin's feed block or shortcode replaces that group inside the section's container, where the heading and "Seguir" stay.
  - The theme's tile styling targets `.lcda-instagram-grid` only, so plugin markup is not affected by accident.
  - Matching the plugin's output to the approved look (6 / 3 / 3 square tiles, 2px ink frame, ▶ for reels) is done when the plugin is chosen: first through plugin settings, then with a small theme CSS layer scoped to the section.
- No Instagram API code, feed logic or plugin is added to the theme. Do not add a plugin until it is approved.

## Newsletter: external-provider boundary

- **Provider side (future):** subscription processing, validation, the provider API or embedded form, double opt-in and consent records where required, and lists/audiences.
- **Theme side:** the `lcda-newsletter` band, i.e. eyebrow "Comunidad", H2, and the joined input + "Sumarme" presentation (`.lcda-newsletter-form--band` in `home.css`). The footer "Sumate" form has the same boundary (`template-parts/site/footer.php`).
- **Replaceable part:** the Custom HTML block in the Home band, and the footer's static form, are intentionally non-functional placeholders. They are not a `<form>`, have no name/action, and the button is `type="button"` + `aria-disabled`, so nothing is sent, stored or faked. The provider's form replaces them; the theme then styles the provider's fields to the approved look.
- No provider is chosen or integrated in the theme. The theme does not store emails or consent.

## Footer "Visitanos"

- The column is menu-driven: it renders only when the location **La Casa — Footer: Visitanos** has a menu assigned, and its contents are that menu's items (address, phone, Instagram as custom links).
- If the assigned menu has no items, WordPress prints no list, so only the column title shows. That is a **wp-admin content task** (Appearance → Menus → add the items), not a theme defect.
- The theme never hard-codes the address, phone or social URLs.

## E2 status: complete (2026-09-29; theme 0.7.0, casa-eventos 0.2.0)

- **Single Event:** implemented (E2.1).
- **Agenda:** implemented (E2.2). The dynamic Agenda shows history, including finished events, which supersedes the "current/upcoming only" wording above.
- **Home featured events:** implemented (E2.3). "Eventos destacados" with the eyebrow "Próximas fechas" (existing Home pages: one-time copy edit, `home-contract.md`), not month-bound, up to 4 cards, hidden when empty or when casa-eventos is inactive.
- **E2.4 (final review):** demo event patterns deleted; the render-slot helper is shared (`inc/blocks.php`); cross-surface, plugin-inactive, legacy-page, visual and performance QA passed. **E2 is complete.**
- **Not part of E2 (not started):** ticketing / WooCommerce / Payway, the custom Programador UI, the Instagram and newsletter integrations.
- **Deployment note:** Home, Agenda and Event pages are computed per request from live Event states; a production full-page cache must be reviewed (TTL or bypass) before release (`docs/deployment.md`).
- The decisions are in `casa-eventos-contract.md` §15; the theme/plugin boundary is in `event-markup-contract.md` "Rendering responsibility".

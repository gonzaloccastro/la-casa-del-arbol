# Content ownership and integration boundaries

**Status:** v1, recorded after the Home V1 live QA (theme 0.4.1). This is an architecture decision record: it says **who owns which data and behavior**. Nothing here is implemented yet except the theme's presentation side.

The rule: **the theme owns presentation.** It owns section layout, headings, markup contracts, CSS and the visual integration point. It does not own event data, Instagram feeds or newsletter subscriptions. Each of those has one owner, and the theme renders what that owner provides.

## Ownership map

| Area | Single source of truth / owner | Theme owns | Status in 0.4.x |
|---|---|---|---|
| Event data (title, poster, date/time, category, entrada/access, ticket info, CTA, featured flag) | **Event entity** in the future `casa-eventos` plugin (with WooCommerce for stock/orders) | card, grid and detail markup + CSS (`event-markup-contract.md`) | presentational placeholder cards only |
| Home "Eventos destacados del mes" | a **projection/query of Event data** | the section: heading, "Ver agenda" actions, grid container, rhythm | placeholder demo grid |
| Agenda (archive) | a projection/query of Event data | page header, filter chip presentation, masonry | not built (Step 5) |
| Single Event | the Event entity itself | detail layout and components | not built (Step 6) |
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
- **Agenda** consumes the same Event data (published, current/upcoming, chronological, month grouping), not a separate list.
- **What gets replaced later:** only the event grid output inside the Home section (`lcda-event-grid--featured` and its cards). The section around it stays as the approved design: `lcda-section`, heading, "Ver agenda completa →", "Ver toda la agenda". `casa-eventos` renders the same card markup (`event-markup-contract.md`), so no visual redesign is needed.
- **Until then:** the Home cards are placeholders from the demo grid pattern. They are **not** the future editing model. Editors must not treat the Home as the place to maintain event data. When `casa-eventos` exists, the placeholder grid is removed from the Home page and from the theme, and nothing is migrated from it.
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

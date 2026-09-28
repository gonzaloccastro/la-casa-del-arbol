# casa-eventos contract (Event Core, E1)

**Status:** v1.2, milestone E1 + E1.1 (roles and capabilities), plugin `casa-eventos` 0.1.0 (updated after the LocalWP runtime QA, WordPress 7.1.2). Source: `plugins/casa-eventos/`.
**Inputs:** `ticketing-v1-contract.md` (functional contract), `content-ownership.md`, `event-markup-contract.md`, `home-contract.md`, `agenda-contract.md`, `single-event-contract.md`, the design-only architecture proposal (local report 16), the product decisions closed for E1 on 2026-09-27 and those closed for E1.1 on 2026-09-28 (paused events, categories, the Programador role).

This document records **approved** decisions only. Proposals that are still open are listed as open in the last section. Where it says "E2" or "commerce", the item is intentionally not implemented in E1.

## 1. Boundaries

| Layer | Owns | Never does |
|---|---|---|
| **Event Core** (`inc/core/`) | CPT, taxonomy, meta schema, datetime rules, derived fields, UUID, operational state, validation, the Event read model and the query API | frontend output, WooCommerce, ticket types, orders |
| **Admin** (`inc/admin/`) | editor panels, list table, category fields, settings screen, notices | reading `_casa_*` meta directly; it uses the core API |
| **Later modules** (E2 frontend, commerce) | views, CTAs, Woo sync, holds, checkout, Payway, emails, exports | reading `_casa_*` meta directly; they consume `Event` and `Queries` |
| **Theme** `la-casa-del-arbol` | presentation (markup contracts, CSS) | event CPT/meta/taxonomy/queries/business logic |

- Event Core has **no WooCommerce dependency** and works without WooCommerce installed.
- The Event never stores ticket price or ticket stock. Those will live only in WooCommerce variations (commerce phases).
- Only `inc/core/` contains `_casa_` meta key strings in PHP. This is checked automatically (`tests/check-architecture.php`).

## 2. Event entity

- Post type **`casa_evento`**. Public singular URL **`/evento/{slug}/`** (`with_front` false). **No CPT archive**: `/agenda/` stays the listing.
- **One post = one dated occurrence.** No recurrence engine in V1.
- Supports: title, editor, thumbnail, excerpt, revisions, custom-fields (required for REST meta).

| Concept | Storage |
|---|---|
| Title | `post_title` (required to publish) |
| Description | `post_content` (Gutenberg blocks) |
| Subtitle / bajada | `post_excerpt`, read **raw** (never `get_the_excerpt()`, which invents text) |
| Poster | featured image (`_thumbnail_id`); alt text lives on the attachment |
| Category | taxonomy `casa_categoria`, exactly one term to publish |
| Identity | `_casa_uuid` |

Capabilities: the Event has its own primitive capabilities (`*_casa_eventos`), not the generic post ones. See §14.

## 3. Meta schema

All keys are protected (`_` prefix), `single`, registered with `register_post_meta()` on `casa_evento`. "REST" = exposed as editable REST meta (auth: `edit_post` on that event). "Rev" = metadata revisions enabled (WP ≥ 6.4).

| Key | Type / values | Blank / default | REST | Rev | Written by |
|---|---|---|---|---|---|
| `_casa_start` | local wall time `Y-m-d H:i:s` | required to publish | yes | yes | editor |
| `_casa_end` | local wall time `Y-m-d H:i:s` | optional; must be > start | yes | yes | editor |
| `_casa_access_mode` | `tickets` \| `whatsapp` \| `external` | required to publish; no silent default | yes | yes | editor |
| `_casa_entry_kind` | `paid` \| `free` \| `gorra` | optional (`''` = not specified) | yes | yes | editor |
| `_casa_entry_label` | short text | optional | yes | yes | editor |
| `_casa_external_url` | http(s) URL | required to publish when mode = `external`; kept but ignored otherwise | yes | yes | editor |
| `_casa_capacity` | positive integer | blank → **100**, materialized on save | yes | yes | editor, then sync |
| `_casa_sales_close` | local wall time `Y-m-d H:i:s` | blank → start − 60 min, computed on read, **not** materialized | yes | yes | editor |
| `_casa_status` | `active` \| `paused` \| `cancelled` | `active`, materialized | yes | **no** | editor |
| `_casa_listed` | `'1'` \| `'0'` (REST boolean) | `'1'`, materialized | yes | yes | editor |
| `_casa_featured` | `'1'` \| `'0'` (REST boolean) | `'0'`, materialized | yes | yes | editor |
| `_casa_uuid` | UUID v4 | generated on first real save | no | no | sync only |
| `_casa_timezone` | IANA identifier | captured on first real save | no | no | sync only |
| `_casa_start_gmt` | UTC `Y-m-d H:i:s` | `''` without start | no | no | sync only |
| `_casa_end_gmt` | UTC `Y-m-d H:i:s` | `''` without a valid end | no | no | sync only |
| `_casa_ends_gmt` | UTC effective end | `''` without start | no | no | sync only |
| `_casa_cancelled_gmt` | UTC instant of cancellation | `''` unless cancelled | no | no | sync only |

- Identity and derived fields are never editor-controlled and are not revisioned. They are recomputed from their inputs on every save (`wp_after_insert_post`) and after a revision restore.
- Derived fields are always stored (possibly `''`), so admin ordering by `_casa_start_gmt` never drops events without a date.
- Read-only REST field **`casa_event`** on the post type: uuid, timezone, effective state, gmt instants, effective sales close, cancelled-at, validation errors/warnings. It is computed from the read model and is never written.

### Category taxonomy

- **`casa_categoria`**, attached only to `casa_evento`. Hierarchical UI (checkboxes) but used as a **flat controlled vocabulary**. Not public, no term archives, no rewrite; REST enabled for the editor.
- **Meaning (closed, E1.1):** a category is a **general, stable public Agenda filter**. Conceptual examples: Música, Cine, Teatro, Talleres, Fiestas, Ferias, Otros. The production vocabulary is configured editorially later. Categories are **not** artists, event names, producers, individual cycles or ticket types.
- Exactly **one** category for a published or scheduled event (validated).
- **Category administration belongs to administrators.** The Programador assigns one existing category and cannot create, edit or delete categories (§14).
- Term meta: `_casa_color` = `mint` \| `yellow` (the category owns the tag color; never stored per event), `_casa_order` = integer (chip order, then name). Categories created outside the category screen (e.g. from the editor) get explicit `mint` / `0`. Every registered sanitize callback is a plugin function: WordPress calls them with four arguments, which PHP internal functions such as `intval()` reject.
- The plugin **does not create categories** on activation. The initial categories are created editorially.
- Chip presentation and category queries for the Agenda are E2.

### Settings

Option `casa_eventos_settings` (Eventos → Ajustes): `venue_name` (default "La Casa del Árbol"), `venue_address` (default "Av. Córdoba 5217"). **Single venue in V1**; the settings are the authoritative location and there is no per-event venue override. The WhatsApp number is **not** a plugin setting (see §9).

## 4. Datetime rules

- **Authoritative: local wall time + IANA timezone.** The GMT copies are derived for comparisons, sorting and scheduling (the core `post_date` / `post_date_gmt` pattern).
- The event timezone is captured on the first real save: the site's **named** timezone (`timezone_string`), or `America/Argentina/Buenos_Aires` (filterable fallback) when the site uses a manual UTC offset. It is then kept: changing the site setting later does not reinterpret existing events.
- An admin notice warns when the site timezone is not a named timezone, or is a named timezone other than the venue's.
- Never PHP's default timezone: only `DateTimeImmutable` with explicit zones, `gmdate()`, `time()`, `wp_date()`.
- **Effective end** = the declared end when it is after the start, otherwise start + **3 hours** (filterable default duration). `_casa_ends_gmt` is the single key for "finished".
- **Finished is derived, never stored:** `now ≥ ends_gmt`.
- An event belongs to the **real calendar date and month in which it starts**. An event starting Sunday 00:30 is Sunday. A multi-day event belongs to its start month in V1.
- Queries compare against "now" rounded down to the minute (cache-friendly, see §7).

## 5. Operational state

| Concept | Where |
|---|---|
| draft / pending / scheduled (`future`) / published | WordPress `post_status` |
| active / paused / cancelled | `_casa_status` |
| finished | derived from `_casa_ends_gmt` |

Effective state (one function, used by admin and all later modules):

1. `post_status` is `future` → **scheduled**; not `publish` → **draft** (includes pending, private, trash);
2. `_casa_status` = cancelled → **cancelled** (wins over finished);
3. now ≥ effective end → **finished**;
4. `_casa_status` = paused → **paused**;
5. otherwise → **active**.

(A cancelled scheduled event reads as scheduled until it is published, and then as cancelled.)

Rules:
- **Paused** remains public and removes every actionable CTA, including the WhatsApp reservation.
- **Cancelled** remains public and blocks actions immediately.
- A cancelled event **may be reactivated by administrators** (`manage_options`, filterable) with a warning. The Programador cannot reactivate. Enforced on the server.

Visibility by state (closed, E1.1):

| State | Agenda | Home featured | Actionable CTA |
|---|---|---|---|
| active | eligible | eligible if explicitly featured | according to access/sales rules |
| paused | stays visible | **not eligible** | no |
| cancelled | stays visible | **not eligible** | no |
| finished | historical Agenda, visible | **not eligible** | no |
- Cancelling **never refunds** buyers automatically.
- "Actionable" (a live CTA may be shown) ⇔ effective state is **active**. Finished, paused, cancelled, scheduled and draft are never actionable. CTA rendering itself is E2.

## 6. Access, entry, capacity, sales cutoff

- **Access mode** (drives the CTA in E2): `tickets` (own ticketing, later: Woo + Payway), `whatsapp`, `external` (a URL that will drive "Comprar entradas" to Passline or another provider).
- **Entry kind**: `paid` \| `free` \| `gorra`. **Entry label**: optional editorial short text. Business logic is never derived from matching label strings.
- In tickets mode no Woo product is created in E1, and no price or stock is stored on the Event.
- **Capacity**: Event metadata, positive integer, default **100**, materialized on save (a later change of the default never changes existing events). Blank on save becomes 100. No unlimited capacity in V1.
- **Sales cutoff**: blank means start − 60 minutes (filterable offset, computed on read so it follows the start). An explicit cutoff **may be after the start**, must **not** be later than the effective end (blocking), and gets a warning when it is after the start.
- Enforcement of capacity and cutoff against real orders and holds is a commerce-phase concern.

## 7. Visibility and queries

- `listed` = eligible for Agenda / Home / Related. An unlisted event stays reachable by its URL; its SEO treatment (noindex, sitemap) is E2.
- `featured` = eligible for Home featured events.
- Ordering everywhere: `_casa_start_gmt` ASC, then post ID ASC (stable for same-minute events).

Query API (`CasaEventos\Core\Queries`, returns `Event[]`):

| Function | Selection |
|---|---|
| `featured_events( [limit] )` | published, featured, **listed**, **active** (neither paused nor cancelled), effective end > now, nearest first |
| `upcoming_events( [limit, exclude, include_cancelled, category] )` | published, listed, effective end > now |
| `related_events( event, [limit] )` | `upcoming_events` excluding the event and cancelled events — **provisional default**, the related rule is E2 |
| `month_events( 'YYYY-MM' )` | published, listed, local start inside the local month, **including past (historical) and cancelled events** |
| `adjacent_event_month( 'YYYY-MM', ±1 )` | nearest earlier/later month that has published listed events |
| `current_month()` | the current month in the venue timezone |

These functions carry no presentation decisions (counts, fallbacks, empty states are E2).

## 8. Editor

- Gutenberg block editor with **native document sidebar panels** in plain WordPress JavaScript (no npm, no build, no JSX), over typed, sanitized, authenticated REST meta. No ACF, no classic meta boxes for the core fields.
- **"Datos del evento"**: start \*, end, access mode \*, entry kind, entry label, external URL (only when mode = external), capacity, sales cutoff.
- **"Publicación del evento"**: active / paused / cancelled, listed, featured, UUID (read-only).
- Drafts may be incomplete. **Publishing or scheduling is validated on the server** (REST `rest_pre_insert_casa_evento`; Quick/Bulk Edit through `wp_insert_post_data`). Publish/schedule rules: title, start, end > start when set, access mode, external URL in external mode, exactly one category, sales cutoff ≤ effective end. Rules that block **any** save, drafts included: invalid dates, unknown enum values, a non-http(s) URL, a capacity that is not a positive integer, and reactivation of a cancelled event by a non-administrator.
- The REST check runs **before anything is written**. Core validates meta against its REST schema only after the post row (status, title, terms) is saved, so the plugin also validates the request's meta against the same registered schema up front, and counts only IDs that really are `casa_categoria` terms (core silently drops unknown IDs). A refused request leaves the post, its meta and its terms unchanged.
- Programmatic saves outside those paths (WP-CLI, custom code) are not blocked; the list table flags published events whose data is incomplete.
- The filter `casa_eventos/validate_event` is the single hook point where later modules add guards (e.g. capacity below sold + held).

## 9. Hooks for later modules

| Hook | When |
|---|---|
| `casa_eventos/event_synced` (id, changes) | after every derivation |
| `casa_eventos/schedule_changed` (id, old, new) | start / end / effective end GMT changed (including the first time a date is set) |
| `casa_eventos/event_cancelled` (id) | the event became cancelled (records `_casa_cancelled_gmt`) |
| `casa_eventos/event_reactivated` (id, cancelled_gmt) | a cancelled event became active or paused |
| `casa_eventos/uuid_regenerated` (id, old, new) | a duplicated UUID was replaced |
| `casa_eventos/validate_event` (filter) | publish/save validation |

Event Core schedules nothing and sends nothing. WhatsApp URL integration is E2 and must reuse the theme's existing single source (the CTA menu location), never an Event field or a second copy of the number.

## 10. UUID

- UUID v4 (`wp_generate_uuid4`), generated on the first real save (never for auto-drafts), **immutable**: the plugin refuses updates, re-adds and deletes of an existing UUID from any other code path.
- **Collision protection:** if two events carry the same UUID (a copied post, an import), the event with the lower ID keeps it and the other gets a new one (`uuid_regenerated` fires). It is checked when the UUID is written and on every sync.
- The UUID is a machine identity for integrations and future tickets. It is never presented as a QR code or used as an access credential.

## 11. Revisions and uninstall

- Metadata revisions are enabled for the editorial fields in §3 ("Rev"). Identity and derived fields are excluded and are recomputed after a revision restore.
- `_casa_status` is **not** revisioned: it is operational state, not content. Restoring a revision (which does not go through REST validation) must never cancel or reactivate an event; reactivation stays administrator-only.
- **Uninstall preserves all Event data** (posts, meta, terms, settings). Nothing is deleted automatically. Uninstall removes only the authorization the plugin added (§14): the Programador role definition and the plugin capabilities. Users are never deleted.

## 12. Decisions recorded for E2 (not implemented in E1)

**Home**
- The section is **"Eventos destacados"**, not necessarily "Eventos destacados del mes". Featured events are **not constrained to the current month**.
- Cancelled **and paused** events are not promoted (closed in E1.1). Featured requires listed. (All in `featured_events()`.)
- Exact count, fallback and empty state are E2.

**Agenda**
- `/agenda/` defaults to the **current month**; an event belongs to its **start month**.
- **Previous months are navigable**; month navigation stays secondary in the visual hierarchy.
- **Historical event cards remain visible** and have **no active action**. If the design keeps a CTA-shaped element on them, it is grey and non-actionable, not a working link.
- Current and future event CTAs follow the state and the access mode.

These supersede the "current/upcoming only" wording in `content-ownership.md` and `agenda-contract.md` for the dynamic Agenda. Those documents are reconciled in E2.

**Also E2:** Related rule, CTA rendering (labels, WhatsApp/external destinations), state presentation (paused, cancelled, finished), unlisted SEO, single-event template and `lcda_is_canvas()` coverage, removal of the theme's demo patterns.

## 13. Still open (not approved)

- Frontend rendering responsibility (plugin blocks vs theme templates).
- Agenda empty-month behavior and whether "next month" skips empty months (the query API supports both).
- Whether the Cuándo text shows the end time.
- Entry label default text and, in tickets mode, deriving it from ticket prices.
- Agenda URL format for month/category (`?mes=YYYY-MM&categoria=slug` was proposed).
- Plugin deployment workflow (the deploy script is theme-only).
- Commerce decisions: Woo hold mechanism, XLSX writer, Payway gateway, commerce capabilities (orders, buyer lists, exports).
- Programador access to synced patterns and user global styles in the editor (core gates both on the generic `edit_posts`; see §14).

## 14. Roles and capabilities (E1.1)

Source: `inc/core/capabilities.php`.

### Model

- `casa_evento` uses **its own primitive capabilities** with `map_meta_cap` (`capability_type` `casa_evento` / `casa_eventos`). Generic post permissions never grant access to events. Event permissions never grant access to Posts or Pages.
- `casa_categoria` has **four separate capabilities**. The taxonomy is hierarchical, so core requires `edit_terms`, never `assign_terms`, to create a term (REST, the editor's "add new category", the admin form).
- Settings (Eventos → Ajustes) and the reactivation of a cancelled event remain **`manage_options`**, which is administrator-only.
- No generic capability is granted to make a screen work.

| Post type capability | Event capability |
|---|---|
| edit_posts / create_posts | `edit_casa_eventos` |
| edit_others_posts | `edit_others_casa_eventos` |
| edit_published_posts | `edit_published_casa_eventos` |
| edit_private_posts | `edit_private_casa_eventos` |
| publish_posts | `publish_casa_eventos` |
| read_private_posts | `read_private_casa_eventos` |
| delete_posts | `delete_casa_eventos` |
| delete_others_posts | `delete_others_casa_eventos` |
| delete_published_posts | `delete_published_casa_eventos` |
| delete_private_posts | `delete_private_casa_eventos` |
| read | `read` |
| meta: edit_post / read_post / delete_post | `edit_casa_evento` / `read_casa_evento` / `delete_casa_evento` (mapped by core) |

| Taxonomy capability | Category capability | Administrator | Programador |
|---|---|---|---|
| manage_terms (Categorías screen, term meta) | `manage_casa_categorias` | yes | no |
| edit_terms (create, edit) | `edit_casa_categorias` | yes | no |
| delete_terms | `delete_casa_categorias` | yes | no |
| assign_terms | `assign_casa_categorias` | yes | yes |

### Programador (`casa_programador`, display name "Programador")

The operational staff member who loads and maintains the dates. It is a **shared institutional calendar**: the Programador edits every event regardless of author.

Capabilities (the complete role): `read`, `upload_files`, `edit_casa_eventos`, `edit_others_casa_eventos`, `edit_published_casa_eventos`, `edit_private_casa_eventos`, `publish_casa_eventos`, `read_private_casa_eventos`, `assign_casa_categorias`.

- **Can:** access wp-admin; list, create, edit (any author), save drafts, publish and schedule events; pause, resume and cancel them (cancellation keeps its confirmation); assign one existing category; upload and select posters.
- **Cannot:** delete or trash events, drafts included (closed decision: no delete capability in V1; pause and cancel are the operational paths, and an administrator removes mistaken drafts); create, edit or delete categories, or open Eventos → Categorías; open Eventos → Ajustes; reactivate a cancelled event; manage users, plugins, themes, WordPress settings, Pages, Posts, comments, tools; WooCommerce administration (no Woo capability is granted).
- **Visible operational navigation** (`inc/admin/navigation.php`, presentation only):
  - **Left menu:** Eventos (Todos los eventos · Añadir evento) and Medios, nothing else. It is an allowlist on `admin_menu`: Escritorio, Perfil, separators and menus added by other plugins are removed from the menu. Core's "Contraer menú" button stays.
  - **Admin bar:** a minimal operational bar, navigation plus logout.
    - In wp-admin: the mobile menu toggle (without it the left menu cannot be opened on a phone), the site-name node (opens the public site, no submenu) and, on the right, **"Salir"**.
    - On the public site: **"Eventos"** (back to the events list) and **"Salir"**.
    - "Salir" uses WordPress's own logout URL and nonce (`wp_logout_url()`).
    - Everything else is removed: the WordPress logo, updates, comments, "+ Nuevo", the account/profile menu, search, the site-name submenu, and "Editar evento".
    - The admin bar is not disabled, and the theme is not involved.
  - **Login:** without a specific destination, the Programador lands on the events list.
  - The navigation applies to users holding the role who are not administrators (`is_programador()`). Administrators keep the normal menu and admin bar.
- **Navigation is not authorization.** The Programador keeps `read` and `upload_files`, so Escritorio (`index.php`) and Perfil (`profile.php`) stay reachable by URL, as WordPress needs for account behavior. Every other screen is refused by capability checks (403), not hidden with CSS or redirects.
- **Media:** `upload_files` allows uploading and browsing the library, and selecting any existing image as a poster. Core maps editing an attachment to the generic `edit_posts`. One narrow `map_meta_cap` rule lets a user who holds `upload_files` and `edit_casa_eventos` edit the details (alt text) of **images they uploaded themselves**. Other people's media stay read-only, and deleting media keeps core's rules (the Programador cannot).
- The **WordPress Editor, Author and Contributor roles get no Event or category capability** (closed decision). Event operation belongs to Administrator and Programador only.

### Administrator

Receives every Event and category capability in addition to its core capabilities. It keeps full control: delete events, manage categories, settings, reactivation.

### Install, update, deactivation, uninstall

- **Activation** runs `install_roles()`: it creates the Programador role or resets it to the definition above (extra capabilities are removed, missing ones added), and adds the plugin capabilities to the administrator. It is idempotent.
- **Updates:** roles are stored in the database, and plugin updates do not run the activation hook. `CAPS_VERSION` (option `casa_eventos_caps_version`) is compared on `plugins_loaded`. When it differs, the same install runs once. Bump it whenever the role definition changes.
- **Deactivation** removes the Programador **role definition** and the version option. Its users keep their role assignment but have no access (not even uploads) while the plugin is inactive. Reactivation restores access automatically. Administrator capabilities stay (inert).
- **Uninstall** removes the Programador role and every plugin capability from every role, plus the version option. **No event, meta, category, setting or user is deleted.**

### Interim editor UX and future direction (decided, not implemented)

- The current Gutenberg editor with its document-sidebar panels is an **administrative/development interface, not the final operational UX** for the Programador. During E1.1 it remains accessible to the Programador as an interim interface.
- A later phase will build a **simple, visual, server-rendered Event management interface owned by `casa-eventos`**, with a focused flow: Eventos → event list · **+ Cargar evento** · edit event.
- The Programador should not need to understand CPTs, Gutenberg sidebar concepts, taxonomy administration, WooCommerce products/variations or internal metadata.
- The form exposes event concepts directly: title, poster, existing-category selector, start/end, description, access mode, entry kind, capacity, visibility, operational state, and later ticket types.
- No React/SPA tooling for it. It will use the same capabilities, validation (`validate_event_data`) and Event API as today.

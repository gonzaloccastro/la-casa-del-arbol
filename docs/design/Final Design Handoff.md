# La Casa del Árbol — Final Design Handoff (V1)

**Status:** Desktop V1 = APPROVED · Mobile V1 = APPROVED · Design FROZEN
**Source of truth:** `Website Direction.dc.html` (screens: 01 Home, 02 Agenda, 03 Single Event — each in Desktop and Mobile)
**Scope:** Implementation spec only. No redesign. Where this document and the source file disagree, the source file wins.

Reading guide — every section is tagged:
- **[GLOBAL]** reusable rule/component, applies on every page
- **[HOME] / [AGENDA] / [EVENT]** page-specific
- **Desktop** / **Mobile** sub-blocks describe breakpoint behavior

> Preview-only elements to ignore in production: the black "Vista previa" toolbar at the very top, the grey `#e7e3d9` backdrop around mobile screens, and the 2px side borders on the mobile frame. The desktop header's `top:33px` offset exists only to clear that toolbar — in production it is `top:0`.

---

## 1. GLOBAL RULES

### 1.1 Color tokens [GLOBAL]

| Token | Hex | Use |
|---|---|---|
| `--ink` | `#171717` | Text, all borders, dark buttons, footer background, WhatsApp CTA on red |
| `--paper` | `#FCFAF6` | Page background, header background, light text on dark/red |
| `--white` | `#FFFFFF` | Card body background, Instagram section background, input background (light contexts) |
| `--red` | `#EF3323` | Primary accent: primary CTA, eyebrows, active nav underline, footer top rule, date numerals, entrada text, mobile menu background, Festejá band |
| `--mint` | `#B8DDBF` | Category tag (mint categories), burst background, footer secondary text |
| `--yellow` | `#F2C45E` | Category tag (yellow categories), burst background, active filter chip, footer column labels, eyebrow on red |
| `--placeholder` | `#e7e3d9` | Image container background while loading / empty image slot |
| `--body-muted` | `#3a3a3a` | Card subtitle text |
| `--body-soft` | `#5a5a5a` | Single Event secondary paragraph |

Opacity conventions:
- Inactive nav items (header desktop): `opacity:.5`
- Inactive nav items (footer): `opacity:.6`
- Inactive nav items (mobile menu): `opacity:.55`
- Meta line (weekday · time) and "La Casa del Árbol" card label: `opacity:.6`
- Footer divider: `rgba(252,250,246,.2)`; mobile-menu dividers: `rgba(252,250,246,.35)`
- Carousel arrow background: `rgba(252,250,246,.85)`

Link defaults: `a { color:#EF3323 } a:hover { color:#171717 }`.

No gradients, no shadows, no border-radius anywhere, except the circular "entrada libre" stamp (§1.9).

### 1.2 Typography [GLOBAL]

Google Fonts load: `Anton` · `Archivo` 600/800/900 · `Jost` 500/600/700 · `Work Sans` 400/500/600.

| Role | Family | Weight | Case | Notes |
|---|---|---|---|---|
| Display (H1, H2, mobile menu items) | Anton | 400 (only weight) | UPPERCASE | line-height .88–1 |
| UI / labels / buttons / card titles / eyebrows / tags | Archivo | 800 (labels, buttons) · 900 (card titles, primary CTAs, date numerals) | UPPERCASE | letter-spacing .02–.08em |
| Header nav (desktop only) | Jost | 600 | UPPERCASE | 10.5px, letter-spacing .02em |
| Body / paragraphs / inputs / footer lists | Work Sans | 400 | Sentence case | |

Type scale (desktop → mobile):

| Element | Desktop | Mobile |
|---|---|---|
| Agenda H1 "Septiembre" | `clamp(3rem,7vw,6rem)`, lh .88 | `2.6rem`, lh .9 |
| Single Event H1 (title) | `clamp(2.2rem,4.6vw,4rem)`, lh .95 | `2.1rem`, lh .98 |
| Home H2 "Eventos destacados del mes" | `clamp(2rem,4vw,3.2rem)`, lh .95 | `1.9rem`, lh .98 |
| Home H2 "¡Festejá en el Árbol!" | `clamp(2rem,4.2vw,3.4rem)`, lh .95 | `1.9rem`, lh .98 |
| Home H2 "Seguinos en Instagram" | `clamp(1.8rem,3.4vw,2.8rem)` | `1.5rem` |
| Home H2 newsletter | `clamp(1.7rem,3vw,2.3rem)`, lh 1 | `1.5rem`, lh 1 |
| Event H2 "También en la agenda" | `clamp(1.6rem,3vw,2.2rem)` | `1.4rem` |
| Mobile menu items | — | Anton `1.7rem` |
| Eyebrow (red / yellow on red) | Archivo 800, 13px, ls .08em | 11px, ls .08em |
| Card title — Home | Archivo 900, 16px, lh 1.15 | 14.5px |
| Card title — Agenda | Archivo 900, 19px, lh 1.12 | 17px |
| Card title — Related | Archivo 900, 15px | 13.5px |
| Card meta (weekday · time) | Archivo 800, 11–11.5px, ls .04em | 10–10.5px, ls .03em |
| Card subtitle (Agenda) | Work Sans 13.5px, lh 1.5 | 13px |
| Event body paragraph | Work Sans 16.5px, lh 1.7, max 56ch | 15px, lh 1.65 |
| Event secondary paragraph | Work Sans 15px, lh 1.6 | 13.5px, lh 1.55 |
| Footer column label | Archivo 800, 11px, ls .06em, yellow | same |
| Footer list items | Work Sans 14px | same |
| Footer legal line | Work Sans 12px, mint | same |

### 1.3 Spacing system [GLOBAL]

Base values in use (px): 4 · 6 · 8 · 9 · 10 · 12 · 14 · 16 · 18 · 20 · 22 · 24 · 28 · 32 · 36 · 40 · 48 · 56 · 64.

Section vertical padding:

| Section type | Desktop | Mobile |
|---|---|---|
| Content section (Destacados, Instagram) | 64px top/bottom | 36px top / 40px bottom |
| Band section (Festejá, Newsletter) | 56px | 40px (Festejá) / 36–40px (Newsletter) |
| Agenda page header | 48px top / 32px bottom | 32px / 24px |
| Agenda grid | 40px top / 64px bottom | 28px / 40px |
| Single Event main | 28px top / 64px bottom | 20px / 40px |
| Related row | 48px / 64px | 32px / 40px |
| Footer main | 56px top / 28px bottom | 44px / 28px |

Grid/stack gaps: desktop card grids 24px; Instagram 16px desktop / 8px mobile; mobile card feed 22px; mobile horizontal scrollers 14px; heading → content 32px desktop / 18–22px mobile.

### 1.4 Gutters and max widths [GLOBAL]

| | Desktop | Mobile |
|---|---|---|
| Content max-width | `1440px`, centered | full width (designed at 390px, frame max 430px) |
| Side gutter | `32px` | `16px` (content) · `24px` (footer, mobile menu) · `20px` (footer legal row) |
| Header inner padding | `14px 32px` | `12px 16px` |

Horizontal scrollers on mobile bleed to the viewport edge using `margin:0 -16px; padding:0 16px` so the first card aligns with the gutter and cards scroll edge-to-edge.

### 1.5 Borders and graphic treatments [GLOBAL]

- **Header bottom rule:** 3px solid ink.
- **Section dividers:** every section ends with `border-bottom:2px solid ink`.
- **Footer top rule:** 4px solid red.
- **Cards, posters, chips, secondary buttons, Instagram tiles, inputs (light):** 2px solid ink.
- **Card footer rule:** `1px dashed ink` separating content from entrada line.
- **Metadata grid (Event):** 2px solid top/bottom; 1px dashed internal dividers.
- **Footer inputs (dark):** 1px solid paper.
- All corners square. No shadows.
- **Burst / star shape:** 26-point polygon via `clip-path` (exact polygon in §1.8).

### 1.6 Header [GLOBAL]

**Desktop**
- Sticky, `top:0`, `z-index:20`, background paper, 3px ink bottom rule.
- Single row, `justify-content:space-between`, gap 20px:
  1. Logo **symbol only** (no wordmark), 52×33px, ink, links to Home.
  2. Nav: Agenda · Saberes +60 · Reservas · Alquilá · Tienda · La Casa · Contacto. Jost 600 10.5px uppercase, gap 13px, `nowrap`; overflows horizontally rather than wrapping.
  3. WhatsApp CTA: red solid, Archivo 800 12px, padding `10px 16px`.
- Active item: full opacity + `border-bottom:2px solid red; padding-bottom:3px`. Inactive: opacity .5. (In V1 "Agenda" is shown active on all pages.)

**Mobile**
- Sticky `top:0`, paper, 3px ink bottom rule, padding `12px 16px`.
- Left: logo symbol 34×21px → Home.
- Right: "Menú" button — 2px ink outline, Archivo 800 11px uppercase, label + 3-line icon (16×2px bars, 3px gap), `min-height:44px`.
- No inline nav and no WhatsApp button in the bar (WhatsApp lives in the menu).

### 1.7 Mobile navigation [GLOBAL · Mobile only]

- Tapping "Menú" opens a **full-screen red overlay** (`position:fixed; inset:0; z-index:200; background:#EF3323; color:paper`), vertical scroll if needed, padding `20px 24px`.
- Top row: paper logo symbol (34×21) left; "Cerrar ✕" button right (2px paper outline, Archivo 800 11px, `min-height:44px`). 40px below it the nav starts.
- Nav list: Agenda, Saberes +60, Reservas, Alquilá, Tienda, La Casa, Contacto — **Anton 1.7rem uppercase**, each row `padding:16px 0`, divided by `1px dashed rgba(252,250,246,.35)` (last row without divider). Inactive items opacity .55.
- Bottom: full-width black "Escribinos por WhatsApp" button (Archivo 900 13px, padding 16px, centered), 24px above.
- Behavior: tapping a nav item navigates **and closes** the menu. "Cerrar" closes. No animation specified in V1 — an instant show/hide is the approved state; a short fade is acceptable, no slide-in drawers.
- Implementation must lock body scroll while open.

### 1.8 Date badge / burst [GLOBAL]

- Square element clipped with:
  `clip-path:polygon(50% 0%,58% 14%,70% 4%,74% 20%,89% 14%,86% 31%,100% 35%,88% 47%,98% 59%,81% 63%,85% 79%,68% 74%,60% 91%,50% 78%,40% 91%,32% 74%,15% 79%,19% 63%,2% 59%,12% 47%,0% 35%,14% 31%,11% 14%,26% 20%,30% 4%,42% 14%)`
- Content: day of month (2 digits), Archivo 900, color red, centered (`display:grid; place-items:center`).
- Background alternates by list index: even → mint, odd → yellow. Single Event hero burst is always mint.
- Placement: absolute, top-left over the poster.

| Context | Desktop size / font / offset | Mobile size / font / offset |
|---|---|---|
| Home destacados card | 52px / 17px / 10px | 44px / 14px / 8px |
| Agenda card | 54px / 18px / 10px | 48px / 15px / 8px |
| Related card | 44px / 14px / 10px | 38px / 12px / 8px |
| Single Event hero | 74px / 24px / −14px (breaks out of poster) | 58px / 18px / −12px |
| "A la gorra" stamp (Festejá band) | 70px / 11px text, mint, `top:-18px; right:32px` | 60px / 9.5px, `top:-16px; right:16px` |

### 1.9 Entrada-libre stamp [GLOBAL · Agenda cards]

- Shown only when `entrada` is "Entrada libre" or "A la gorra".
- Black circle (`border-radius:50%`), white Archivo 800 uppercase text, `rotate(8deg)`, bottom-right of poster.
- Desktop 58px / 9.5px text / 10px offset · Mobile 50px / 8.5px / 8px offset.

### 1.10 Category tags [GLOBAL]

- Archivo 800 uppercase, solid fill, no border, square.
- Color by category: **mint** → Cine, Fiesta, Recreación · **yellow** → Música en vivo, Festival.
- Cards: 10px text, padding `3px 7px` (mobile 9.5px, `3px 6px`).
- Single Event: 11px, padding `4px 10px` (mobile 10.5px, `4px 9px`).

### 1.11 Filter chips [GLOBAL pattern · used on Agenda]

- Archivo 800 12px uppercase, 2px ink border, padding `8px 16px` (mobile 11.5px, `8px 14px`).
- Active: yellow fill. Inactive: transparent. V1 labels: Todos · Música en vivo · Cine · Recreación · Fiesta · Festival.
- Desktop: wrap, gap 10px. Mobile: single horizontal scrolling row, `nowrap`, gap 8px, bleeds to edges.

### 1.12 Buttons / CTA variants [GLOBAL]

All: Archivo, uppercase, letter-spacing .03em, square corners, no shadow.

| Variant | Style | Used for |
|---|---|---|
| **Primary (red)** | bg red, text white, Archivo 900 13.5px, padding `16px 28px` | Comprar entradas / Reservar |
| **Primary compact (red)** | bg red, Archivo 800 12px, padding `10px 16px` | Header WhatsApp (desktop) |
| **Dark (ink)** | bg ink, text white, 2px ink border, Archivo 900 13.5–14px, padding `16px 32px` / `18px 30px` | Ver toda la agenda · Escribinos por WhatsApp · Sumarme |
| **Secondary (outline)** | transparent, 2px ink border, ink text, Archivo 800 13–13.5px, padding `14px 28px` (Compartir) / `11px 20px` (Seguir) | Compartir · Seguir |
| **Outline on red/dark** | 2px paper border, paper text | Cerrar (mobile menu) |
| **Text link** | Archivo 800 12–13px uppercase, `border-bottom:2px solid ink` or none | "Ver agenda completa →", "← Volver a la agenda" |

Mobile: all primary / dark / secondary action buttons become **full-width**, centered text, padding 14–16px. Exception: Instagram "Seguir" stays inline next to its heading.

### 1.13 Comprar vs Reservar [GLOBAL rule · rendered on Event]

- The primary CTA label is **data-driven per event** (`ctaLabel`), same red Primary style in both cases — no visual difference between states.
  - **"Comprar entradas"** → ticketed events (Passline or priced entry).
  - **"Reservar"** → free, "a la gorra", or registration-based events.
- V1 mapping: Cine Club Saberes, Bubis Vagins, Pop, Lapoana, Encuentros Flamencos → Comprar entradas. Kitty Cumbia, Sindicato del Cuero, Metegol, Parri y Veredazo → Reservar.
- No backend logic defined in V1. Link targets TBD.

### 1.14 Event card [GLOBAL component]

Anatomy (top → bottom):
1. **Poster container** — `position:relative; width:100%; aspect-ratio:<native>; overflow:hidden; background:#e7e3d9`. Poster fills it.
2. **Date burst** — top-left (§1.8).
3. **Entrada-libre stamp** — bottom-right, Agenda variant only (§1.9).
4. **Body** — padding `14px 16px 16px` (Agenda `16px 18px 18px`), flex column, gap 8–9px:
   - Row: meta "WEEKDAY · HH:MM" (left, opacity .6) + category tag (right), space-between.
   - Title (Archivo 900 uppercase).
   - Subtitle (Agenda variant only).
   - Footer row above `1px dashed ink`: entrada text in red (Home: entrada only; Agenda: "La Casa del Árbol" left at .6 opacity + entrada right).
- Card frame: 2px ink border, white body background, entire card clickable → Single Event.

Variants:

| Variant | Where | Contents |
|---|---|---|
| **Featured** | Home destacados | poster · burst · meta+tag · title · entrada |
| **Full** | Agenda | poster · burst · stamp · meta+tag · title · subtitle · venue+entrada |
| **Compact** | Event "También en la agenda" | poster · burst · meta · title (no tag, no entrada; padding `14px 16px`) |

### 1.15 Poster aspect-ratio rule [GLOBAL]

- **One poster asset per event**, reused across Home, Agenda, Single Event and Related. Asset ID = event `id`.
- The container takes the event's `aspect` value; the poster is displayed **complete — no crop, no recolor, no overlay other than burst/stamp**.
- All 9 V1 posters are standardized to **4:5**. Store aspect per event so future non-4:5 posters render natively (the Agenda masonry depends on this).
- Mobile uses the same asset and ratio; no separate mobile crop.

### 1.16 Image crop rules [GLOBAL]

| Image type | Fit | Ratio | Notes |
|---|---|---|---|
| Hero photos | `object-fit:cover`, centered | container-driven (see §2.1) | No distortion; subject centered horizontally, headroom for sticky header |
| Event posters | Full poster visible (container ratio = poster ratio) | native (4:5 in V1) | Never crop art |
| Instagram tiles | `object-fit:cover`, centered | 1:1 | Square crop from real post |
| Empty / loading | `#e7e3d9` fill | — | |

### 1.17 Newsletter [GLOBAL component · 2 instances]

**Instance A — Home band (light)**
- Desktop: eyebrow "Comunidad" + H2 left; form right (`flex:1; max-width:460px; min-width:280px`). Input and button joined: input 2px ink border with `border-right:none`, padding `14px 16px`, Work Sans 14px, white bg; "Sumarme" dark button, padding `0 22px`.
- Mobile: stacked; heading block then form as a **vertical join** (input with `border-bottom:none`, then full-width Sumarme button, padding 14px).

**Instance B — Footer "Sumate" (dark)** — see §1.18.

### 1.18 Footer [GLOBAL]

Content (identical on every page, do not change): full logo (symbol + "La Casa del Árbol" wordmark, paper) · **Navegación** (Agenda, Saberes +60, Reservas, Alquilá, Tienda, La Casa) · **Visitanos** (Av. Córdoba 5217, Palermo · 11 4038 5603 · @_lacasadelarbol_) · **Sumate** (copy "Enterate primero de la agenda del mes." + email + OK) · legal row (© 2026 La Casa del Árbol · Palermo, Buenos Aires).

Shared: bg ink, text paper, `border-top:4px solid red`; column labels yellow; Sumate copy and legal text mint; inactive nav items opacity .6.

**Desktop**
- Max-width 1440, padding `56px 32px 28px`.
- 4-column grid `1.3fr 1fr 1fr 1.2fr`, gap 40px: Logo (130×82) · Navegación · Visitanos · Sumate.
- Sumate form: joined row, input 1px paper border transparent bg padding `10px 12px`; red "OK" button padding `0 16px`.
- Legal row: 1px `rgba(252,250,246,.2)` top border, padding `18px 32px`, space-between on one line.

**Mobile** (final approved composition)
- Padding `44px 24px 28px`, vertical stack, gap 36px:
  1. Full logo, 104×66, left.
  2. **Two-column grid** `1fr 1fr`, gap 20px: Navegación | Visitanos.
  3. Sumate, full width: copy (max-width 340px), then a **horizontal row** — input `flex:1` (padding `13px 14px`) + red "OK" (padding `13px 22px`, fixed width), gap 10px.
- Legal row: padding `16px 20px`, stacked (two lines, gap 4px).

### 1.19 Instagram grid [GLOBAL pattern · currently Home only]

- Square tiles (`aspect-ratio:1/1`), 2px ink border, cover crop.
- Reel indicator: small black square with white "▶", top-right (desktop 20px at 8px offset; mobile 18px at 6px).
- **Desktop:** 6 columns, gap 16px. **Mobile:** 3 columns × 2 rows, gap 8px.
- V1: `ig-sept` is the only real image; the other 5 remain placeholders until real posts are supplied.

### 1.20 Touch targets [GLOBAL · Mobile]

- Minimum hit area **44×44px** for every interactive element.
- Already compliant as designed: Menú / Cerrar (min-height 44), full-width CTAs (≥48), mobile menu rows (~60), carousel arrows (40px visual — extend hit area to 44), carousel dots (visual 4px bar inside a `10px 5px` padded hit wrapper).
- Elements whose **visual** size is under 44px and need an invisible hit-area extension (padding / pseudo-element) **without changing their look**: filter chips, Instagram "Seguir", "← Volver a la agenda", footer nav/contact items, cards' inner links if any.

### 1.21 Breakpoints and stacking rules [GLOBAL]

Approved layouts exist at two widths: **Desktop** (designed to 1440 max, fluid down) and **Mobile** (designed at 390, valid 320–430).

| Range | Layout |
|---|---|
| `≥ 1024px` | Desktop layout |
| `≤ 767px` | Mobile layout |
| `768–1023px` | **Not designed in V1.** Implement with the desktop layout and these fluid fallbacks only: Home destacados 4→2 cols, Agenda masonry 3→2 cols, Event related 3→2 cols, Instagram 6→3 cols, footer 4→2 cols, Event poster/info grid remains 2-col. Flag any visual problem for design review rather than improvising. |

Stacking rules (desktop → mobile):
- Header: inline nav + WhatsApp → symbol + Menú button; nav moves into full-screen overlay.
- Multi-column card grids → horizontal snap scroller (Home, Related) or single-column feed (Agenda).
- Side-by-side heading + action rows → heading stacked, action full-width below (except Instagram "Seguir", which stays inline).
- Two-column Event layout → poster first, then info.
- Metadata 3-col grid → 3 stacked label/value rows.
- Footer 4-col → logo / 2-col (Nav | Visitanos) / full-width Sumate / stacked legal.
- Joined input+button forms: Home newsletter stacks vertically; footer Sumate stays horizontal.

---

## 2. HOME-SPECIFIC RULES [HOME]

Section order (both breakpoints): Header → Hero carousel → Eventos destacados del mes → ¡Festejá en el Árbol! → Seguinos en Instagram → Newsletter (Comunidad) → Footer.

### 2.1 Hero carousel

- **Purely visual:** full-bleed photo only. No headline, copy, or CTA overlaid.
- 5 slides, in order: `hero-agenda` · `hero-saberes` · `hero-reservas` · `hero-alquila` · `hero-lacasa`.
- Background ink behind; section ends with 2px ink rule.
- Controls: prev/next square buttons (paper .85, ink "‹ ›" Archivo 900), vertically centered; dot row bottom-center. Active dot = red bar 24×4px, inactive = paper bar 10×4px.
- Auto-advance every **6s**, loops. Arrows and dots navigate directly. (Recommended for implementation: pause on hover/focus and respect `prefers-reduced-motion`.)

| | Desktop | Mobile |
|---|---|---|
| Height | `82vh`, min 640px, max 920px | `62vh`, min 420px, max 560px |
| Width | full viewport (not constrained to 1440) | full viewport |
| Arrows | 36×36px, 20px from edges, 15px glyph | 40×40px, 10px from edges, 16px glyph |
| Dots | 20px from bottom, gap 8px | 14px from bottom, gap 6px, padded hit area |
| Crop | cover, centered | cover, centered (same asset; separate mobile crop optional later) |
| Swipe | — | Add horizontal swipe in implementation (arrows remain) |

### 2.2 Eventos destacados del mes

- Eyebrow "Septiembre 2026" (red) + H2. Shows events with `featured:true` (V1: Cine Club Saberes, Pop, Lapoana, Parri y Veredazo) using **Featured card** variant.
- **Desktop:** header row with heading left and "Ver agenda completa →" text link right; 4-column grid, gap 24px; centered dark button "Ver toda la agenda" below (36px gap).
- **Mobile:** heading stacked, text link removed; cards in a **horizontal scroll-snap row** (`scroll-snap-type:x mandatory`, each card `flex:0 0 68%`, snap start, gap 14px, edge-bleed); full-width "Ver toda la agenda" dark button 24px below.

### 2.3 ¡Festejá en el Árbol!

- Full-width red band, paper text. Eyebrow "Cumpleaños · Eventos privados" in yellow + Anton H2. Mint "A la gorra" burst breaking the top edge (right side).
- **Desktop:** heading left, dark "Escribinos por WhatsApp" button right, wraps if needed.
- **Mobile:** stacked, button full-width.

### 2.4 Instagram — see §1.19

- White section background. Eyebrow "@_lacasadelarbol_" + H2 "Seguinos en Instagram" + outline "Seguir".
- Desktop: header row with Seguir right. Mobile: eyebrow on its own line, then H2 + Seguir on one row.

### 2.5 Newsletter — see §1.17 Instance A.

---

## 3. AGENDA-SPECIFIC RULES [AGENDA]

Order: Header → Page header (eyebrow "Agenda cultural" + H1 month + filter chips) → Event grid → Footer.

### 3.1 Page header
- H1 is the month name ("Septiembre"), Anton. Filter chips per §1.11 below it (28px desktop / 22px mobile).

### 3.2 Event grid

**Desktop — masonry**
- CSS multi-column masonry: `column-count:3; column-gap:24px`; each card `break-inside:avoid; margin-bottom:24px`.
- **Full card** variant (§1.14). Card heights vary naturally with each poster's native ratio and text length; do not force equal heights or crop posters to align rows.
- Order: chronological by date within the month (V1 source order).

**Mobile — poster feed**
- Single column, full gutter width, vertical gap 22px.
- Same Full card variant at mobile sizes (burst 48px, stamp 50px, title 17px). Posters at native ratio, full width.

---

## 4. SINGLE EVENT-SPECIFIC RULES [EVENT]

Order: Header → "← Volver a la agenda" → Main (poster + info) → También en la agenda → Footer.

### 4.1 Main

**Desktop**
- Back link row: padding `24px 32px 0`.
- 2-column grid `.85fr 1.15fr`, gap 56px.
- **Left:** poster at native ratio, 2px ink border; mint burst 74px offset `-14px/-14px` (breaks outside the poster corner).
- **Right, in order:** category tag → H1 title (18px above / 24px below) → metadata grid → description → secondary paragraph → CTA row.
- Metadata grid: 3 equal columns **Cuándo / Dónde / Entrada**; label Archivo 800 10.5px red, value Archivo 900 14px uppercase; cells padding `16px 18px`; 2px ink top/bottom, 1px dashed column dividers; 28px below.
  - Cuándo format: `WEEKDAY DD/09 · HH:MMhs`. Dónde: "Av. Córdoba 5217".
- CTA row: Primary red (Comprar entradas / Reservar, §1.13) + Secondary outline "Compartir", gap 14px, wraps.

**Mobile**
- Back link padding `18px 16px 0`.
- Stacked: poster full width (burst 58px at `-12px`), 24px gap → tag → H1 (2.1rem) → metadata as **3 rows** (label left red 10px, value right-aligned 12.5px, row padding `13px 2px`, dashed row dividers) → paragraphs → CTAs **stacked full-width** (Primary then Compartir, gap 10px).

### 4.2 También en la agenda
- Shows 3 other events (V1: first 3 events excluding the current one), **Compact card** variant.
- **Desktop:** 3-column grid, gap 24px.
- **Mobile:** horizontal scroller, cards `flex:0 0 62%`, gap 14px, heading and first card aligned to 16px gutter.

---

## 5. REUSABLE COMPONENT INVENTORY

**Global components**
1. `SiteHeader` — desktop bar / mobile bar (props: active nav item)
2. `MobileMenu` — full-screen red overlay
3. `SiteFooter` — desktop 4-col / mobile stacked (fixed content)
4. `EventCard` — variants: `featured`, `full`, `compact`
5. `PosterFrame` — native-ratio poster container (prop: `aspect`)
6. `DateBurst` — props: day, size, bg (mint/yellow)
7. `EntradaStamp` — circular rotated stamp (conditional)
8. `CategoryTag` — props: label, color (mint/yellow); size `card` / `large`
9. `FilterChip` / `FilterChipRow` — props: label, active
10. `Button` — variants: `primary`, `primary-compact`, `dark`, `secondary`, `outline-light`, `text-link`
11. `EventCTA` — Primary Button with label from `ctaLabel` (Comprar entradas / Reservar)
12. `NewsletterForm` — variants: `band-light` (Home), `footer-dark`
13. `SectionHeading` — eyebrow + Anton heading (+ optional right-side action)
14. `InstagramGrid` / `InstagramTile` — prop: isReel
15. `HorizontalScroller` — mobile edge-bleed scroll row (optional snap)

**Page-specific components**
- Home: `HeroCarousel`, `FestejaBand`
- Agenda: `AgendaHeader` (month H1 + chips), `AgendaMasonry` (desktop columns / mobile feed)
- Single Event: `EventHero` (poster + info), `EventMetaGrid` (3-col desktop / rows mobile), `RelatedEvents`

**Event data model used by the design** (per event): `id` (also poster asset ID) · `weekday` · `day` · `time` · `category` · `tagColor` (mint|yellow) · `title` · `subtitle` · `description` · `entrada` · `aspect` · `img` · `ctaLabel` (Comprar entradas|Reservar) · `featured` (bool). Derived: `entradaLibre` = entrada ∈ {"Entrada libre", "A la gorra"}; burst color alternates by list index.

---

## 6. OUT OF SCOPE FOR V1
- Pages not designed: Saberes, Reservas, Tienda, Contacto, La Casa.
- Tablet layout (see §1.21 fallback).
- Hover/focus states beyond link color, filter functionality, CTA destinations, newsletter submission, share behavior, carousel transitions.
- Remaining 5 Instagram images (placeholders).

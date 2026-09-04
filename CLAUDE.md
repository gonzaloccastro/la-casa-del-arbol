\# La Casa del Árbol — Web \& Ticketing



\## Project



New WordPress website for La Casa del Árbol, an independent cultural center in Palermo, Buenos Aires.



The existing production domain is currently being used as the development environment. It is password protected and configured as noindex.



Site:

https://lacasadelarbol.org/



\## Technical architecture



\- WordPress is the CMS.

\- Astra is the parent theme.

\- Custom child theme: `la-casa-del-arbol`.

\- Gutenberg is the primary content editor.

\- Do not use Elementor or another page builder.

\- Use `theme.json`, Gutenberg patterns, templates, PHP and CSS.

\- Keep editorial content editable in WordPress.

\- Do not hardcode page copy into PHP templates unless technically necessary.

\- Keep `functions.php` small and modular.

\- Avoid unnecessary JavaScript and third-party dependencies.



Future architecture:



\- WooCommerce will handle orders, checkout, stock and customers.

\- Payway will be the payment processor.

\- A custom plugin named `casa-eventos` will contain event/ticketing business logic.

\- Business logic must never live in the theme.

\- Event CPT will map to a hidden WooCommerce variable product.

\- Ticket types will map to WooCommerce variations.

\- WooCommerce will remain the source of truth for ticket stock and orders.

\- V1 does not include QR codes or check-in.



Do not implement the ticketing architecture until explicitly requested.



\## Current development milestone



Build only the first visual MVP:



1\. Global visual system

2\. Header

3\. Footer

4\. Home

5\. Events archive

6\. Single event page

7\. Reusable Gutenberg patterns/components

8\. Mobile responsive behavior



Do not implement yet:



\- WooCommerce integration

\- Payway

\- casa-eventos plugin

\- reporting

\- QR/check-in

\- user roles

\- CRM

\- newsletter integration

\- complex booking systems



\## Visual direction



The site should feel like La Casa del Árbol, not like a generic WordPress theme or ecommerce website.



Primary visual references:



\- Current La Casa del Árbol agenda graphics

\- Current Instagram: @\_lacasadelarbol\_

\- 2024 La Casa del Árbol logo



Secondary references:



\- Institutional presentation documents for history and conceptual language



When references conflict, prioritize the most recent material.



Visual characteristics:



\- editorial cultural-poster language

\- strong typography

\- saturated red as primary accent

\- off-white/paper backgrounds

\- mint and yellow secondary accents

\- straight borders rather than generic rounded cards

\- modular poster/grid composition

\- occasional stickers, bursts and graphic shapes

\- each event may retain its own poster/artwork identity

\- strong visual hierarchy

\- playful but controlled composition



Avoid:



\- generic SaaS design

\- generic WooCommerce product grids

\- excessive rounded cards

\- excessive shadows

\- generic Astra starter-template appearance

\- visually cluttered layouts that harm readability



\## Editing philosophy



WordPress editors must be able to update normal content without touching code.



Use:



\- Gutenberg for copy, images and editorial page content

\- patterns for repeatable layouts

\- theme templates for presentation structure

\- CSS/theme.json for visual consistency



The theme controls presentation.

WordPress controls editorial content.



\## Public navigation



Initial main navigation:



\- Eventos

\- Saberes

\- Reservas

\- Eventos privados

\- Tienda

\- La Casa

\- Contacto



During the first visual MVP, Tienda may remain inactive or omitted.



\## Reservations



Do not build a booking engine.



Reservations, birthdays and private events are initially handled through WhatsApp.



The website should explain the service and provide a strong WhatsApp CTA.



\## Performance



Prioritize:



\- semantic HTML

\- responsive design

\- accessibility

\- minimal JS

\- optimized assets

\- Core Web Vitals

\- reusable CSS

\- no unnecessary plugins



\## Workflow



Before making large changes:



1\. Inspect the existing code.

2\. Explain the intended change.

3\. Keep changes scoped.

4\. Do not replace working architecture without reason.

5\. Do not add dependencies without approval.



After each implementation:



\- list files changed

\- explain architectural decisions

\- state what remains editable from WordPress

\- identify unfinished work

\- mention any assumptions


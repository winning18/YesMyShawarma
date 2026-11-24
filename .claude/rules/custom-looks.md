---
paths:
  - "resources/views/themes/**"
  - "app/Services/Content/WebsiteThemeService.php"
  - "app/Services/Content/ThemeHomeService.php"
  - "resources/views/layouts/customer.blade.php"
---

# Custom looks: the playbook

How Solusys Digital builds and changes a client's custom website design (owner, 2026-11-24).
Read this, and the look's own `DESIGN.md`, before touching any look.

## What a custom look is

A fourth (fifth…) website look built inside Express POS, exactly like Classic, Photo-led and
One-page (schema.md's "Website content" → Website look). The client picks it under Website
content → Website look. Because it's the same system, the client keeps everything: menu and
prices from the dashboard, cart, checkout, Paystack, tracking, customer accounts, website
content, search ranking and old links, the home-screen app, visit statistics, and every update.

**Default answer to "the client wants a custom design": build a custom look.** A separate site
calling a Storefront API is only for a client who must keep an existing website (e.g.
WordPress) or wants something beyond a restaurant site. That API doesn't exist yet; raise it as
its own project, never as part of a look.

## Starting one

Start a **new chat** per custom-look project (long chats get summarised and lose detail). Bring:

- the client (install slug) and whether the look is **theirs alone or open to all clients**;
- the designer's files or reference sites, and any animation references (video or links);
- pages the design covers (a look changes header, home, menu and item page; everything else is
  shared, wearing the look's font and the brand colours).

Before any code, per the owner's standing rules: ask for **typefaces** (recommend options and
say how each fits), confirm **colours come from the client's three brand colours**, ask for
**tone, audience and inspiration** if missing, push back where a request costs more than it
gives, then write the plan and **wait for approval**. Animation libraries are dependencies:
ask first.

## Building it

- **Folder:** `resources/views/themes/<key>/` — `header.blade.php`, `home.blade.php`,
  `menu/index.blade.php`, `menu/show.blade.php` as needed. `WebsiteThemeService::view()` falls
  back to Classic for anything not overridden. Key: lowercase letters, e.g. `deboyes`.
- **Register it** in `WebsiteThemeService::THEMES` (label, summary, font) and keep the lists that
  name every look in step: the portal's `config/provisioning.php` `themes`, and the
  `classic|photo|onepage…` check in `deploy/provision.sh` (reinstall it on the server after).
  A look that's one client's alone needs a way to hide it from other businesses: propose it
  (a setting) and get approval; don't invent it inside the look.
- **No business written into code** (product rule 1): the client's name, wording, phone numbers
  and photos come from settings and the menu, never from the look's files. The client is named
  only in `DESIGN.md`.
- **Colours: brand tokens only, never a fixed hex.** Background `brand-surface` with
  `brand-on-surface` text; `brand-highlight` for the main colour on the background; main colour
  `brand-yellow` with `brand-on-primary` text; accent `brand-red` with `brand-on-accent`;
  `brand-button`/`brand-on-button` for buttons and badges on white; `brand-*-text` for brand
  coloured text on white. Errors stay the fixed red (schema.md's "Errors"). Never `xpos-*`.
- **Fonts:** self-hosted latin `.woff2` in `public/fonts`, an `@font-face` and a
  `.theme-<key> { --font-heading: … }` block in `resources/css/app.css`; headings use
  `font-heading`. A browser only downloads a face a page uses, so other clients never load it.
- **Images:** `x-product-image` (WebP, lazy below the fold) and the sizes in `ImageRules`; if the
  design needs a new image slot or shape, add it there so its upload shows the right size card.

## Animation

- Plain CSS and a few lines of JavaScript first (fades, reveals on scroll, hover effects,
  tickers, parallax). Animate only `transform` and `opacity`, so cheap phones stay smooth.
- Lottie (illustrations from After Effects) or GSAP (timed scroll sequences) only with the
  owner's approval as a dependency, and loaded **only by that look** (its own Vite entry,
  included only from the look's views), never in `customer.js`.
- Ordering always works first: menu and Add to cart usable before any animation has loaded.
- `prefers-reduced-motion`: every animation has a still version.
- Hero video: short, silent, compressed, with a still image first, large screens only.
- No 3D, WebGL or game-like effects: too heavy for the budget and for cheap Android phones.

## Before it goes live

- Performance budget (CLAUDE.md): under 2 seconds to interactive on throttled 3G; check it.
- `php artisan test` (WebsiteThemeTest already draws home, menu and item page for every look in
  `THEMES`); add tests for anything the look adds.
- Browser check at phone and desktop width, with screenshots, on a cheap-phone profile.
- On staging, send the client the signed **Preview** link (Website content → Website look →
  Preview): they see the new look on their own site while customers still see the current one.
  Release only on their approval, following CLAUDE.md's release routine.
- Write or update the look's `DESIGN.md` (template: `resources/views/themes/DESIGN-TEMPLATE.md`).

## Changing it later

1. Start a chat with the request. Read the look's `DESIGN.md` and code first.
2. Small adjustments: change the look in place, on staging, preview link to the client, release
   on approval, add a line to `DESIGN.md`'s change log.
3. A full redesign: build it as a new look next to the old one (`<key>-v2`), switch with the
   Website look setting, and remove the old one only after the client has confirmed and the new
   one has run for a while. Switching back is one setting.
4. A change to one client's look must not change any other look; the tests and a browser check
   of Classic confirm it.

Custom design and every later adjustment are paid work, outside the subscription; agree the
scope first. Prices are an open decision (CLAUDE.md).

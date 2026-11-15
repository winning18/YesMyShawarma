---
paths:
  - "app/Http/Controllers/HomeController.php"
  - "app/Http/Controllers/MenuController.php"
  - "app/Http/Controllers/FaqController.php"
  - "app/Http/Controllers/RobotsController.php"
  - "resources/views/home.blade.php"
  - "resources/views/menu/**"
  - "resources/views/faq.blade.php"
  - "app/Http/Controllers/LlmsController.php"
  - "app/Services/Content/**"
---

# AEO (Answer Engine Optimization)

Deliberate business decision: AI answer engines (ChatGPT, Claude, Perplexity, Google's AI
features, etc.) citing or training on this site's menu, hours, and policies is wanted, not
merely tolerated. Every piece below exists because of that choice — don't remove any of it as
"just SEO cruft" without checking this file first.

## robots.txt

On a POS-only install (no Website module, Express POS step 3c) there's no site to index:
robots.txt disallows everything and `/sitemap.xml` and `/llms.txt` answer 404.

`RobotsController::AI_CRAWLERS` names known AI crawlers (`GPTBot`, `ClaudeBot`, `PerplexityBot`,
`Google-Extended`, `CCBot`, etc.) with their own explicit `Disallow:` (allow) rule in
production, ahead of the blanket `User-agent: *` rule. This is deliberately redundant with the
wildcard rule today — the point is that these stay allowed even if the wildcard rule is ever
tightened later, since removing an AI crawler's own named line is a visible, on-purpose edit,
where a wildcard change alone wouldn't be. Non-production still blocks everything, AI crawlers
included — staging is test/demo data and must never be cited as if it were real.

## Structured data (schema.org JSON-LD)

Each is built from the exact same data the page itself renders, in the controller, never
duplicated in the Blade view — so the visible content and the structured data can never say
different things:

- **`HomeController::restaurantSchema()`** — one `Restaurant` block per active branch, hours
  from `WorkingHoursService` (the real admin-set weekly schedule), never the inert flat
  `opens_at`/`closes_at` columns (schema.md's Branches section explains why those are inert).
- **`MenuController::menuSchema()`** — a `Menu`/`MenuSection`/`MenuItem` block for the whole
  `/menu` page, so an answer engine can read every item and price in one request rather than
  crawling each item's own page.
- **`MenuController::productSchema()`** — `Product`/`Offer` on each item's own `/menu/{item}`
  page.
- **`FaqController`** — the FAQ questions (`faq_items`, edited under Website content,
  `FaqService`) are the single source for both the visible `<details>/<summary>` accordion and
  the `FAQPage`/`Question`/`Answer` structured data; the controller builds both from the same
  list, the Blade view never hardcodes question/answer text of its own.

Restaurant data's `addressLocality` / `addressRegion` / `servesCuisine` come from the Business
profile (city, region, what you serve) and are left out when not filled in.

Adding a new customer-facing page with citable facts (a new static page, a promotions page,
etc.) should follow the same shape: build the schema from the same data the page renders, in
the controller, and add it here.

## `/llms.txt`

A plain-text summary for AI crawlers, in the emerging `llms.txt` convention — served by
`LlmsController` from `SitePageService::llmsText()` (Express POS step 2c; it used to be the
static `public/llms.txt`, deleted — a static file would shadow the route on nginx). The business
can write its own under Website content (the `llms` site page, `:url` filled with this
install's address — Yes My Shawarma's was copied in); left empty, one is built from the profile
and links only the pages that exist.

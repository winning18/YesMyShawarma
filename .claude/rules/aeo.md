---
paths:
  - "app/Http/Controllers/HomeController.php"
  - "app/Http/Controllers/MenuController.php"
  - "app/Http/Controllers/FaqController.php"
  - "app/Http/Controllers/RobotsController.php"
  - "resources/views/home.blade.php"
  - "resources/views/menu/**"
  - "resources/views/faq.blade.php"
  - "public/llms.txt"
---

# AEO (Answer Engine Optimization)

Deliberate business decision: AI answer engines (ChatGPT, Claude, Perplexity, Google's AI
features, etc.) citing or training on this site's menu, hours, and policies is wanted, not
merely tolerated. Every piece below exists because of that choice — don't remove any of it as
"just SEO cruft" without checking this file first.

## robots.txt

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
- **`FaqController::ITEMS`** — the single source for both the visible `<details>/<summary>`
  accordion and the `FAQPage`/`Question`/`Answer` structured data; the controller builds both
  from the same array, the Blade view never hardcodes question/answer text of its own.

Adding a new customer-facing page with citable facts (a new static page, a promotions page,
etc.) should follow the same shape: build the schema from the same data the page renders, in
the controller, and add it here.

## `public/llms.txt`

A hand-written plain-text summary for AI crawlers, in the emerging `llms.txt` convention —
served as a static file (same mechanism as `favicon.ico`, no controller). Keep its links current
when a page in the "Ordering"/"About"/"Policies" sections is renamed or removed.

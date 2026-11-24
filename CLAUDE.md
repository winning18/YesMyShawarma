# CLAUDE.md

**Express POS** — a point-of-sale and ordering system for food businesses, sold by Solusys
Digital. Read this fully before making any change.

Detailed reference lives in `.claude/rules/` and loads automatically when you work on the
matching files. Do not duplicate that content here.

---

## What this is

Staff take orders at a counter POS; orders land on a live kitchen board in real time. Owners and
managers run branches, staff, menu, stock and reports from an admin dashboard. Optional modules
add a customer ordering website, delivery with riders, delivery platforms (Bolt Food), promotions,
freebies, a market list with profit, SMS and more.

**One codebase, many businesses.** Every client runs this same code. What differs between them
lives in settings and module switches, never in separate copies of the code. A change made here
reaches every business on its next deploy.

**Yes My Shawarma is install number one** — the restaurant this was built for, and still a live
client: Accra, Ghana, three branches (Ga Odumase is the main branch — its address heads general
reports; Pokuase Y-Junction; GIMPA, Gimpa Green Hill Campus). It has every module switched on and
must keep working exactly as it does through every change. Its staff and customers should never
notice the product work unless a change adds something to them on purpose.

**Who it's for (MVP):** food and hospitality businesses in Ghana — restaurants, cafés, bars, food
trucks, caterers. Mobile data is often slow, so page weight is a product requirement, not polish.

**Product brief and MVP plan** (owner-approved, 2026-11-15):
https://claude.ai/artifact/3mX21W2A3ZL9EYnRhu3Fpi (tab "MVP plan").

**Future:** a React Native app will consume this backend. Assume a second client is coming.

---

## Product rules

These come before everything else in this file.

1. **No business is written into the code.** No business name, branch, menu item, category,
   prefix, phone number, address or amount that belongs to one client appears in code, views,
   seeders or tests' assumptions about production. It lives in settings or the database. Step 2
   moved the last of Yes My Shawarma's into settings and the database — don't add new ones.
2. **Every feature is either core or a module.** Core is always on: counter POS, kitchen board,
   menu with options and branch availability, branches, staff, roles and permissions, shifts and
   cash-up, sales reports, order history. Everything else is a module a business switches on.
   Decide which before building, and say so in the plan.
3. **A module switched off disappears completely** — its routes, menu items, POS options, page
   sections, report lines, scheduled jobs and notifications. Never a half-hidden feature that
   still runs underneath. Test it switched off as well as on. How: `App\Services\Modules\
   ModuleService` lists the modules (switches are `module_<key>` settings, owner-only Modules
   page); routes take `->middleware('module:<key>')` (404 when off); Blade wraps parts in
   `@module('<key>') … @endmodule`; services and scheduled jobs check
   `ModuleService::enabled()` themselves. A new install starts with every module off; the test
   suite runs with every module on (`MODULES_DEFAULT_ON` in phpunit.xml) unless a test switches
   one off.
4. **One client's request is built as a module or a setting**, never as a branch of the code or a
   copy. If only Yes My Shawarma wants something, it goes behind a switch that's on for Yes My
   Shawarma.
5. **Settings start with today's values.** When something moves into settings, Yes My Shawarma's
   current value is filled in automatically (a data migration), so nothing changes on the day.
   Such a migration must skip a client install: one with the `client_setup_at` setting
   (`ClientSetupService::SETUP_MARKER`, stored by `expresspos:new-client`). "The install has
   orders" alone no longer means Yes My Shawarma once a client is live.
6. **Branding.** Inside the app, staff and customers see the business's own name, logo and
   colours. The staff login page always shows EXPRESS POS, a Solusys Digital product. A
   business's customers never see "Express POS". The sign-in pages (`layouts/guest`, the
   `x-express-pos-logo` component, `auth/partials/sign-in-form`) use Express POS's own logo
   system — Midnight #101010, Kente Gold #FCD116, Forest Green #12A150, Signal Red #E23A3A
   (`xpos-*` Tailwind colours), Archivo Black Italic for the wordmark and headline (self-hosted,
   `public/fonts`), Figtree for text. Source: the owner's "Tap & Go · Logo system" and "POS
   sign-in screen" designs (2026-11-15). The form side is headed by the business's own logo and
   name (which install this is); the dark side carries Solusys Digital's social links
   (`x-solusys-social-links`). Never use the `xpos-*` colours on a business's screens.
7. **Every change is safe for Yes My Shawarma.** All modules stay on for Yes My Shawarma;
   switching modules off is only ever tested on staging and on other clients' installs.

### Release routine until the MVP (owner decision, 2026-11-15)

Express POS work stays on the `staging` branch and the staging server until the MVP is finished,
then goes to production in one release. Production (`Main`) stays as it was on 2026-11-15
(`d19f436`) apart from live fixes.

A fix or small request for live Yes My Shawarma in the meantime takes the fix route, never the
staging branch:

1. Branch from production: `git switch -c fix/<name> origin/Main`.
2. Make the fix there, with tests; run `php artisan test`.
3. Push the branch; the owner releases it with `git push origin fix/<name>:refs/heads/Main`.
4. Merge it into the Express POS work: `git switch staging && git merge origin/Main`, run the
   tests, push `staging`.

Never push `staging` to `Main` before the MVP release, and never put Express POS work on a fix
branch.

---

## Installs and editions

**One install per business** (owner decision, 2026-11-15): the same code, deployed separately
for each client, each with its own database, settings and subdomain under Solusys Digital
(`<client>.pos.solusysdigital.com`; the short `pos` subdomain is the owner's choice, 2026-11-20, and the portal is `admin.pos.solusysdigital.com`). One business can never see another's data. The same
deploy updates every install. Moving to one shared system for all clients is a later decision,
around ten clients — don't build for it now, but don't make it harder either (keep everything
branch-scoped, keep settings in the database).

**Addresses (owner, 2026-11-24, fixed):** every client's POS lives at
`<client>.pos.solusysdigital.com` and never moves off it. Only a client's customer website may have
its own domain (e.g. `deboyeskitchen.com`), served by the same install.

**Staging and sandboxes (owner, 2026-11-24):**
- Shared staging moves to `staging.pos.solusysdigital.com`: an ordinary install filled with the
  made-up "Express POS Demo" (`expresspos:demo-data`, data in `database/seeders/data/
  demo_business.php`), behind a password gate. `staging.yesmyshawarma.com` runs until the MVP
  release, then redirects there.
- A client's **sandbox** is made on demand from Express POS Admin at
  `<client>-staging.pos.solusysdigital.com` (one level, so the existing wildcard DNS covers it):
  a copy of their install on the newest code, customers' personal data replaced
  (`expresspos:scrub-sandbox`, refused on a live install), no live keys, a password gate, never
  backed up, deleted on demand or after 7 days (`deploy/sandbox.sh`, the portal's
  `clients:expire-sandboxes`). For custom-look work and support, never the live site.
- **At the MVP release** Yes My Shawarma becomes an ordinary install: its POS at
  `yesmyshawarma.pos.solusysdigital.com`, `yesmyshawarma.com` connected as its own website
  domain. Needs a written cut-over checklist first (database, Paystack webhook, Print Agent
  address, staff sign-in and push, rider subdomains).

**Brand (owner, 2026-11-24):** there is no default palette. Every client chooses its two brand
colours and uploads its logo before onboarding can go ahead (`expresspos:new-client` requires
them); Solusys staff can change them later from the client's page in Express POS Admin
(`expresspos:brand`), and the owner from Business profile. The code's fallback colours are
neutral greys, never another business's.

**Build before the first client is onboarded (owner, 2026-11-24):**
1. Own website domain: an optional website domain per client in Express POS Admin (DNS, SSL,
   the install answering on it), with the POS staying on its subdomain.
2. Paystack and SMS (Arkesel) keys per client from the portal, not by hand on the server.
3. Website content set up as part of onboarding (pages, FAQ, photos) for clients with a website.

**Images and errors (owner, 2026-11-24):** every upload's size rules live in `ImageRules` and
show on its field and in Help → Image sizes; big photos are shrunk on upload; receipt and damage
photos are deleted 24 hours after they're viewed and decided. Every error says what's wrong,
where (red outline, fixed red) and how to fix it. See schema.md's "Image uploads" and "Errors".

**Website looks (owner, 2026-11-24):** three themes built into the product, every one open to
every client: Classic (today's design, the default), Photo-led (Fraunces headings) and One-page
(Bricolage Grotesque headings). Chosen at onboarding (`expresspos:new-client --theme`) and by the
owner or GM under Website content → Website look, with a signed preview link first. Colours are
always the business's own. A theme changes only the header, home, menu and item page; every
other customer page is shared. See schema.md's "Website content". A fully custom design for one
client is later work and needs a customer API first. A client wanting a custom design (built by
Solusys) gets a custom look inside Express POS, per `.claude/rules/custom-looks.md`; start each
one in a new chat.

**Editions:**

- **Subscription** (the MVP): hosted by Solusys Digital, every module available, every update.
  Will also work offline once offline mode is built (straight after the MVP).
- **One-time** (later, not in the MVP): installed on the client's own computer, offline only,
  limited features. A separate packaging job — don't design MVP work around it.

Clients are set up by the owner (Solusys Digital) for now; there is no self sign-up. Billing for
Express POS itself is done manually, outside the app.

---

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel (PHP) |
| Database | MySQL 8, one database per install |
| Customer site + dashboards | Blade + Alpine.js, server-rendered |
| Real-time | Laravel Reverb |
| Auth (internal) | Laravel session guard |
| Auth (API / future app) | Laravel Sanctum |
| Permissions | spatie/laravel-permission, teams enabled, team = branch |
| Payments | Paystack (cards + MoMo) — a module |
| Queue | Redis, or database driver until volume justifies Redis |
| Images | Cloudinary or S3 behind Cloudflare |
| SMS | Arkesel (`ArkeselNotifier`), behind the `Notifier` contract — falls back to log-only (`LogNotifier`) if `ARKESEL_API_KEY` is unset. A module |
| Browser push | Web Push (`minishlink/web-push`, `WebPushNotifier`), behind the `PushNotifier` contract — falls back to log-only (`LogPushNotifier`) if VAPID keys are unset. See realtime.md's "Web Push" section |
| Error tracking | Sentry (`sentry/sentry-laravel`), wired into `bootstrap/app.php`'s `withExceptions()` — a no-op until `SENTRY_LARAVEL_DSN` is set (`config/sentry.php`). `send_default_pii` is off by default |

**No SPA.** No React or Vue on the customer site.

---

## Hard rules

1. **Study before changing.** On first contact with an unfamiliar area, read it in read-only
   mode and report what you found. Make no edits until explicitly told to.
2. **Maintain existing conventions.** Match the naming, structure, and patterns already in the
   codebase even if you would have chosen differently.
3. **Never invent schema.** Propose migrations and wait for approval. Do not silently add
   columns or tables.
4. **Business logic lives in service classes.** Controllers validate input, call a service,
   return a response. Nothing else. No logic in models or Blade.
5. **Never generate large data tables inline** (currency lists, zone seeds, region lists).
   Put them in `database/seeders/data/` and reference them.
6. **Money is integer minor units.** Store pesewas as `unsignedBigInteger`. Never floats.
   Currency is GHS throughout (Ghana only for now).
7. **Datetimes stored UTC**, displayed in `Africa/Accra`.
8. **Every write that changes an order writes an `order_events` row.** No exceptions.
9. **Ask before adding a dependency.** Prefer first-party Laravel and what is installed.
10. **Run `php artisan test` before declaring a task complete.**

---

## Identity model

Two separate identity stores with separate guards. **Do not merge these.**

**`customers`** — buyers. Phone-first. A row is created on first order whether or not the
person registers. An account is created later by setting a password on the existing row, so
guest order history carries over automatically. Guard: `customer`. No branch scope, no roles.
Identified by `phone`, unique, normalised to E.164.

**`users`** — staff, riders, managers, owners. Always authenticated, always branch-scoped.
Guard: `web` for staff/manager/owner, `rider` for riders — same table, two guards purely so a
staff and a rider login can coexist in one browser (see permissions.md's "Guards" section).
Roles via spatie with `branch_id` as the team key. A user may hold different roles at different
branches.

Merging these means every permission check must first ask "is this even an internal user?" —
the check that gets forgotten once and leaks a branch's revenue to a customer account.

---

## API-first

Only Blade consumes the API so far. Build it anyway.

- Every domain operation is a service class method callable from HTTP, console, or queue.
- `Api\` controllers return JSON resources. `Web\` controllers return Blade views.
  **Both call the same service.**
- Never put logic in a web controller that an API controller cannot reach.

This is what makes the React Native app a client rather than a rewrite.

---

## Performance budget

Customer-facing pages (website module):

- Under **2 seconds** to interactive on a throttled 3G Lighthouse profile
- Menu images: WebP/AVIF, `srcset`, lazy-loaded below the fold
- No JS framework on the customer site
- Cloudflare in front of everything
- PWA manifest and service worker: home-screen install, offline menu cache

---

## Build order: the MVP

The restaurant platform (foundation, operations, riders, administration, growth, reporting) is
built and live for Yes My Shawarma. The work now is turning it into Express POS. Do not start a
step before the previous one is released.

1. **Product rulebook** — this file.
2. **Business settings** — name, logo, colours, contact, prefixes, receipt text, the promotion's
   free item and the drinks category move into settings.
3. **Module switches** — a Modules page; every module can be switched off completely.
4. **POS-only mode** — with the website module off, the address opens the staff login.
5. **Express POS identity** — the EXPRESS POS login page; business branding inside.
6. **Installs per client** — a repeatable install per client on a Solusys Digital subdomain,
   daily backups, one deploy updating every install, the owner's admin account on each.
   Brought forward before the pilot (owner, 2026-11-20) as **Express POS Admin**: a separate
   app in its own private repo (`winning18/expresspos-admin`, at
   `admin.pos.solusysdigital.com`) where Solusys staff onboard clients. Version 1:
   onboarding only. It runs `deploy/provision.sh` (root-owned `expresspos-provision`, the only
   thing its worker may sudo), which calls `deploy/new-install.sh` and `expresspos:new-client
   --json`, and registers the install in `/etc/expresspos/installs` (the staging deploy updates
   everything listed there) and the nightly backups. One wildcard DNS record
   `*.pos.solusysdigital.com` covers every client.
7. **Several cashiers** — a till per cashier or terminal, each with its own float and cash-up.
8. **Direct printing** — receipts and kitchen tickets straight to Epson printers, cash drawer.
   8c. **Tables** (owner, 2026-11-20; the `tables` module): open bills on eat-in tables at the
   POS — rounds to the kitchen straight away, one bill settled at the end at a till, bills move
   to a free table, no till closes while its tables owe (schema.md's Orders → Tables).
9. **Optional tax** — VAT, NHIL and levies per business, off by default.
10. **Food vendor pilot** — the first paying client set up, trained and live.

Straight after the MVP: offline mode for the subscription edition.

**Current step:** 6 (Express POS Admin, brought forward), then 10 (food vendor pilot: De Boyes Kitchen). Step 10 tools built 2026-11-20 — `expresspos:new-client` (`ClientSetupService`), Help guides (staff quick-start, owner guide, go-live checklist), `deploy/new-install.sh` for a hand-set install on the staging code (updated by the staging deploy via the `EXPRESSPOS_EXTRA_INSTALLS` repo variable), nightly backups (`deploy/backup-databases.sh`), Reverb settings read at run time (`partials/reverb-config`) so one build serves every install; see `deploy/README.md`. Production Yes My Shawarma database backed up nightly since 2026-11-20 (`/etc/expresspos/backup.conf`). Step 9, optional tax, built 2026-11-20 (the `tax` module: Settings → Tax, inclusive or added on top, food only (delivery fees never taxed), `order_taxes` kept as at sale, Reports → Tax, TIN on receipts — schema.md's Orders → Tax). Step 8, direct printing, built 2026-11-15/20: 8a printers,
receipts, kitchen tickets and browser printing; 8b the Express POS Print Agent for Windows
(`print-agent/`, Go, unsigned for the pilot) — schema.md's Printing notes. Step 6 (installs per client) is the last step, after the
pilot (owner, 2026-11-15). Step 7, several cashiers, built 2026-11-15: 7a named
counters with a till each (schema.md's Shifts section), 7b Staff ID + PIN sign-in on counters
(`users.staff_code` like YMS-0042 from the business's initials, `users.pin` hashed; till staff
only, only on a set-up counter of their branch, 5 wrong tries lock 15 minutes; managers set PINs
on the Users page). Step 6 (installs per client) waits on the owner's hosting and DNS
decisions. Step 5, Express POS identity, built 2026-11-15. Step 1, this rulebook, approved 2026-11-15; step 2,
business settings, built 2026-11-15 (2a business profile, 2b menu and branch rules, 2c website
content); step 3, module switches, built 2026-11-15 in three parts (3a the switches, the Modules
page and seven modules; 3b promotions, customer SMS, Paystack, delivery and riders; 3c website);
step 4, POS-only mode, built with 3c (no Website module: `/` opens the staff login, the customer
site answers 404, error pages show no site links). Also built 2026-11-15: the home page strip
editor (Website content), and delivery fees (per km, minimum fee, minimum order) as settings.
<!-- Update this line as steps complete. It is the fastest signal of where the project is. -->

---

## Out of scope for the MVP

Do not build these. If a task seems to require one, stop and raise it.

- Loyalty points or stamp cards (the WhatsApp group reward is a flat discount, not loyalty)
- Live rider GPS tracking on a map
- Multi-currency or countries outside Ghana
- Table reservations
- A lead pipeline with stages (the customer list stays a list)
- Native mobile app
- Retail or service businesses (barcodes, variants, appointments, commission)
- The one-time, offline-only edition
- Self sign-up for clients, and billing for Express POS inside the app
- One shared system holding every client's data

---

## Open decisions

Flag these rather than assuming an answer.

- Prices: setup fee, one-time fee, monthly fee.
- Exactly which features the one-time edition leaves out.
- The food vendor's go-live date and first modules.
- Client contract, terms of service and data protection registration.
- Where client installs are hosted, and access to create subdomains on solusysdigital.com.

Resolved, no longer open: SMS provider (Arkesel, see Stack table above). Delivery fee model is
continuous distance pricing — haversine distance from branch × a flat rate per km
(`App\Services\Delivery\DeliveryFeeCalculator`). See `.claude/rules/schema.md` and
`.claude/rules/orders.md`. Riders hand in the cash delivery fees they collect from 2026-11-11;
from 2026-11-20 a rider cash-up at a till takes the food money too, and the owner chooses whether
fees are handed in (the business's income) or kept by riders (in no financial figure) —
payments.md's "Rider cash-ups". Rider pay itself is not tracked in the app, by choice.
"Inventory or stock depletion" was deliberately built, flagged and approved mid-project —
recipe-driven, per-branch, tied to order acceptance/cancellation. See `.claude/rules/schema.md`'s
"Recipes and stock deduction" section. "Promo types beyond percentage and fixed discount" was
likewise flagged and approved: a `buy_x_get_y_free` type exists alongside the original two. See
`.claude/rules/schema.md`'s "Promotions" section.

---

## Reference rules

These load automatically when you touch matching files. Read them before working in that area.

| File | Covers | Loads when working on |
|---|---|---|
| `.claude/rules/schema.md` | Tables, snapshotting, money | migrations, models, seeders |
| `.claude/rules/orders.md` | State machine, escalation, rider claim | order services, models, jobs |
| `.claude/rules/permissions.md` | Roles, matrix, scoping | policies, middleware, routes |
| `.claude/rules/realtime.md` | Channels, broadcast auth | events, listeners, channels |
| `.claude/rules/payments.md` | Paystack, webhooks, cash | payment services, webhook controller |
| `.claude/rules/custom-looks.md` | Building and changing a client's custom website look | website looks (`resources/views/themes/**`), theme services |

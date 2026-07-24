# CLAUDE.md

Multi-branch restaurant ordering platform. Read this fully before making any change.

Detailed reference lives in `.claude/rules/` and loads automatically when you work on the
matching files. Do not duplicate that content here.

---

## What this is

Customers order from a mobile-first website. Orders land in a branch dashboard in real time.
Riders claim and deliver them. Owners and managers run the business from an admin dashboard.

Replaces an existing WhatsApp-group ordering process and becomes the profitable direct channel
alongside Bolt Food.

**Context:** Accra, Ghana. Three branches (two live, one opening). Mobile data is often slow —
page weight is a product requirement, not polish.

**Future:** a React Native app will consume this backend. Assume a second client is coming.

---

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel (PHP) |
| Database | MySQL 8 |
| Customer site + dashboards | Blade + Alpine.js, server-rendered |
| Real-time | Laravel Reverb |
| Auth (internal) | Laravel session guard |
| Auth (API / future app) | Laravel Sanctum |
| Permissions | spatie/laravel-permission, teams enabled, team = branch |
| Payments | Paystack (cards + MoMo) |
| Queue | Redis, or database driver until volume justifies Redis |
| Images | Cloudinary or S3 behind Cloudflare |
| SMS | TBD — abstract behind a `Notifier` contract |

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
   Currency is GHS throughout v1.
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
Guard: `web`. Roles via spatie with `branch_id` as the team key. A user may hold different
roles at different branches.

Merging these means every permission check must first ask "is this even an internal user?" —
the check that gets forgotten once and leaks a branch's revenue to a customer account.

---

## API-first

Only Blade consumes the API in v1. Build it anyway.

- Every domain operation is a service class method callable from HTTP, console, or queue.
- `Api\` controllers return JSON resources. `Web\` controllers return Blade views.
  **Both call the same service.**
- Never put logic in a web controller that an API controller cannot reach.

This is what makes the React Native app a client rather than a rewrite.

---

## Performance budget

Customer-facing pages:

- Under **2 seconds** to interactive on a throttled 3G Lighthouse profile
- Menu images: WebP/AVIF, `srcset`, lazy-loaded below the fold
- No JS framework on the customer site
- Cloudflare in front of everything
- PWA manifest and service worker: home-screen install, offline menu cache

---

## Build order

Do not start a phase before the previous one works.

1. **Foundation** — branches, menu with modifiers, branch availability, guest checkout,
   Paystack, staff dashboard with alarm and escalation. *This can go live.*
2. **Operations** — full state machine, `order_events`, shifts, customer tracking page.
3. **Riders** — rider auth, claim flow, real-time dispatch.
4. **Administration** — owner dashboard, user onboarding, permissions UI, branch management.
5. **Growth** — promotions v1, customer list with lifetime value and export.
6. **Reporting** — operational and financial depth built on `order_events`.

**Current phase:** 1 — foundation.
<!-- Update this line as phases complete. It is the fastest signal of where the project is. -->

---

## Out of scope for v1

Do not build these. If a task seems to require one, stop and raise it.

- Loyalty points or stamp cards
- Ratings and reviews
- Live rider GPS tracking on a map
- Multi-currency
- Inventory or stock depletion
- Table reservations
- Promo types beyond percentage and fixed discount
- A lead pipeline with stages (v1 is a customer list, nothing more)
- Native mobile app

---

## Open decisions

Flag these rather than assuming an answer.

- SMS provider for escalation and order confirmations
- Whether rider payouts are tracked in-platform or handled offline
- Whether managers can edit prices (currently owner-only)
- Delivery fee model: flat per zone, or distance-banded

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

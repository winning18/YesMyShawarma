---
paths:
  - "database/**"
  - "app/Models/**"
---

# Schema

Column lists are indicative. **Propose migrations before writing them.**

## Universal rules

- Money is `unsignedBigInteger` in pesewas. Never floats, never decimals.
- Datetimes stored UTC.
- Every branch-owned table carries `branch_id` and is covered by the branch global scope.
- Soft deletes on `menu_items`, `options`, `promotions`, `users`. Never on `orders`.

## Branches

```
branches
  id, name, slug, phone, address, ghanapost_code, lat, lng,
  opens_at, closes_at, is_accepting_orders, is_active,
  areas_served (json list of neighbourhood names, nullable — shown on the branch page and
  as schema.org areaServed; informational only, never limits delivery. Express POS 2b,
  replaced config/delivery_areas.php; YMS values copied from
  database/seeders/data/branch_areas.php by the 2026_11_15_110000 migration)

branch_working_hours
  id, branch_id, day_of_week (1=Mon..7=Sun), opens_at, closes_at
```

`is_accepting_orders` is a manual kill switch separate from opening hours — staff use it when
the kitchen is overwhelmed.

`branches.opens_at`/`closes_at` are a flat, same-every-day fallback — required at branch
creation, editable on the branch's own edit form, but otherwise inert. **They gate nothing and
customers never see them.** The real weekly schedule is `branch_working_hours`, one row per
`(branch_id, day_of_week)`, edited from its own "Working Hours" dashboard page
(`WorkingHoursService`) — this is what `isOpenAt()`/`isOpenNow()`/`nextOpening()` read for order
gating and what the customer-facing branch card's "Hours today" line reads for display
(`WorkingHoursService::todayLabel()`). A day with no row at all means "never configured"; a row
that exists with both `opens_at`/`closes_at` null means "deliberately closed that day" — two
different signals sharing the same null value, disambiguated only by row presence. A branch
with zero `branch_working_hours` rows fails open (`isOpenAt()` always true) so a branch that has
never touched the feature behaves exactly as before it existed.

## Menu

```
categories              id, name, slug, tagline, sort_order, is_active, is_beverage,
                        hero_image_path
                        (is_beverage = "these are drinks": never count toward
                        buy-X-get-Y, no WhatsApp reward, small side-by-side cards
                        on the menu, suggested on item pages. Replaced the
                        hard-coded "drinks" slug, Express POS 2b)
menu_items              id, category_id, name, slug, description,
                        base_price, image_path, is_active, sort_order
branch_menu_item        branch_id, menu_item_id, is_available, unavailable_until
option_groups           id, name, min_select, max_select, is_required
options                 id, option_group_id, name, price_delta, is_active
menu_item_option_group  menu_item_id, option_group_id, sort_order
menu_item_schedules     id, menu_item_id, branch_id, day_of_week, starts_at, ends_at
```

**Branch-level availability is mandatory.** Staff must mark an item sold out from a phone in
one tap. `unavailable_until` supports auto-restore at next opening.

`hero_image_path` is only ever set for the categories used in the home page hero slider — not
a general category photo field. `tagline` is the per-category slide caption shown alongside it,
a plain staff-editable string (not a hardcoded map).

A selected option's own quantity (`order_item_options.quantity`, default 1) only ever varies
for an option belonging to a multi-select group (`max_select > 1`) — a single-select group's
option (Size, Spice level) is always exactly one, same as before this column existed. It is a
fixed amount for the whole line, not multiplied by the order item's own `quantity`: "extra
cheese x2" means 2 total for that line, however many units are in it. `MenuPricingService`
enforces both rules — silently correcting any quantity submitted for a single-select option
back to 1, and folding a multi-select option's `price_delta × quantity` into the line total
once, never scaled by the item's quantity. `min_select`/`max_select`/`is_required` are
unaffected — they still count how many *distinct* options were chosen, regardless of any
option's own quantity.

`menu_item_schedules` is opt-in per item, per branch: a row means "on `day_of_week`
(0=Sunday..6=Saturday, matching Carbon), available between `starts_at` and `ends_at`" — plain
Africa/Accra local time, not a UTC instant, since this is a recurring weekly pattern tied to
the branch's own rhythm, not a point in time. An item with no rows is left entirely to the
manual `is_available` toggle. `ApplyMenuItemSchedules` (scheduled every 5 minutes) is the only
thing that writes `is_available` for items that do have rows — manual toggles on those still
work but get reasserted next run.

Price is always `menu_items.base_price` — no per-branch override.

## Customers

```
customers           id, phone (unique, nullable), name, email, password,
                    phone_verified_at, marketing_opt_in, marketing_opted_out_at,
                    suspended_at, suspended_by, suspension_reason
customer_addresses  id, customer_id, label, delivery_area_id (nullable), area_other (nullable),
                    ghanapost_code (nullable), landmark, lat, lng, is_default
```

`phone` is the identity key, normalised to E.164, for every path except one: a POS walk-in who
declines to give it. Web checkout and registration still require it. A guest row is created on
first order; registration sets `password` on the existing row so history carries over.
`CustomerService::findOrCreateByPhone()` never looks up or reuses a row by a null phone — each
phone-less walk-in gets its own fresh, un-findable-again `Customer` row, never merged with
another's order history. MySQL's unique index already permits multiple NULLs, so this needed no
index change alongside the nullable column.

**Imported leads** (Customers → Import contacts, `CustomerImportService`, `customers.broadcast`):
outside contacts from a CSV become ordinary `customers` rows with no orders — no separate leads
table (owner, 2026-10-21). If one orders later, `findOrCreateByPhone()` finds the same row, so
their history joins up. An import never changes an existing row except to fill a missing name.

Free-text `landmark` is required on delivery orders; `ghanapost_code` is optional. Riders use both.

**My account** (owner, 2026-11-11, `Customer\Account\*` controllers): a logged-in customer edits
name/email/password, toggles SMS offers (`marketing_opted_out_at`, the same flag as the broadcast
opt-out link), sees their orders with Order again (`ReorderService` — current branch, unavailable
items skipped and named, free promo lines never copied), and manages `customer_addresses`
(`CustomerAddressService` — saved from checkout's "Save this address" tick, or added with the
phone's current location; first one is the default; picking one at checkout fills every delivery
field including lat/lng). Changing `phone` needs a 6-digit code texted to the new number
(`SmsCodeService`: cache-only, HMAC-stored, 10-minute expiry, 5 tries, 3 texts per number per 10
minutes); a number held by another registered or suspended customer is refused, and a guest row
on it is merged in — its orders move over with an `order_events` row each (`meta.action =
customer_merged`), its reviews/redemptions/addresses follow, and its own `phone` is cleared.
Customers' "Forgot password" uses the same codes.

**`lat`/`lng` (here and in `orders.delivery_address_snapshot`) are rider-only.** Staff and
managers work off the semantic address — `area_name`, `landmark`, `ghanapost_code` — never the
raw coordinate the customer's device shared at checkout. `OrderResource` (the live dashboard's
JSON payload) only includes `lat`/`lng` when `BranchContext::primaryRoleFor()` resolves the
requesting user's role at the order's branch to `rider`; everyone else — including owner,
manager, and general_manager — gets `null` for both, regardless of what's actually in the
snapshot. This is enforced in the resource, not just hidden in the UI.

## Orders

```
orders
  id, reference, track_token, customer_id, branch_id,
  fulfilment_type (delivery|pickup), status,
  subtotal, discount_total, delivery_fee, tax_total, total,
  payment_method (paystack|cash|momo), payment_status, channel (web|pos),
  promotion_id, delivery_address_snapshot (json),
  rider_id, claimed_at, scheduled_for,
  placed_at, accepted_at, ready_at, dispatched_at,
  delivered_at, cancelled_at, cancellation_reason

order_items
  id, order_id, menu_item_id,
  name_snapshot, unit_price_snapshot, quantity, line_total, is_free, notes

order_item_options
  id, order_item_id, option_id, name_snapshot, price_delta_snapshot, quantity

order_events
  id, order_id, from_status, to_status,
  actor_type, actor_id, shift_id, meta (json), created_at

order_taxes
  id, order_id, name, rate_basis_points, inclusive (bool),
  food_amount, delivery_amount (pesewas)
```

### Tax (Express POS step 9, the `tax` module)

`TaxService`. The business sets its taxes under Settings → Tax (owner only, `business.manage`):
lines of name + rate (Ghana preset VAT 15%, NHIL 2.5%, GETFund 2.5%, all on the same base),
whether prices include the tax (`inclusive`) or it's added on top, and whether the delivery fee
is taxed. Stored as the `tax_config` setting (JSON). Charged only with the module on **and** at
least one line; Bolt Food and freebies are never taxed.

- **At the sale** (`OrderCreationService`): `quote()` taxes the food after discounts and, if set,
  the delivery fee. Added on top: each line is `round(base × rate)`, added to `total`. Inclusive:
  the tax inside the base at the combined rate, shared by rate (the last line takes the
  rounding); `total` is unchanged. `order_taxes` keeps each line as it was; `tax_total` is all of
  it either way. Later rate changes never touch a placed order.
- **Fee changes** (adjust, arrival, transfer, delivered recompute) call `retotal()`: food tax
  stays frozen; the delivery tax follows the new fee at the order's own rates (whether the fee is
  taxed is read from today's setting).
- **Reports**: sales are always before tax (`netFood` = subtotal − discount − tax inside the
  prices). `financialSummary()` also returns `till_total` (food with its tax, what a till holds:
  shift cash-up and `tillSales()`) and `tax_total`. A rider's delivery fee includes any tax added
  to it (`DeliveryFeeReportService::split`). Reports → Tax lists tax per line and per day, less
  tax on refunds completed in the period (refund amount × order tax ÷ order total).
- **Shown on** receipts (browser and ESC/POS, with the business TIN `business_tin`), the order
  page, confirmation, tracking, the POS and checkout running totals (`partials/tax-script`
  mirrors `split()`; the server's figure is the one charged). Added tax shows as lines above
  Total; included tax as "Includes VAT 15%" under it. YMS has the module on but no lines, so
  nothing changes there.

### Snapshotting is non-negotiable

Menu prices and names change. A delivered order must always render exactly what the customer
saw and paid.

**Never join to `menu_items` or `options` to display a historical order.** Read the
`*_snapshot` columns. The foreign keys exist for analytics only.

Same applies to `delivery_address_snapshot` — customers edit saved addresses.

### Other notes

- `reference` is the human-readable order number shown to customers and staff.
- `track_token` is a random 32-char string powering `/track/{token}` with no login.
- `channel` distinguishes web checkout (`web`, the default) from staff-entered counter/phone
  orders (`pos`). For `pos` orders, `order_events`' `actor_type`/`actor_id` on the placement
  row identify the staff member who entered it, not the customer — `OrderCreationService`
  defaults to `'customer'`/the resolved `Customer` id, but a caller may pass an explicit actor.
- Status timestamps are denormalised for reporting speed. `order_events` remains the source
  of truth; if the two disagree, `order_events` wins.
- `is_free` is set only on the synthetic row a `buy_x_get_y_free` promotion grants — see the
  Promotions section below. It's never folded into the paid row it was earned from, so it
  shows distinctly on the receipt/kitchen ticket; `unit_price_snapshot`/`line_total` are both 0
  on that row, but `quantity` is a real count — stock deduction treats it like any other row,
  with no `is_free` check of its own (a free shawarma still consumes real ingredients).
- `users.rider_last_seen_at` (nullable, UTC) is when a rider last used the rider app. It's what
  "logged in" means for rider availability: within `session.lifetime` and at
  `users.current_branch_id`. Written by `MarkRiderSeen`, cleared by rider logout and suspension.
  It replaced reading `sessions.user_id` (2026-11-20). See orders.md's rider assignment section.

## Shifts

```
shifts  id, user_id, branch_id, terminal_id (nullable), started_at, ended_at, ended_by_user_id,
        starting_cash, total_sales, system_sales, opening_note, closing_note, no_expenses

shift_expenses  id, shift_id, description, amount, created_at

terminals  id, branch_id, name (unique per branch), device_token_hash (unique, nullable),
           paired_at, is_active, timestamps

orders.shift_id  (nullable) the till that took the money

printers  id, branch_id, name (unique per branch), role (receipt|kitchen), paper_width (58|80),
          connection (network|usb), address (IP[:port] or Windows printer name), opens_drawer,
          is_active, timestamps
terminals.receipt_printer_id  (nullable) a counter's receipt printer

users.staff_code  (nullable, unique) "YMS-0042" — till staff's counter sign-in ID (step 7b)
users.pin         (nullable) hashed like a password; set by a manager
```

**Counters (Express POS step 7a, owner 2026-11-15).** A branch with no counters (`terminals`)
works exactly as below: one shared shift. Once it has counters, each counter has a till of its
own — a shift with `terminal_id` set, its own float, sales and cash-up — and several are open at
once. A device becomes a counter when a manager sets it up (Settings → Counters,
`CounterController`, `settings.manage`): the device keeps a random secret in the long-lived
`express_counter` cookie, the counter keeps only its SHA-256. `CounterService::current()` reads
it per request. With counters:

- POS sales are taken only on a counter (`CounterService::canTakeSalesHere()`, checked in
  `PosController` and `OrderCreationService`); other devices see "This device isn't a counter".
- `ShiftService::activeForBranch()` means "this device's counter's till" — none on a non-counter
  device or in a queued job — so every caller (order events, the forced shift start, the branch
  lock) follows without knowing about counters.
- `orders.shift_id`: a POS order gets its counter's till at placement; a web order gets the till
  that accepts it (`OrderStateMachine`, owner's choice), or none if accepted on a non-counter
  device. Set in shared mode too (the shared shift).
- A till's system sales are `OrderReportService::tillSales()` — its own orders, net of refunds —
  not the branch's sales since it opened.
- The first counter can't be added while the branch's shared shift is open; a counter with an
  open till can't be switched off.

**Printing (Express POS step 8a, owner 2026-11-15).** Settings → Printers (`PrinterController`,
`settings.manage`) lists a branch's receipt and kitchen printers. `PrinterService` decides which:
a counter's own receipt printer, else the branch's first; every active kitchen printer.
`ReceiptService` builds what a receipt and a kitchen ticket say (business name and logo, branch,
items, totals, payment, the Business profile's receipt message; the ticket leads with the
reference's short code and has no prices) — for the browser print pages (`print/receipt`,
`print/kitchen`, `PrintController`, sized to 58 or 80 mm) now and the print agent's ESC/POS in 8b.
**Print agent (step 8b).** `print_agents` (branch, name, `token_hash`, a one-time
`pairing_code_hash` + `pairing_expires_at`, `last_seen_at`) and `print_jobs` (branch, printer,
order, kind receipt|kitchen|drawer, status pending|sent|printed|failed, attempts, error,
requested_by, claimed_at, printed_at). The agent (`print-agent/`, Go, Windows) connects with a
code from the Printers page (`PrintAgentService`, `/api/print-agent/connect`), then every two
seconds collects jobs (`PrintJobService::claim()`, ESC/POS from `EscPosRenderer`) and reports each
(`/api/print-agent/jobs/{id}`); a job never confirmed is offered again after two minutes, up to
three tries. Jobs are queued only while the branch's agent is online (seen in the last 60 s) — a
POS sale queues receipt, kitchen ticket(s) and, for cash with a drawer printer, a drawer kick;
accepting queues kitchen tickets; Print buttons queue reprints — and the response's
`printed_by_agent` / `queued` tells the page whether to print through the browser instead.
Failed receipts and tickets show on the order board (`print_failures`) until reprinted. Each printer on
the Printers page has "Print a test page" (`kind` test, no order; the page polls its status, or
prints a browser test page with no agent on); test pages never show on the board. Asking for a
connection code again for a not-yet-connected agent of the same name reuses it.

In 8a printing goes through the acting device's browser (`partials/print-script`, a hidden
frame): the receipt and kitchen ticket after a POS sale, the kitchen ticket on accept, and Print
buttons on the order page and board cards for reprints. A branch with no printers prints nothing
and shows no buttons. The cash drawer waits for the agent.

One open shift per branch at a time, not one per staff member — `user_id` is who opened it,
`ended_by_user_id` who closed it, and they may well be different people. A second staff member
logging in while a branch's shift is already open joins it rather than getting one of their own
(`ShiftService::start()` returns the existing row unchanged on a second call for the same
branch) — reaching the dashboard, accepting/advancing orders, and ending the shared shift are
all available to them exactly as if they'd opened it themselves. `BranchSelectionController`'s
shift lock (see orders.md) is keyed the same way: the lock belongs to the branch's open shift,
not to whoever happens to have started it.

Orders attribute to a shift, not only a user. Terminals get shared between staff; the shift
makes handover explicit and makes "which shift had four cancellations" answerable — per
individual action, that's still `order_events.actor_id`, not the shift; the shift groups a
branch's orders into an operational session, it doesn't by itself say who served what.

`starting_cash` and `total_sales` are two independent facts, never combined:

- `starting_cash` — the float given to make change at the start of the session. It exists
  purely as a record that "x amount was available at the start" — it is never sales revenue
  and must never be added to, subtracted from, or otherwise combined with `total_sales` in any
  calculation or report. Optional for every role — capturing it is a courtesy, not a
  requirement.
- `total_sales` — what was actually sold during the shift, entered by the person ending it.
  Required of everyone who ends a shift (`ShiftController::end()`), and can never be entered
  below `system_sales` (revenue recorded from `started_at` to the moment of ending, this shift's
  branch only — `ShiftController::systemSalesForShift()`, via `OrderReportService::
  financialSummary()`) — an amount above it is accepted and shown as "Extra" in the Today
  report rather than silently dropped. Scoped to the shift, not the calendar day: two shifts on
  the same day each start back at 0, so starting a new shift right after ending one is never
  rejected for "under-reporting" against sales that actually belonged to the shift before it.

Both are `unsignedBigInteger` pesewas, both nullable at the column level — "required" is an
application-layer rule (`ShiftController`), not a schema one.

`shift_expenses` is an immutable ledger, same shape as `order_events`/`stock_movements` —
`amount` in pesewas, `created_at` only, no `updated_at`, reached only through `shift_id` (no
`branch_id` of its own). Ending a shift requires either at least one expense row (`description`
+ a positive `amount`, both mandatory per row) or `shifts.no_expenses` explicitly set — the
latter is a genuine "I confirm there were none today," kept visually distinct in the Today
report from a shift that simply predates this feature (both show zero rows, but only one set
the flag). The Today report's "Net" column is `total_sales` minus the sum of that shift's
`shift_expenses`, shown as-is rather than floored at zero.

Since the market list (below), each shift close's expenses are **also** written as a pending
`market_lists` row (`shift_id` set) — `shift_expenses` itself is unchanged and still drives the
Today report's per-shift Net, but every money report (sales statement, Expenses tab, Profit)
reads the market list only, so nothing is deducted twice.

## Market list

```
expense_categories  id, name (unique), keywords (json), sort_order, is_active
market_lists        id, branch_id, purchased_on (date), recorded_by, recorder_role,
                    shift_id (nullable, unique), status (pending|needs_changes|approved),
                    total, receipt_path (nullable), note, reviewed_by, reviewed_at,
                    review_note, timestamps
market_list_items   id, market_list_id, expense_category_id, description,
                    quantity (decimal 10,2, nullable), unit (nullable), amount, timestamps
market_list_events  id, market_list_id, actor_id (nullable), action, note, created_at
```

Every purchase and expense (owner, 2026-11-11), recorded by staff/manager/GM/owner/stock_manager
(`MarketListService`). Branch-owned (`BranchScope`; route binding bypasses it like `Order`, the
policy checks the list's own branch). Only `approved` lists count anywhere — sales statement Net
sales, the Expenses tab (default filter) and the Profit tab — always by `purchased_on`, the
Africa/Accra date of purchase, never the approval date. Approval rules live in
`MarketListService`/`MarketListPolicy` (see permissions.md's "Market list"). Approved lists are
locked. A pending or sent-back list can be deleted outright (owner, 2026-11-12; see
permissions.md's "Market list") — no "cancelled" status; `market_list_events` is the immutable history (submitted, edited, resubmitted,
sent_back, approved, auto_approved, converted). `total` is denormalised from the items, rewritten
on every edit.

`expense_categories` are the "kinds" reports group by (Chicken, Transport, … Others), first filled
from `database/seeders/data/expense_categories.php` by its migration, then managed from Market
list → Manage kinds (`ExpenseKindService`, `market_lists.approve`: owner/GM/manager): add, rename,
reorder, edit keywords, hide/show. Others can't be renamed or hidden and always sorts last. A
hidden kind isn't offered or suggested, but stays on past items (and selectable on lists already
using it). After a kind gains keywords, items still in Others that now match can be moved across,
approved lists included; each touched list logs a `kind_changed` event. `ExpenseKindMatcher` suggests one
from the description — longest keyword at the start of a word wins, else Others; the form runs
the same rule in the browser. Staff can change it and the approver can correct it while
approving. The Profit tab sums `quantity` per kind only when every item shares one unit.

`receipt_path` is on the private `local` disk like damage photos, served only through
`MarketListController::receipt()`, but kept permanently (financial record, no expiry).

Shift expenses from before the market list were converted once into approved lists
(`2026_11_11_100300_convert_shift_expenses_to_market_lists`, `actor_id` null, event `converted`).

The Profit tab (`ProfitReportService`): sales (the statement's own definition, Bolt Food
excluded) − completed refunds − approved purchases, per day/week/month/year, with Bolt Food as a
separate line and a "profit incl. Bolt Food" total labelled before Bolt's commission (owner,
2026-11-11). Pending and sent-back lists are shown as "awaiting approval", never counted.

## Freebies and WhatsApp group orders

```
orders                + source (nullable; 'whatsapp')
complimentary_orders  id, order_id (unique), recipient_type (customer|staff), recipient_user_id,
                      recipient_name, reason, status (pending|approved|rejected),
                      recorded_by, recorder_role, reviewed_by, reviewed_at, review_note
```

Owner, 2026-11-14, both POS-only and pickup-only:

- **Freebies** — payment_method `complimentary` (`Order::COMPLIMENTARY`): food given free to a
  customer or staff member. `subtotal` is the menu value given away, `discount_total` equals it,
  `total` is 0; status `paid` immediately; stock is still used. In
  `Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS` alongside Bolt Food, so every sales figure, the
  till check and Profit leave it out — code that means "Bolt Food" must test `Order::BOLT_FOOD`,
  never that list. Never gets a promotion. `complimentary_orders` records who and why and the
  approval (`FreebieService`, market list tier rule; see permissions.md's "Freebies"); a
  rejected freebie stays out of sales, flagged against its giver. Reference prefix YMGS-FREE.
- **WhatsApp group orders** — `orders.source = 'whatsapp'`: `SettingsService::WHATSAPP_REWARD_AMOUNT`
  (GH₵10) off the food only (never drinks, `PromotionService::foodSubtotal()`), never more than
  the food, held in `discount_total` (no `promotion_id`) so "sales = subtotal − discount" already
  reflects it. Cash or MoMo only. On a promotion day the order keeps the promotion instead when it
  actually earns something from it, otherwise still gets the reward. Reference prefix YMGS-WA.

Freebies & rewards (`FreebieController`, `FreebieReportService`) lists freebies with value,
recipient, reason, giver and status, with breakdowns, and totals the WhatsApp rewards.

## Delivery areas

```
delivery_areas  id, name (unique), is_active
```

Replaces the earlier per-branch, radius/fee-per-zone `delivery_zones` design (never used in
production — dropped with zero rows). `delivery_areas` is now just a shared, named-area label
shown in the rider's dropdown ("this delivery is headed to Amasaman") — it plays no part in
pricing or branch routing. `$data->branchId` is assumed already resolved by the caller before
`OrderCreationService::create()` runs; there is no cross-branch routing-by-coordinate.

Delivery pricing is continuous, not zone-based: haversine distance from the branch to the
customer's shared location, times a flat rate per km, rounded to the nearest whole cedi and
floored at a minimum fee — no fractional-cedi change, and never cheaper than the floor
regardless of distance — see `App\Services\Delivery\DeliveryFeeCalculator`. The rate per km,
the minimum fee and the unrelated minimum order for delivery (a minimum cart subtotal, checked in
`OrderCreationService`) are each business's settings — `delivery_rate_per_km`,
`delivery_minimum_fee`, `delivery_minimum_order` in pesewas, Settings → General → Delivery fees,
shown only with the Delivery module on. Unset, the calculator's constants apply (GH₵5 / GH₵10 /
GH₵20, Yes My Shawarma's values, which `2026_11_15_150000_fill_delivery_fee_settings` also
stored for it). Read them through `ratePerKm()`, `minimumFee()`, `minimumOrderTotal()`, never the
constants.

## Payments

```
payments  id, order_id, provider, provider_reference,
          amount, currency, status, raw_payload (json), verified_at
```

`provider_reference` is unique — it is the idempotency key for webhook retries.

## Refunds

```
refunds  id, order_id, branch_id, amount, reason, status (pending|approved|denied|completed),
         requested_by, reviewed_by, reviewed_at, review_note,
         completed_by, completed_at, provider_reference
```

Branch-owned (its own `branch_id`, covered by the branch global scope — not reached only
through `order_id`). See payments.md's Refunds section for the request → approve → complete
workflow and why this never touches `orders.status`. `amount` can be less than `orders.total`
(partial refund) and an order can have more than one `refunds` row over time — the refundable
remainder is `orders.total` minus every `completed` refund against it.

## Damage reports

```
damage_reports  id, branch_id, order_id (nullable), stock_item_id (nullable),
                reported_by, reporter_role, description, photo_path (nullable),
                photo_viewed_at (nullable), status (pending|approved|denied),
                reviewed_by, reviewed_at, review_note
```

A rider finds damage while delivering an order (`order_id` set), or staff finds a damaged item
not tied to any specific order (`order_id` null, `stock_item_id` optionally set when it's about
stock rather than e.g. equipment) — either way, one record, reviewed by manager/general_manager/
owner (`damage_reports.review`, same equal-tier shape as `orders.refund`). Approving is an
acknowledgement only — no automatic stock deduction or financial consequence, a deliberate v1
scope decision (`DamageReportService`).

`photo_path` lives on the **private** `local` disk (`config/filesystems.php`), never the public
one branch/menu images use, and is served only through `DamageReportController::photo()`
(`DamageReportPolicy::viewPhoto()` — the reviewer tier, or the original reporter seeing their
own submission back). The first time an actual *reviewer* opens it, `photo_viewed_at` is set
(`DamageReportService::markPhotoViewed()` — the reporter viewing their own photo never counts);
24 hours after that, the scheduled `damage-reports:delete-expired-photos` command
(`routes/console.php`, hourly) deletes the file and nulls `photo_path`, but the row itself stays
permanently, same as every other audit table in this app outliving the artifact it was about. A
report whose photo has never been viewed is never touched, however old it gets — the window only
starts once someone's actually looked.

## Promotions

```
promotions             id, code, type (percentage|fixed|buy_x_get_y_free), value,
                       buy_quantity, free_quantity, free_menu_item_id (FK
                       menu_items, nullOnDelete), min_order_total,
                       starts_at, ends_at, recurring_days, is_automatic,
                       banner_headline, banner_image_path,
                       max_redemptions, max_per_customer, is_active
promotion_branch       promotion_id, branch_id
promotion_redemptions  id, promotion_id, order_id, customer_id, amount_discounted
```

Do not add a promo type beyond these three without being asked — `buy_x_get_y_free` was
itself flagged and approved mid-project (CLAUDE.md's "Resolved, no longer open" note), not
assumed.

`buy_quantity`/`free_quantity` are only meaningful for `type === 'buy_x_get_y_free'` — `value`
stays `0` (the column is `NOT NULL`) and unused for that type. **`buy_quantity` is how many
qualifying items the customer pays for per grant, not a total group size** — "buy 2 get 1 free"
is `buy_quantity=2, free_quantity=1`, not 3. Owner's rule (2026-11-06): qualifying items are
everything except drinks (categories with `is_beverage`), mixed freely across items, and the
giveaway is the promotion's own `free_menu_item_id`, always plain (Yes My Shawarma's is Chicken
Shawarma) — 2x Signature earns a Chicken Shawarma, not a third Signature. The free item is
required on the form for this type; a promotion without one (or whose item is inactive) gives
nothing away and reports the problem to Sentry. Add-on options are never free. `PromotionService::freeUnitsForQuantity()` computes
`floor(paidQuantity / buy_quantity) * free_quantity`, repeating per group rather than capping at
one freebie per order (paying for 4 grants 2, not 1). A `buy_x_get_y_free` promotion is always
`is_automatic` — enforced in `Store`/`UpdatePromotionRequest` — since it has no code-entry path
that could ever grant it (see below).

Rather than reducing a price, `buy_x_get_y_free` grants a genuinely separate, zero-priced
`order_items` row (`is_free`) — visible as its own line in the cart, checkout, POS, and the
receipt/kitchen ticket, not a generic "Discount: -GH₵X" figure. `PromotionService::
appendFreeLines()` is the one place this row gets built — grouped by `menu_item_id` (not by
cart line, since the same item can be split across several lines with different options) and
priced at 0 with no options of its own (a free unit's own chosen options still bill in full on
whichever paid line earned it — the free row itself is always the plain base item). Called
identically by `CartService::summary()` (so the free line shows up — and recalculates — the
moment it's earned, purely a view-time augmentation, never written back into the stored cart)
and `OrderCreationService::create()` (so the same row becomes the real `order_items` entry).
Because of this, `orders.discount_total` is always `0` for this type — `subtotal`/`total`
already exclude the free unit's value entirely, since `priceItems()` only ever prices what the
customer actually submitted; the free row is pure addition, not a reduction. The redemption
ledger (`promotion_redemptions.amount_discounted`) still records the free unit's full value
regardless, since "how much did we give away" shouldn't depend on how the order's own columns
happen to be structured.

`recurring_days` (nullable JSON array of weekday ints, Carbon's own numbering — 0=Sunday..
6=Saturday, matching `menu_item_schedules`) and `is_automatic` (boolean) together drive a
promotion that applies with **no code at all** — `PromotionService::findActiveAutomatic()`
finds the one such promotion (if any) active for a branch today, Africa/Accra local. At most
one is ever returned: `orders.promotion_id` only ever holds a single promotion, so an automatic
one always takes that slot and the checkout/POS UI hides coupon-code entry entirely on a day
one applies — `validate()` itself also rejects an automatic promotion's own `code` if it's
somehow submitted manually, since that code exists only to satisfy the column's uniqueness, not
to be typed in. Never applies to a `payment_method === 'bolt_food'` order, the same blanket
exclusion `Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS` already applies to sales reporting —
Bolt Food orders are settled entirely on Bolt's own platform; in POS specifically, the free
line/discount preview is computed before payment_method is necessarily final (it's a radio
alongside the cart, not a separate step), so it assumes non-Bolt-Food and the page itself hides
it client-side the moment Bolt Food is actually selected — `OrderCreationService::create()` is
still what authoritatively excludes it regardless of what the preview showed. A promotion with
no branch rows (every branch) is the only kind `findActiveAutomatic()` can match when called
with no branch at all (the homepage banner, before a customer has picked one) — a branch-
restricted automatic promotion only ever shows once a branch is actually selected.

`banner_headline`/`banner_image_path` drive the homepage banner shown ahead of the normal hero
slider on a day an automatic promotion is active (`HomeController::index()`) — kept on the same
row as the discount itself so the banner and the live discount can never disagree about whether
today counts.

## SMS broadcasts

```
sms_broadcasts            id, message, branch_id (nullable = all customers), status,
                          scheduled_at, recipient_count, sent_count, failed_count,
                          created_by, started_at, completed_at
sms_broadcast_recipients  id, sms_broadcast_id, customer_id, phone, opt_out_code (unique),
                          status, error, sent_at
```

Customers → SMS broadcasts (`customers.broadcast`: general_manager, owner). Owner decision
(2026-10-18): broadcasts go to **every customer with a valid phone who hasn't opted out** —
`marketing_opted_out_at` is null — not to `marketing_opt_in`, which nothing collects. Each SMS
ends with a personal `/stop/{opt_out_code}` link; the GET only asks, the POST opts out (link
previews fetch GETs). Order SMS ignore the opt-out.

No stored contact list: `SmsBroadcastService::audience()` reads `customers` live when a broadcast
starts, normalising and de-duplicating numbers then. `branch_id` narrows it to customers with an
order at that branch. Status: `scheduled -> sending -> sent`, or `scheduled -> cancelled`. "Send
now" is just `scheduled_at = now`. `sms:send-due-broadcasts` (every minute) claims due rows,
snapshots recipients, and queues `SendSmsBroadcastBatch` for every broadcast still sending; each
job sends for up to 40s, so only `pending` recipients are ever texted and a dead job resumes.
No frequency limit: the original one-per-7-days rule was removed at the owner's request
(2026-10-21) — broadcasts can go any day, any time.

Past broadcasts (owner, 2026-11-20). These are for broadcasts only; order SMS keep their own
three automatic tries (`SendSms`):
- **Retry failed** (`SmsBroadcastService::retryFailed()`, a sent broadcast with failures) puts that
  broadcast's `failed` recipients back to `pending` with their same opt-out codes, leaving out
  anyone opted out since, and sets the broadcast back to `sending`. The next minute's run sends
  them like any broadcast still sending.
- **Resend** (`resend()`, sent broadcasts only) is a new broadcast with the same message and
  `branch_id`, sent now to today's audience with new codes.
- **Copy** only puts the message and branch back in the compose form.

## Visitor tracking

```
visitor_sessions  id, token (unique), ip_address, user_agent, referrer,
                  branch_id, order_id (unique), created_at, updated_at
page_views        id, visitor_session_id, path, duration_seconds, viewed_at
```

Anonymous, cookie-based, customer-site-only (`TrackVisitorSession`, attached only to the public
route group — never staff/POS/rider). `token` is a random value handed out as the `visitor_token`
cookie; `created_at`/`updated_at` are first-seen/last-seen. `branch_id`/`order_id` stay null
until the session converts (its first order — `whereNull('order_id')` guards against
re-attributing an already-converted session to a second order).

`ip_address`/`user_agent`/`referrer` are set once, at row creation, from the request that
started the session — never overwritten by a later visit from the same returning token, since
they describe how the visit began. `page_views` is one row per GET request in the tracked
group; `duration_seconds` starts null and is filled in later by a `sendBeacon()` call fired as
the visitor leaves the page (`customer.blade.php`), so it stays null if the beacon never lands.

All of this is gated on the Cookie Policy's consent choice: a `cookie_consent=decline` cookie
(set via `PolicyController::updateCookieConsent`) stops `TrackVisitorSession` from recording
anything at all for that request, and forgets an existing `visitor_token` cookie the moment
someone declines.

## Stock

```
stock_items      id, branch_id, name, unit, quantity (decimal 10,2),
                 low_stock_threshold (decimal 10,2), low_stock_alerted_at,
                 created_by (-> users), timestamps
stock_movements  id, stock_item_id, type (restock|sale), quantity (decimal 10,2),
                 actor_id (-> users), shift_id (nullable -> shifts), note, created_at
```

Branch-owned (`BranchScope`, like every other branch-owned table). `stock_items.quantity` is a
denormalised running total — `stock_movements` is the source of truth, same relationship as
`orders`/`order_events`. Every quantity change goes through `StockService::restock()`,
`recordSale()`, or `recordAutomaticConsumption()`, all three of which write a matching
`stock_movements` row before updating the total, so the two can never drift; nothing else may
write `stock_items.quantity` directly. `stock_movements` carries no `branch_id` of its own
(reached only via `stock_item_id`), same as `order_events` has none of its own. `actor_id` is
required, not nullable — every stock change must be attributable, no system-authored rows exist
here the way `order_events` allows for escalation (even automatic recipe deduction attributes
to the staff member who accepted the order — see below).

`low_stock_alerted_at` de-bounces the owner SMS (`StockAlertNotifier`): set the moment quantity
crosses below `low_stock_threshold`, cleared by `restock()` once quantity is back at/above
threshold. Without it, every subsequent sale while already low would re-send the same alert.
`recordAutomaticConsumption()` can push quantity negative (deliberately never blocks an order
over a stock-tracking gap) — `isLowStock()` already treats any quantity below threshold as low,
negative included, so this reuses the exact same alert path with no separate "went negative"
case.

See `.claude/rules/permissions.md`'s "Stock management" section for who can do what.

### Recipes and stock deduction

```
menu_item_recipe_items  id, branch_id, source_type (menu_item|option),
                        source_menu_item_id, source_option_id, stock_item_id,
                        quantity (decimal 10,2), timestamps
```

What one unit of a menu item — or a customer-chosen option — consumes from a branch's own
stock, staff-configured (per branch, not globally) from each menu item's edit page and each
option's own recipe panel on the option-groups edit page. **Per-branch, not shared**: unlike
`menu_item_components` (which decomposes a combo into other `menu_items`/`options` purely for
sales-reporting categorisation), this points at an actual `stock_item_id` — and `stock_items`
are themselves per-branch with no shared ingredient catalog anywhere in this app, so a recipe
has to say "at this branch" to know which row it means. Exactly one of `source_menu_item_id`/
`source_option_id` is set, same validation-not-DB-constraint shape as `menu_item_components`.
A unique index on `(branch_id, source_type, source_menu_item_id, source_option_id,
stock_item_id)` stops the same stock item being added twice to the same recipe.

An item or option with no rows configured simply never touches stock when ordered — this was
deliberately not built as a day-one requirement (see CLAUDE.md's resolved "Inventory or stock
depletion" note) and is opt-in per item/branch rather than retroactively assumed for every
existing menu item.

`RecipeStockService` (`App\Services\Stock`) resolves what an order actually consumes, mirroring
`MenuPricingService`'s own per-unit-vs-fixed-for-the-line split exactly: a menu item's own
recipe, and a single-select option's, both scale by the order item's quantity; a multi-select
option's recipe is fixed for the whole line (its own `order_item_options.quantity`), never
further multiplied by the item's quantity — same reasoning as pricing itself, "extra cheese x2"
means 2, not 2 per unit.

Deduction and reversal are driven entirely by `OrderStateMachine::transition()`, never called
directly by a controller:

- **Deducted the moment an order reaches `accepted`** — not `paid`, since that's the point
  staff (the transition's actor, always a real user for this one) actually commits the kitchen
  to making it.
- **Reversed only if cancelled while still `accepted`** — i.e. `from === 'accepted'`. Once
  `preparing` has started the food was likely actually made, so a later cancellation leaves
  stock deducted on purpose rather than incorrectly restoring ingredients that were really used
  and wasted. `rejected` never needs reversal logic at all: it's reachable only directly from
  `paid` (never from `accepted`), so a rejected order never had stock deducted in the first
  place.
- Never blocks an order over insufficient stock — `recordAutomaticConsumption()` lets quantity
  go negative and relies on the existing low-stock alert to surface it, rather than refusing to
  accept a real order because this system's own count might be stale.

## Settings

```
settings  id, key (unique), value (text, nullable)
```

General-purpose key-value store for admin-editable business configuration
(`App\Services\Settings\SettingsService`, reached via the "Settings" sidebar item —
`settings.manage`, owner/manager/general_manager per permissions.md). Not branch-owned, so it's
exempt from the branch global scope.

Current keys:

- `order_reference_prefix_pos`, `order_reference_prefix_web`, `order_reference_prefix_bolt_food`
  — the prefix `OrderCreationService::generateReference()` uses (default `YMGS-POS`/`YMGS-WEB`/
  `YMGS-BOLT` when unset, so a fresh install needs no seeding). Bolt Food orders are always
  channel `pos` (payments.md) but get their own prefix — checked before the plain POS case, or
  they'd be indistinguishable from a real in-house sale on sight. The reference itself is
  `{prefix}-{6-character random code}` — letters/digits only, ambiguous characters (0/O, 1/I/L)
  excluded, same alphabet as `UserManagementService`'s temporary passwords, since this is
  another value read off a screen or receipt and typed back in by hand. Changing a prefix only
  affects orders placed after the change; existing `orders.reference` values are never
  rewritten.
- **Business profile** (Express POS step 2a, `BusinessProfile`, owner-only page): `business_name`
  (also fed into `config('app.name')` at boot), `business_legal_name`, `business_tagline`,
  `business_public_emails`, `business_contact_recipients` (comma lists), `business_instagram_url`
  / `_facebook_url` / `_tiktok_url`, `business_logo_path` and `business_icon_path` (public disk,
  `business/`), `business_main_branch_id` (heads all-branch reports; falls back to the first
  branch), and six brand colours `brand_primary(_light|_dark)` / `brand_accent(_light|_dark)`
  that the stylesheet reads as CSS variables (`partials/brand-head`, tailwind.config.js). A fresh
  install starts neutral (no logo: the name shows as text); Yes My Shawarma's values were copied in
  by `2026_11_15_100000_fill_business_profile`, which only fills an install that already has
  orders and never overwrites an existing setting. Order reference prefixes default to neutral
  POS / WEB / BOLT / WA / FREE (`SettingsService::DEFAULT_PREFIXES`); Yes My Shawarma's YMGS-*
  are stored settings.
- `home_menu_sliders` — the home page strips as JSON `[{title, slugs[]}]`, edited under Website
  content → Home page strips (`HomeStripService`); unset = one strip per active category.
- **Module switches** (Express POS step 3, `ModuleService`, owner-only Modules page):
  `module_<key>` = `'1'` / `'0'` for website, paystack, delivery, bolt_food, promotions,
  whatsapp_reward, freebies, market_list, stock, sms, refunds, reviews, tax. No row = off on a real
  install (`config/modules.php`); Yes My Shawarma's are all stored on by
  `2026_11_15_130000_switch_on_every_module` (installs with orders only). Data belonging to a
  switched-off module is kept, never deleted.
- **Location and food** (Business profile, step 2c): `business_city`, `business_region`,
  `business_cuisines` (comma list) — Google's Restaurant data, the `:city` placeholder, the
  menu-item search snippet and the weekly sales report. Empty on a new install.
- **About page** (Website content, step 2c): `about_mission`, `about_vision`,
  `about_founded_year` (shows "years of operation"), `about_customers_label` ("5,000+").
- **Tax** (step 9): `tax_config` (see Orders → Tax) and `business_tin` (Business profile).
- `paystack_enabled` — removed in Express POS step 3b; now the Paystack module switch
  (`module_paystack`, payments.md's "Paystack on/off" section).

## Website content

```
site_pages  id, slug (unique), title, meta_title, meta_description (500), body (longText,
            Markdown), timestamps — all but slug nullable
faq_items   id, question, answer (text), sort_order, timestamps
```

Express POS step 2c. The customer website's own words, edited under Settings → Website content
(`SitePageManagementController`, `FaqItemManagementController`; `content.manage`, owner and
general manager). `SitePageService::PAGES` lists the slugs — home, menu, menu-item, branches,
branch, contact, reviews, faq, about, terms, refunds, privacy, cookies, default (the description
for every page without its own) and llms — and the placeholders each fills in (`:name` and
`:city` everywhere; `:branches`, `:branch`, `:area`, `:address`, `:phone`, `:item`, `:url` per
page). A missing row or empty field falls back to plain wording built from the business name.
Bodies are rendered by `Str::markdown` with raw HTML escaped and unsafe links dropped, plus
league/commonmark's Attributes extension for `{#anchor}` heading ids; placeholder values typed
elsewhere (a branch address) are escaped so they're never read as Markdown.

About, FAQ and the four policies (`OPTIONAL_PAGES`) only exist once something is written —
otherwise they answer 404 and the header, footer, cookie notice, sitemap and llms.txt don't link
them. Yes My Shawarma's content (`database/seeders/data/site_content.php`) was copied in by
`2026_11_15_120000_create_site_pages_and_faq_items`, only on an install with orders, never over
existing rows.

## Push subscriptions

```
push_subscriptions  id, user_id, endpoint (unique), public_key, auth_token
```

See realtime.md's "Web Push" section. One row per subscribed browser/device, not per user —
`endpoint` (the browser's own push-service URL) is the natural identity, so the same person on
a phone and a tablet gets two rows, and re-subscribing an already-known browser updates its row
in place rather than duplicating it. `endpoint` is a plain `string(512)`, not `text` — MySQL
can't put a unique index on a TEXT column without a prefix length, and real endpoints are well
under that. Never branch-owned: a subscription belongs to a user's own device, not a branch,
and `NewOrderPushNotifier` targets recipients by role/branch through `users`, not through this
table directly.

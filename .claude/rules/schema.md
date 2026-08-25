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
  opens_at, closes_at, is_accepting_orders, is_active
```

`is_accepting_orders` is a manual kill switch separate from opening hours — staff use it when
the kitchen is overwhelmed.

## Menu

```
categories              id, name, slug, tagline, sort_order, is_active, hero_image_path
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
customers           id, phone (unique), name, email, password,
                    phone_verified_at, marketing_opt_in
customer_addresses  id, customer_id, label, ghanapost_code,
                    landmark, lat, lng, is_default
```

`phone` is the identity key, normalised to E.164. A guest row is created on first order;
registration sets `password` on the existing row so history carries over.

Both `ghanapost_code` and free-text `landmark` are required on delivery orders. Riders use both.

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
  subtotal, discount_total, delivery_fee, total,
  payment_method (paystack|cash|momo), payment_status, channel (web|pos),
  promotion_id, delivery_address_snapshot (json),
  rider_id, claimed_at, scheduled_for,
  placed_at, accepted_at, ready_at, dispatched_at,
  delivered_at, cancelled_at, cancellation_reason

order_items
  id, order_id, menu_item_id,
  name_snapshot, unit_price_snapshot, quantity, line_total, notes

order_item_options
  id, order_item_id, option_id, name_snapshot, price_delta_snapshot

order_events
  id, order_id, from_status, to_status,
  actor_type, actor_id, shift_id, meta (json), created_at
```

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

## Shifts

```
shifts  id, user_id, branch_id, started_at, ended_at,
        starting_cash, total_sales, opening_note, closing_note
```

Orders attribute to a shift, not only a user. Terminals get shared between staff; the shift
makes handover explicit and makes "which shift had four cancellations" answerable.

`starting_cash` and `total_sales` are two independent facts, never combined:

- `starting_cash` — the float given to make change at the start of the session. It exists
  purely as a record that "x amount was available at the start" — it is never sales revenue
  and must never be added to, subtracted from, or otherwise combined with `total_sales` in any
  calculation or report. Optional for every role — capturing it is a courtesy, not a
  requirement.
- `total_sales` — what was actually sold during the shift, entered by the person ending it.
  Required for `staff` specifically (`ShiftController::end()`), optional for everyone else.

Both are `unsignedBigInteger` pesewas, both nullable at the column level — "required for
staff" is an application-layer rule (`ShiftController`), not a schema one.

## Delivery zones

```
delivery_zones  id, branch_id, name, delivery_fee,
                min_order_total, radius_metres, centre_lat, centre_lng, is_active
```

Start radius-based. Upgrade to polygons only if radii prove too blunt in practice.

Branch routing: match the customer's coordinates to a zone, take the branch that owns it. If
several match, prefer the nearest branch that is currently accepting orders.

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

## Promotions (v1 scope only)

```
promotions             id, code, type (percentage|fixed), value,
                       min_order_total, starts_at, ends_at,
                       max_redemptions, max_per_customer, is_active
promotion_branch       promotion_id, branch_id
promotion_redemptions  id, promotion_id, order_id, customer_id, amount_discounted
```

Do not add promo types beyond percentage and fixed without being asked.

## Settings

```
settings  id, key (unique), value (text, nullable)
```

General-purpose key-value store for admin-editable business configuration
(`App\Services\Settings\SettingsService`, reached via the "Settings" sidebar item —
`settings.manage`, owner-only). Not branch-owned, so it's exempt from the branch global scope.

Current keys: `order_reference_prefix_pos`, `order_reference_prefix_web` — the prefix
`OrderCreationService::generateReference()` uses per channel (default `YMGS-POS`/`YMGS-WEB`
when unset, so a fresh install needs no seeding). The reference itself is
`{prefix}-{6-character random code}` — letters/digits only, ambiguous characters (0/O, 1/I/L)
excluded, same alphabet as `UserManagementService`'s temporary passwords, since this is another
value read off a screen or receipt and typed back in by hand. Changing a prefix only affects
orders placed after the change; existing `orders.reference` values are never rewritten.

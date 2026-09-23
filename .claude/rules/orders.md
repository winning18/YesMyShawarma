---
paths:
  - "app/Services/Orders/**"
  - "app/Models/Order*.php"
  - "app/Jobs/**"
  - "app/Http/Controllers/**/Order*.php"
---

# Order lifecycle

## State machine

```
pending_payment ──▶ paid ──▶ accepted ──▶ preparing ──▶ ready ──▶ dispatched ──▶ delivered
       │             │          │            │            │            │
       ▼             ▼          ▼            ▼            ▼            ▼
   abandoned     rejected   cancelled    cancelled    cancelled     failed
                                                                       │
                                                                       ▼
                                                                   refunded
```

| Status | Meaning | Who advances it |
|---|---|---|
| `pending_payment` | Created, awaiting Paystack webhook | system |
| `paid` | Payment verified, or cash order confirmed | system |
| `abandoned` | No payment within 30 minutes | scheduled job |
| `accepted` | Branch acknowledged the order | staff / manager |
| `rejected` | Branch declined — closed, out of stock | staff / manager |
| `preparing` | Kitchen started | staff |
| `ready` | Ready for pickup or dispatch | staff |
| `dispatched` | Rider has it, or customer collected | rider / staff |
| `delivered` | Complete | rider |
| `failed` | Delivery could not be completed | rider / manager |
| `cancelled` | Cancelled before delivery | manager or above |
| `refunded` | Money returned | owner |

The `refunded` status here is reachable only from `rejected`/`cancelled`/`failed` — an order
that never actually delivered value, whose money is confirmed returned. This is **not** the
same mechanism as the `refunds` table (see payments.md) that backs actual customer refund
requests, which apply to a `paid`/`delivered`/any-revenue-bearing order and deliberately never
touch this status column at all — the two `refunded` concepts sit next to each other, not on
top of each other. Don't wire the newer feature into this state-machine transition; it was
built to stay independent, on purpose.

## Rules

- Transitions are enforced in `OrderStateMachine`. **No code sets `status` directly** — not
  controllers, not jobs, not tinker scripts, not seeders.
- Illegal transitions throw. Do not silently no-op.
- Every transition writes an `order_events` row with `from_status`, `to_status`,
  `actor_type`, `actor_id`, `shift_id`, and a `meta` JSON payload.
- Each transition also stamps its denormalised timestamp on `orders`.
- Cancellation after `paid` requires a `cancellation_reason` and triggers a refund decision.
- Pickup orders skip the rider entirely: `ready` → `dispatched` on collection.

## Acknowledgement escalation

Restaurant staff will not watch a screen during a dinner rush. This is a first-class
requirement, not a polish item. Orders that sit unacknowledged are the failure mode that
kills the whole system.

- The dashboard plays a **repeating audible alarm** while any order sits in `paid`. It stops
  only when a user accepts or rejects. Not a single chime — a loop.
- Browser tab title shows the pending count so a backgrounded tab still signals.
- Unacknowledged **5 minutes** → SMS the branch manager and any general_manager who oversees
  that branch (permissions.md — general_manager holds everything a manager holds).
- Unacknowledged **10 minutes** → SMS the owner.

**Implement escalation as a scheduled job scanning for `paid` orders past threshold.** Do not
use queued delayed jobs — they are lost on worker restarts, which is exactly when you need
them most.

Escalations write to `order_events` with `meta.escalation_level` so the pattern is auditable.

## Rider assignment

Assignment is **system-driven, not a rider-facing claim pool.** Riders never browse or pick
orders themselves — a rider seeing an order at all means it's already theirs.

Primary responsibility, in order:

1. **System** — automatic, the moment an order reaches `ready` (delivery orders only; pickup
   skips the rider entirely). See `RiderAssignmentService::autoAssign()`.
2. **Staff** — manual override via `orders.assign_rider`, for when auto-assignment finds
   nobody, or a correction is needed (rider went offline, wrong pick, etc.).
3. **Manager / owner** — same manual override, last resort.

Manual assignment isn't the normal path — it exists for the cases automatic assignment can't
resolve on its own.

**Eligibility** for auto-assignment: no shift required — starting one was never an important
part of a rider's day (see permissions.md). Instead, a rider is "available" when they're
**logged in** (a live session row, active within `config('session.lifetime')` — the same window
Laravel itself already treats as "still authenticated") **and currently at the order's branch**
(`users.current_branch_id`, set by `BranchContext::setCurrent()` whenever the branch switcher is
used — see that method's own docblock for why this can't just be read off a session), and not
already carrying another order (`rider_id` on any `ready`/`dispatched` order). See
`RiderAssignmentService::loggedInRiderIds()`. Among eligible riders, the one least recently
assigned goes next (round robin) — `MAX(orders.claimed_at)` per rider, nulls (never assigned)
sorting first.

A rider can hold the `rider` role at more than one branch (permissions.md) — they pick which
one they're working from via the same branch switcher managers use
(`BranchSelectionController`), not a shift. `Rider\DashboardController::data()` deliberately
bypasses `BranchScope` (filters by `rider_id` alone) so an order they're actually carrying never
disappears from their own dashboard just because they've since switched their current branch.

**Assignment is a resource allocation, not an order-status race** — the concurrency risk isn't
two riders claiming the same order (there's no rider-initiated action to race), it's two
orders becoming `ready` at once and both picking the same rider before either commits. Guard
against this by locking the *candidate rider* row (`lockForUpdate()`), not the order: acquire
the lock, re-check eligibility now that any concurrent assignment has had a chance to commit,
then assign. If ineligible, move to the next candidate.

If nobody is eligible, the order stays `ready` with `rider_id` null — no automatic retry queue.
It surfaces on the staff dashboard for manual assignment. If no rider is available at all, the
assign control shows "No riders available" rather than an empty dropdown with nothing to
select, and a distinct one-shot chime (`orderDashboard()`'s `riderAvailableChime()` —
deliberately not the repeating unacknowledged-order alarm; see realtime.md) plays the moment a
rider becomes available *while* an order is actually waiting on one, so staff don't have to keep
checking back manually — this falls out of the existing 15-second poll of `dashboard.riders`
for free, no separate "rider logged in" event needed. `dashboard.riders`
(`RiderAvailabilityController`) is what backs both the dropdown and this — it's filtered to the
`rider` role specifically and to `RiderAssignmentService::loggedInRiderIds()`, the same
eligibility rule auto-assignment itself uses, so the two can never disagree about who counts as
available.

Manual assignment (staff/manager/owner) does not re-check the "not already carrying an order"
eligibility rule — it's a deliberate human override, trusted to know better than the algorithm
in a given moment.

**Broadcasts are cosmetic**, same as anywhere else — `OrderAssignedToRider` (private
`App.Models.User.{riderId}` channel) tells a specific rider's dashboard to refetch. The
database write already decided the assignment before this ever fires.

## Branch transfer

A customer sometimes ends up at the wrong branch — picked the farther of two by mistake, or
ordered for someone else the "nearest branch" logic at checkout never had a chance to account
for (it only ever knows the orderer's own location, not necessarily the delivery recipient's).
`OrderTransferService::transfer()` moves the order to a different branch after the fact,
gated by `orders.transfer_branch` — staff holds this too, unlike the other money-adjacent
permissions (see permissions.md for why: the transfer itself isn't money-touching, but the
refund it can trigger stays behind the same approval boundary staff always has).

- **Only while `paid` or `accepted`.** Once `preparing`, the kitchen has already started —
  that's a cancellation, not a transfer. This also means a rider is never involved yet
  (auto-assignment only fires at `ready`), so there's no rider-reassignment case to handle.
- **Every ordered item must be available at the destination** (`branch_menu_item.is_available`)
  — checked at the point of transfer, not pre-filtered in the destination list shown to staff.
- **Money is never rewritten on an order that's already been charged.** A delivery-fee decrease
  on an already-paid order (`payment_status === 'paid'`) goes back to the customer as a partial
  refund through the existing `refunds` ledger — completed on the spot for
  manager/general_manager/owner (`RefundService::directRefund()`), a pending request needing
  their approval when a staff member is the one transferring (`RefundService::request()`,
  same boundary as `orders.refund_request` everywhere else). There's no
  route to re-run a completed Paystack transaction, so an increase is absorbed instead of
  charged, and recorded in the transfer's `order_events.meta` so it stays visible rather than
  silently eaten. An order where nothing's been collected yet (cash/momo still pending) just
  gets `delivery_fee`/`total` corrected directly, same as OrderStateMachine's own
  delivered-transition fee reconciliation for manually-settled methods.
- Writes one `order_events` bookkeeping row (`from_status === to_status`, same pattern as
  refunds/momo confirmation), never touches `orders.status`.
- Broadcasts `OrderStatusChanged` to the origin branch (its board refetches and the order is
  simply gone, via `BranchScope`) and `OrderPlaced` to the destination (its board refetches and
  picks it up — landing in "Needs acknowledgement" or "In progress" purely off the order's own
  status, same as any other refetch).

## Delivery fee at arrival

`OrderCreationService::resolveDelivery()` prices `delivery_fee` immediately at placement only
when the customer's location was actually captured at checkout. When it wasn't (denied
geolocation, unsupported browser, or the checkout page's explicit opt-out checkbox),
`delivery_fee` deliberately stays at 0 — guessing a flat estimate at placement and correcting
it later turned out to be the wrong shape: it charged some customers for a delivery that ended
up closer, and left staff needing to actively spot and fix every estimate. Instead, nothing is
charged until the rider actually reaches the door.

- **`OrderArrivalService`** (`OrderPolicy::arrive`, the order's own assigned rider only) is
  what the rider's "Arrived" button calls — required before "Mark delivered" becomes available
  on *every* delivery, not just the no-location ones (`OrderResource.arrived_at`, null until
  then, gates that button client-side; the policy and service both re-enforce it server-side).
  If `delivery_fee` is still 0 at that moment, this is also where it finally gets calculated —
  from the *rider's own GPS position* now that they're standing at the customer's location,
  using the same `DeliveryFeeCalculator` rate/rounding/floor as everywhere else, falling back
  to `DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS` if the rider's own geolocation isn't
  available either. A non-zero `delivery_fee` (a real checkout-time location, or a prior
  `DeliveryFeeAdjustmentService` correction) is never overwritten — arrival only ever fills in
  a fee nobody has priced yet. This is a bookkeeping `order_events` row
  (`from_status === to_status`, `meta.action === 'arrived'`), not a new order status — the
  state machine stays `dispatched` → `delivered`/`failed` exactly as documented above.
- **`OrderResource.cash_to_collect`** is what a rider/staff should actually collect in cash —
  never assume `payment_method === 'cash'` is the only case that needs collecting. A paystack
  order only ever had its *subtotal* charged online when location wasn't captured (there's no
  route to charge a Paystack transaction again after the fact), so once the fee is known it's
  still owed in cash even though the order is "paid via Paystack". Before arrival, an
  unpriced delivery's `cash_to_collect` simply doesn't yet include the fee — the rider dashboard
  tells the rider this explicitly rather than letting a 0/low figure read as "nothing to
  collect" (`feePending()` in `resources/views/rider/dashboard.blade.php`).
- **`OrderResource.delivery_fee_is_estimate`** is a yes/no signal (not the coordinate) for
  whether the fee is still open to a manual staff correction rather than precisely priced from
  the customer's own checkout location — true both before and after arrival, since an
  arrival-calculated fee is just as correctable as one still sitting at 0. Safe for
  staff/managers to see even though the raw lat/lng stays rider-only (see schema.md's Customers
  section).
- **`DeliveryFeeAdjustmentService`** (`orders.adjust_delivery_fee`, manager and above — see
  permissions.md) lets staff set or correct the fee for an address whose customer never shared
  a location, at any point before a terminal status — before the rider arrives, or after, same
  as arrival itself never overwriting a value someone already set. Never reachable once the
  customer's own checkout location has precisely priced the fee. Never touches a refund: this
  fee was never charged through Paystack in the first place, so there's nothing to refund or
  absorb, unlike `OrderTransferService`'s money handling — it's purely a correction to what the
  rider is told to collect.

## Totals

Compute in this order, always server-side:

1. `subtotal` = sum of `order_items.line_total`
2. `discount_total` = promotion applied to subtotal, capped at subtotal
3. `delivery_fee` = `DeliveryFeeCalculator::calculate()` — haversine distance from the branch ×
   a flat rate per km, zero for pickup. Only priced here when geolocation was captured at
   checkout; otherwise deferred to the rider marking the order arrived (see this file's
   "Delivery fee at arrival" section, and schema.md's "Delivery areas" section —
   `delivery_areas` itself is a rider-facing label, not part of pricing).
4. `total` = subtotal − discount_total + delivery_fee

Never trust a client-supplied total. Recalculate on every write and reject mismatches.

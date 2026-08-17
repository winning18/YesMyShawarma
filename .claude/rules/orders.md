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

**Eligibility** for auto-assignment: on an active shift at the order's branch, and not already
carrying another order (`rider_id` on any `ready`/`dispatched` order). Among eligible riders,
the one least recently assigned goes next (round robin) — `MAX(orders.claimed_at)` per rider,
nulls (never assigned) sorting first.

**Assignment is a resource allocation, not an order-status race** — the concurrency risk isn't
two riders claiming the same order (there's no rider-initiated action to race), it's two
orders becoming `ready` at once and both picking the same rider before either commits. Guard
against this by locking the *candidate rider* row (`lockForUpdate()`), not the order: acquire
the lock, re-check eligibility now that any concurrent assignment has had a chance to commit,
then assign. If ineligible, move to the next candidate.

If nobody is eligible, the order stays `ready` with `rider_id` null — no automatic retry queue.
It surfaces on the staff dashboard for manual assignment. If no rider is on shift at all, the
assign control shows "No riders available" rather than an empty dropdown with nothing to
select, and a distinct one-shot chime (`orderDashboard()`'s `riderAvailableChime()` —
deliberately not the repeating unacknowledged-order alarm; see realtime.md) plays the moment a
rider comes on shift *while* an order is actually waiting on one, so staff don't have to keep
checking back manually. `dashboard.riders` (`RiderAvailabilityController`) is what backs both
the dropdown and this — it's filtered to the `rider` role specifically, not "anyone on shift":
`shifts` carries no role column (schema.md), so staff and riders both start/end shifts through
the exact same mechanism, and an unfiltered query would list staff in a control reserved for
riders alone.

Manual assignment (staff/manager/owner) does not re-check the "not already carrying an order"
eligibility rule — it's a deliberate human override, trusted to know better than the algorithm
in a given moment.

**Broadcasts are cosmetic**, same as anywhere else — `OrderAssignedToRider` (private
`App.Models.User.{riderId}` channel) tells a specific rider's dashboard to refetch. The
database write already decided the assignment before this ever fires.

## Totals

Compute in this order, always server-side:

1. `subtotal` = sum of `order_items.line_total`
2. `discount_total` = promotion applied to subtotal, capped at subtotal
3. `delivery_fee` = from the matched `delivery_zone`, zero for pickup
4. `total` = subtotal − discount_total + delivery_fee

Never trust a client-supplied total. Recalculate on every write and reject mismatches.

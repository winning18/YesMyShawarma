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
- Unacknowledged **5 minutes** → SMS the branch manager.
- Unacknowledged **10 minutes** → SMS the owner.

**Implement escalation as a scheduled job scanning for `paid` orders past threshold.** Do not
use queued delayed jobs — they are lost on worker restarts, which is exactly when you need
them most.

Escalations write to `order_events` with `meta.escalation_level` so the pattern is auditable.

## Rider claim concurrency

Multiple riders see the same order. A check-then-assign will double-assign on a busy night.

**Claim atomically:**

```sql
UPDATE orders
   SET rider_id = :rider, claimed_at = NOW()
 WHERE id = :order
   AND rider_id IS NULL
   AND status = 'ready'
```

Check affected rows. One row means the claim succeeded. Zero rows means another rider won —
return the winner's name so the UI shows "claimed by Kwame" rather than a generic error.

**The WebSocket broadcast is cosmetic.** It greys the card out quickly for other riders. The
database decides. Never treat a broadcast as authoritative — a rider on a laggy connection
will double-claim.

Default mode is **claim-based**: riders grab from a pool. Dispatch-based assignment
(`orders.assign_rider`) exists as a manager override and a per-branch setting, but claim is
what gets built first.

## Totals

Compute in this order, always server-side:

1. `subtotal` = sum of `order_items.line_total`
2. `discount_total` = promotion applied to subtotal, capped at subtotal
3. `delivery_fee` = from the matched `delivery_zone`, zero for pickup
4. `total` = subtotal − discount_total + delivery_fee

Never trust a client-supplied total. Recalculate on every write and reject mismatches.

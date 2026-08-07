---
paths:
  - "app/Events/**"
  - "app/Listeners/**"
  - "app/Broadcasting/**"
  - "routes/channels.php"
  - "resources/js/**"
---

# Real-time

Laravel Reverb. Self-hosted WebSockets, no per-message pricing.

## Channels

All private except where noted.

| Channel | Audience | Events |
|---|---|---|
| `branch.{id}.orders` | staff, managers, owner at that branch | `OrderPlaced`, `OrderStatusChanged` |
| `App.Models.User.{id}` | that one user | `OrderAssignedToRider` |
| `order.{track_token}` | customer, token-authorised | `OrderStatusChanged` |
| `owner.reports` | owner | `SalePosted` |

Riders don't get a branch-wide channel — there's no claimable pool to broadcast to (see
orders.md's rider assignment section). `OrderAssignedToRider` fires on the specific rider's
own private channel instead, since assignment always targets exactly one person.

## Authorisation

Defined in `routes/channels.php`.

**Authorisation on `branch.{id}.*` must check branch membership, not merely that the user is
authenticated.** This is the single most likely place to accidentally let a rider at branch
one watch branch two's order flow.

`order.{track_token}` authorises on token possession alone — no login. The token is a random
32-char string; treat it as a bearer credential and never log it.

## Broadcasts are cosmetic

Broadcasts update UI quickly. They are never the source of truth.

- Rider assignment is decided by the database write (auto-assign's row lock, or the manual
  override), never by who received a broadcast first. See `.claude/rules/orders.md`.
- The dashboard must reconcile against the server on reconnect. A client that was offline for
  two minutes has a stale board and must refetch, not replay.
- Never derive state from event ordering. Events can arrive out of order or not at all.

## Payload discipline

Broadcast **identifiers and status, not full objects.** The client refetches what it needs.

Reasons: order payloads contain customer phone numbers and addresses, channel membership can
change between broadcast and delivery, and fat payloads make reconnect storms expensive.

Never put customer PII in a broadcast payload.

## Client side

Alpine.js with Laravel Echo. No React or Vue.

The staff dashboard's audible alarm is driven by local state derived from the order list, not
by the broadcast itself — so it still fires correctly after a refetch or reconnect.

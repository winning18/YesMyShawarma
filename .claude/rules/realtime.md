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

## Delivery

Every broadcast event (`OrderPlaced`, `OrderStatusChanged`, `OrderAssignedToRider`) implements
**`ShouldBroadcastNow`, not `ShouldBroadcast`.** A `ShouldBroadcast` event is queued — it is
only ever actually sent once something processes the queue, and this app runs no persistent
queue worker by default (`QUEUE_CONNECTION=database`, nothing consuming it in dev). A queued
broadcast here doesn't fail loudly; it just sits in the `jobs` table forever, and the dashboard
silently never updates in real time — exactly the failure this project hit once already (129
undelivered broadcast jobs piled up before this was caught). `ShouldBroadcastNow` dispatches
inline instead, so delivery can never depend on a worker existing. **Also requires
`php artisan reverb:start` actually running** — a `ShouldBroadcastNow` event with nothing
listening on `REVERB_PORT` fails the same way a queued one silently does, just for a different
reason (nothing to connect to instead of nothing to process the queue).

Dispatching inline means a broadcast failure (Reverb briefly unreachable, a network blip)
happens synchronously, in the same request that placed or updated the order — with no queue
worker's catch-and-log safety net between it and the request. **Every dispatch of one of these
events goes through `App\Support\SafeBroadcast::afterCommit()`, never a bare
`DB::afterCommit(fn () => Event::dispatch(...))`.** It defers to the transaction commit exactly
like `DB::afterCommit` always did, but also catches and `report()`s anything the dispatch
throws — broadcasts are cosmetic (below), so a failed one must never turn into a 500 on an
order that otherwise placed or updated successfully.

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

The audible "unacknowledged order" alarm (`partials/order-alert-script.blade.php`,
`orderAlertWidget()`) lives in `dashboard/_channel-header.blade.php` — shared by the Orders
board, POS, and Order History — not inside `orders/dashboard.blade.php`'s own component. Staff
working POS has no reason to be looking at the Orders board, so the alert has to work
independent of whichever of those three pages happens to be open. It's driven by its own
refetch of `dashboard.orders.data` on every `OrderPlaced`/`OrderStatusChanged` broadcast (or a
20s poll for owner's branch-less aggregate view, same fallback the Orders board itself uses),
not by the broadcast payload directly — so it still fires correctly after a refetch or
reconnect. `orders/dashboard.blade.php`'s own component only owns the order-card lists and the
browser tab title now; it does not run its own alarm loop, to avoid two independent alarms
double-beeping on the one page that includes both.

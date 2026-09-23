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

## Web Push

Echo/broadcasts and the in-page alarm above only ever work while the tab is open and its JS is
actually running — a backgrounded or minimised browser tab (and especially a locked mobile
screen) gets throttled or fully suspended by the OS/browser, silently killing both. Web Push
(`minishlink/web-push`, `App\Contracts\PushNotifier`) exists specifically to reach staff/
manager/general_manager through the OS's own notification tray in that situation, independent
of whether the tab or even the browser app is in the foreground.

- **`App\Contracts\PushNotifier`** mirrors `Notifier`'s shape (SMS) — `WebPushNotifier` (real,
  bound once `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` are configured) or `LogPushNotifier`
  (fallback, local/testing), chosen in `AppServiceProvider` the same way `Notifier` is.
- **`push_subscriptions`** (schema.md) is one row per subscribed browser/device, not per user —
  the same person logged in on a phone and a tablet gets a push on both. `endpoint` (the
  browser's own push-service URL) is the natural unique key; re-subscribing the same browser
  updates the row in place. `PushSubscriptionController` (`/push/subscribe`,
  `/push/unsubscribe`) registers/removes these — called from `partials/order-alert-script.blade.php`'s
  `registerPush()`, the same shared component the in-page alarm lives in, so it's registered
  wherever staff/manager might plausibly have the app open.
- **`App\Services\Notifications\NewOrderPushNotifier`** is the push equivalent of the in-page
  alarm — same audience (staff, manager, general_manager at the order's branch; never owner,
  who doesn't operate this board — `OrderDashboardController::index()`'s own docblock), same
  trigger condition (an order genuinely `'paid'`, needing acceptance). Two sends per order, not
  one: staff and manager/general_manager land on different routes for "the live board"
  (`OrderDashboardController::index()` redirects the latter to `dashboard.orders.live`), so the
  notification's click-through has to match whichever it actually is for that recipient.
- **Three trigger points**, each gated on the order actually being (or becoming) `'paid'`, never
  on placement alone:
  - `OrderCreationService::create()` — a cash/momo order is `'paid'` immediately.
  - `OrderStateMachine::transition()`, `$to === 'paid'` — a Paystack order's own push, once the
    webhook confirms it (it was still `pending_payment`, and so deliberately skipped, at
    creation).
  - `OrderTransferService::transfer()` — the destination branch's staff have never seen this
    order; pushed only if it's still `'paid'` (unaccepted) at transfer time.
  All three reuse `SafeBroadcast::afterCommit()` — a push is exactly as cosmetic and
  best-effort as a broadcast, same "never break the business transaction over this" reasoning,
  even though the class name says "Broadcast."
- **`public/sw.js`** is a minimal service worker whose only job is handling `push` and
  `notificationclick` — not a full PWA/offline-cache worker (CLAUDE.md's performance budget is
  a separate, unimplemented concern). A push is only ever delivered while some service worker
  is registered for that origin, which is the only reason this file exists.
- A `410 Gone`/expired-subscription response from the push service means the browser itself
  dropped it (uninstalled, cleared site data, expired) — `WebPushNotifier` deletes that row
  rather than retrying it forever on every future order.

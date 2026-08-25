---
paths:
  - "app/Services/Payments/**"
  - "app/Http/Controllers/**/*Webhook*.php"
  - "app/Http/Controllers/**/*Payment*.php"
  - "config/services.php"
---

# Payments

Paystack. Cards and mobile money (MoMo). Currency GHS, stored in pesewas as integers.

## `orders.payment_method` values

- `cash` — both channels, both fulfilment types. **Reconciliation timing depends on who
  actually collects the money**, not on channel — see "Cash" below.
- `momo` — **POS only.** A manual, in-house payment reconciled by transaction ID, not routed
  through Paystack — see "Momo (POS)" below. Web checkout offers Momo only via `paystack`
  (the customer picks it at Paystack's hosted page).
- `paystack` — web checkout only. The *only* value `PaystackPaymentService` will initialise a
  transaction for. Paystack's own hosted page lets the customer pick card or Momo; the system
  doesn't know which, so this stays a single generic value.

`cash` and `momo` are both `Order::MANUALLY_SETTLED_PAYMENT_METHODS` — both make the order
enter at `status = 'paid'` immediately (`OrderCreationService::create()`), with their own
`payments` row, no online transaction to wait on. The kitchen proceeds either way; only
`payment_status` (the reconciliation flag, not the order's business state) differs by method
and by fulfilment type — see below. `PaystackPaymentService::initializeForOrder()` rejects
anything but `paystack` — `momo` included.

Reports (`OrderReportService::financialSummary()`'s `revenue_by_payment_method`) group by
whatever string is stored here — a new value shows up as its own row with no code change.

## The webhook is the only source of truth

**Never mark an order paid because of what a client-side callback or a redirect return says.**
The query string Paystack appends to the callback URL (`?reference=...&trxref=...`) is
trivially forged and fires before the money settles — never branch on it, never treat its
presence as meaning anything.

Flow:

1. Order created as `pending_payment`, Paystack transaction initialised server-side
2. Customer pays
3. Paystack POSTs the webhook
4. Verify the signature, then transition to `paid`
5. The redirect page itself decides nothing from what's in the URL

**Narrow, deliberate exception** — the redirect *landing* (not its query string) may trigger a
fresh server-to-server call to Paystack's own `GET /transaction/verify/:reference`
(`PaystackClient::verifyTransaction()`), authenticated with our secret key, to ask Paystack
directly rather than assume the webhook already landed. This exists because webhook delivery
isn't instant or 100% guaranteed, and a customer sitting on a blank "confirming..." page for a
webhook that's delayed (or, rarely, lost) is a real cost. The distinction that keeps this
inside the spirit of the rule above: **nothing about the redirect itself — not its arrival, not
its query string — is ever trusted as proof.** Only Paystack's own authoritative response to
our own outbound API call can. That response is fed through the exact same
`PaystackPaymentService::confirmPayment()` method the webhook calls, under the same
`payments`-row lock and the same idempotency check — whichever of the two (webhook or
verify-on-return) gets there first does the real work; the other is a safe no-op. See
`CheckoutController::paystackReturn()`.

## Webhook requirements

- **Verify the signature** on every request before parsing the body. Reject unsigned or
  mismatched requests with a 400 and log them.
- **Idempotent.** Paystack retries. Key on `payments.provider_reference`, which is unique.
  A duplicate delivery must be a no-op, not a second transition.
- **Exempt from rate limiting.** Add the route to the throttle exclusion list explicitly.
- **Store `raw_payload`** on the `payments` row before acting on it. When a payment dispute
  arrives in three months, this is the only record that matters.
- Respond 200 quickly. Do heavy work in a queued job, not in the request.
- Amount must be reconciled against `orders.total`. A mismatch is never accepted silently —
  log it, alert, and leave the order in `pending_payment`.

## Cash

Supported alongside prepayment. Do not remove it — forcing prepayment will cost orders.

Cash orders skip `pending_payment` and enter at `status = 'paid'` immediately
(`OrderCreationService`). What differs is **when `payment_status` becomes `'paid'`**, split by
`fulfilment_type` — the physical reality of who is actually holding the cash:

- **Pickup** (either channel — a customer at the counter, or a phone order collected in
  person): staff have the cash in hand the moment the order is entered.
  `payment_status = 'paid'` immediately, same instant as `status`. The POS UI requires a
  confirmation prompt before submitting ("confirm you've received cash payment") — a real
  reconciliation claim, not a formality.
- **Delivery** (either channel): nobody has been paid yet — the rider collects it at the door.
  `payment_status` stays `'pending'` until `OrderStateMachine`'s `delivered` transition, which
  is the rider's own confirmation that they collected it (a warning pop-up on "mark
  delivered" — "confirm you've collected cash from the customer" — gates the request; this is
  the rider vouching for the money, treat it with the same weight as the POS prompt above).
  A staff member marking a dispatched order delivered from the main dashboard (bypassing the
  rider app) gets the identical prompt — the confirmation belongs to the action, not the actor.

`OrderStateMachine` never flips `payment_status` for `momo` — see below, it has its own path.

Track the cash/prepaid split per branch. It is the evidence base for deciding whether to keep
cash on delivery at all.

## Momo (POS)

POS only, never delivery-timing-dependent — Momo is reconciled by **transaction ID**, not by
who's holding what. Staff read the transaction ID off the customer's Momo confirmation SMS and
enter it at the POS terminal.

- Entered at placement (`PlaceOrderData::$paymentReference`): the `payments` row and
  `orders.payment_status` are `'paid'` immediately, `provider_reference` set to the ID.
- **Skipped** at placement (busy-hour flow — don't block order entry on it): `payment_status`
  stays `'pending'`. Staff enter it later from the order-detail page or
  `orders.confirm_momo_payment`, which calls
  `PaymentConfirmationService::confirmMomo()` — this is the *only* other path to `'paid'` for a
  momo order. It writes an `order_events` row (`meta.action = 'momo_transaction_confirmed'`,
  `from_status === to_status` — no state transition, this is a payment-reconciliation write,
  not an order-status one) but never touches `status` — the kitchen already started regardless.
- `provider_reference` on `payments` is the transaction ID here, reusing the same unique
  column Paystack uses for its webhook idempotency key — a duplicate/reused ID is rejected
  (`PaymentException::duplicateTransactionReference`), it is not a formality field.

## Refunds

Own table (`refunds`, `App\Models\Refund`), own service (`App\Services\Orders\RefundService`),
own controller (`RefundController`) — request → approve/deny → complete, editable amount up to
whatever remains refundable on the order (`RefundService::remainingBalance()` — order total
minus every already-*completed* refund; pending/denied requests never reserve budget).

- **`orders.refund`** (owner, manager, general_manager — all three hold identical refund
  rights, unlike everywhere else `manager`/`general_manager` sit a tier below `owner`) can
  request-and-immediately-complete a refund in one action (`RefundService::directRefund()` —
  request + approve + complete in a single transaction, so the role skipping approval isn't
  forced to approve its own request) and can approve/deny anyone else's pending request.
  Branch-scoped like every other order permission (`RefundPolicy::checkAtRefundBranch`) — a
  manager only holds it at their own branch, general_manager at every branch they hold that
  role at, same as `orders.void`/`orders.discount`/etc.
- **`orders.refund_request`** (staff only) can only request — creates a `pending` row that
  needs an `orders.refund` holder to approve before it can be completed. Once approved,
  completing it (actually processing the refund) needs `orders.refund_request` or `orders.refund`
  — either is enough, since the person completing it doesn't have to be the one who approved it.
- A refund is **never** for an unpaid order (`payment_status !== 'paid'` is rejected outright),
  and the amount is capped at the order's remaining refundable balance, re-checked with a row
  lock at completion time (`RefundService::complete()`) so two completions racing each other can
  never together refund more than the order is worth — same reasoning as
  `OrderStateMachine::transition()`'s own row lock.

**Refunds never touch `orders.status` or go through `OrderStateMachine`.** A refund is a
financial ledger event, not a change to the order's operational workflow — an order that was
legitimately `delivered` stays `delivered` after being refunded. This is deliberate: flipping
status to `'refunded'` would drop the order out of `Order::NON_REVENUE_STATUSES`-based gross-
revenue queries for the day it was *placed*, when what should happen is gross revenue stays put
and the refund is subtracted from the day it was *completed* instead (see
`OrderReportService::financialSummary()`, and schema.md's Orders section on why the pre-existing
`rejected/cancelled/failed → refunded` state-machine transition is left alone, untouched by this
feature, rather than reused or removed).

For a `paystack`-paid order, `complete()` calls Paystack's refund API
(`PaystackClient::refundTransaction()`) against the original charge's `payments.provider_reference`
— never by hand-editing status. Cash/momo orders have no gateway to call; completing one just
records the refund (a new `payments` row, `status = 'refunded'`, `provider_reference` null) —
the actual cash/momo handback happens outside the app, same as how those methods are collected
in the first place. Either way, a refund writes both a `payments` row and an `order_events` row
(`meta.action = 'refund_requested' | 'refund_approved' | 'refund_denied' | 'refund_completed'`,
`from_status === to_status` — a bookkeeping write, not a status transition, same pattern as
momo-transaction confirmation above).

## Secrets

Keys live in `.env`, referenced through `config/services.php`. **Never inline a key, never
commit one, never log a full payload containing one.** If a key appears in a diff, stop and
raise it.

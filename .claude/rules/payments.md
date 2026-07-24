---
paths:
  - "app/Services/Payments/**"
  - "app/Http/Controllers/**/*Webhook*.php"
  - "app/Http/Controllers/**/*Payment*.php"
  - "config/services.php"
---

# Payments

Paystack. Cards and mobile money (MoMo). Currency GHS, stored in pesewas as integers.

## The webhook is the only source of truth

**Never mark an order paid from a client-side callback or a redirect return.** Both are
trivially forged and both fire before the money settles.

Flow:

1. Order created as `pending_payment`, Paystack transaction initialised server-side
2. Customer pays
3. Paystack POSTs the webhook
4. Verify the signature, then transition to `paid`
5. The redirect page merely polls order status — it decides nothing

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

## Cash on delivery

Supported alongside prepayment. Do not remove it — forcing prepayment will cost orders.

Cash orders skip `pending_payment` and enter at `paid` with `payment_status = 'pending'`.
The rider settles on delivery, which transitions `payment_status` to `paid` at the same time
the order transitions to `delivered`.

Track the cash/prepaid split per branch. It is the evidence base for deciding whether to keep
cash on delivery at all.

## Refunds

`orders.refund` is owner-only. Refunds are initiated through Paystack's API, never by hand-
editing status. A refund writes both a `payments` row and an `order_events` row.

## Secrets

Keys live in `.env`, referenced through `config/services.php`. **Never inline a key, never
commit one, never log a full payload containing one.** If a key appears in a diff, stop and
raise it.

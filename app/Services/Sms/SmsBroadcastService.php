<?php

namespace App\Services\Sms;

use App\Contracts\Notifier;
use App\Exceptions\SmsBroadcastException;
use App\Jobs\SendSmsBroadcastBatch;
use App\Models\Customer;
use App\Models\SmsBroadcast;
use App\Models\SmsBroadcastRecipient;
use App\Models\User;
use App\Services\Customers\CustomerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SMS broadcasts to customers (Customers → Broadcasts). Goes to every
 * customer with a phone who hasn't tapped the opt-out link — an owner
 * decision (2026-10-18), not marketing_opt_in, which nothing collects.
 *
 * There is no separate contact list to sync or de-duplicate: the audience
 * is read live from `customers` when a broadcast starts, so new customers
 * are always included, and numbers are normalised and de-duplicated then.
 *
 * Sending runs through the same Notifier (Arkesel, ARKESEL_SENDER_ID) as
 * order SMS, one message per recipient because each carries its own
 * opt-out link. "Send now" is a broadcast scheduled for now — one path:
 * sms:send-due-broadcasts (every minute) starts it and queues a
 * SendSmsBroadcastBatch for every broadcast still sending; each job sends
 * for up to SECONDS_PER_JOB, and the next minute's run picks up the rest.
 */
class SmsBroadcastService
{
    /** Pending recipients read per query while sending. */
    public const BATCH_SIZE = 50;

    /** How long one SendSmsBroadcastBatch job keeps sending — inside the worker's 60s timeout even with one slow send left over. */
    public const SECONDS_PER_JOB = 40;

    // GSM 03.38 — anything outside these two sets forces the whole SMS
    // into UCS-2, which drops a part from 160 to 70 characters.
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXTENDED = '^{}\\[~]|€';

    public function __construct(
        private readonly Notifier $notifier,
        private readonly CustomerService $customers,
    ) {}

    /**
     * Who a broadcast would reach right now, after normalising and
     * de-duplicating numbers.
     *
     * @return array{recipients: array<int, string>, duplicates: int, invalid: int, opted_out: int}
     */
    public function audience(?int $branchId): array
    {
        $base = Customer::query()
            ->whereNotNull('phone')
            ->when($branchId, fn ($q) => $q->whereExists(
                // Plain query builder, not the Order model — BranchScope
                // would otherwise narrow this to the viewer's current branch.
                fn ($sub) => $sub->select(DB::raw(1))->from('orders')
                    ->whereColumn('orders.customer_id', 'customers.id')
                    ->where('orders.branch_id', $branchId)
            ));

        $optedOut = (clone $base)->whereNotNull('marketing_opted_out_at')->count();

        $recipients = [];
        $seen = [];
        $duplicates = 0;
        $invalid = 0;

        $base->whereNull('marketing_opted_out_at')->orderBy('id')->select(['id', 'phone'])
            ->each(function (Customer $customer) use (&$recipients, &$seen, &$duplicates, &$invalid) {
                if (! $this->customers->isValidGhanaPhone($customer->phone)) {
                    $invalid++;

                    return;
                }

                $phone = $this->customers->normalizeGhanaPhone($customer->phone);

                if (isset($seen[$phone])) {
                    $duplicates++;

                    return;
                }

                $seen[$phone] = true;
                $recipients[$customer->id] = $phone;
            });

        return ['recipients' => $recipients, 'duplicates' => $duplicates, 'invalid' => $invalid, 'opted_out' => $optedOut];
    }

    /**
     * The exact text one recipient receives: the broadcast's own message
     * plus their personal opt-out link.
     */
    public function composeFor(string $message, string $optOutCode): string
    {
        // No "https://" — phones link a bare domain anyway, and every
        // character here is paid for.
        $url = preg_replace('#^https?://#', '', route('sms.opt-out.show', $optOutCode));

        return rtrim($message)."\n".__('Stop: :url', ['url' => $url]);
    }

    /**
     * How many SMS one message is billed as.
     */
    public function smsParts(string $text): int
    {
        $length = 0;
        $unicode = false;

        foreach (mb_str_split($text) as $char) {
            if (str_contains(self::GSM_BASIC, $char)) {
                $length++;
            } elseif (str_contains(self::GSM_EXTENDED, $char)) {
                $length += 2;
            } else {
                $unicode = true;
                break;
            }
        }

        if ($unicode) {
            $length = intdiv(strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);

            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    /**
     * @throws SmsBroadcastException
     */
    public function schedule(string $message, ?int $branchId, ?Carbon $scheduledAt, User $createdBy): SmsBroadcast
    {
        $scheduledAt = ($scheduledAt ?? now())->clone()->utc();

        if ($this->audience($branchId)['recipients'] === []) {
            throw SmsBroadcastException::noRecipients();
        }

        return SmsBroadcast::create([
            'message' => $message,
            'branch_id' => $branchId,
            'status' => SmsBroadcast::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * @throws SmsBroadcastException
     */
    public function cancel(SmsBroadcast $broadcast): void
    {
        $cancelled = SmsBroadcast::whereKey($broadcast->id)
            ->where('status', SmsBroadcast::STATUS_SCHEDULED)
            ->update(['status' => SmsBroadcast::STATUS_CANCELLED]);

        if (! $cancelled) {
            throw SmsBroadcastException::notCancellable($broadcast->fresh()->status);
        }
    }

    /**
     * Starts every broadcast whose time has come. Called every minute by
     * sms:send-due-broadcasts. Returns how many were started.
     */
    public function startDue(): int
    {
        $started = 0;

        SmsBroadcast::where('status', SmsBroadcast::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->pluck('id')
            ->each(function (int $id) use (&$started) {
                // Claimed atomically: a cancel, or an overlapping scheduler
                // run, can't start the same broadcast twice.
                $claimed = SmsBroadcast::whereKey($id)
                    ->where('status', SmsBroadcast::STATUS_SCHEDULED)
                    ->update(['status' => SmsBroadcast::STATUS_SENDING, 'started_at' => now()]);

                if (! $claimed) {
                    return;
                }

                $this->buildRecipients(SmsBroadcast::findOrFail($id));
                $started++;
            });

        // Every broadcast still sending gets a job each minute — the one
        // just started, and any whose last job ran out of time or died
        // part-way. The job's overlap lock drops a duplicate.
        SmsBroadcast::where('status', SmsBroadcast::STATUS_SENDING)
            ->pluck('id')
            ->each(fn (int $id) => SendSmsBroadcastBatch::dispatch($id));

        return $started;
    }

    /**
     * Texts pending recipients until none are left or $until passes.
     * Returns true while more remain; marks the broadcast sent once none do.
     */
    public function send(SmsBroadcast $broadcast, ?Carbon $until = null): bool
    {
        if ($broadcast->status !== SmsBroadcast::STATUS_SENDING) {
            return false;
        }

        do {
            $batch = $broadcast->recipients()->where('status', 'pending')->orderBy('id')->limit(self::BATCH_SIZE)->get();

            foreach ($batch as $recipient) {
                if ($until && now()->gte($until)) {
                    break 2;
                }

                $sent = $this->notifier->send(
                    $recipient->phone,
                    $this->composeFor($broadcast->message, $recipient->opt_out_code),
                    ['sms_broadcast_id' => $broadcast->id],
                );

                $recipient->update($sent
                    ? ['status' => 'sent', 'sent_at' => now(), 'error' => null]
                    : ['status' => 'failed', 'error' => 'Rejected by SMS provider']);
            }
        } while ($batch->count() === self::BATCH_SIZE);

        $counts = $broadcast->recipients()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $morePending = ($counts['pending'] ?? 0) > 0;

        $broadcast->update([
            'sent_count' => $counts['sent'] ?? 0,
            'failed_count' => $counts['failed'] ?? 0,
            ...($morePending ? [] : ['status' => SmsBroadcast::STATUS_SENT, 'completed_at' => now()]),
        ]);

        return $morePending;
    }

    /**
     * The customer behind an opt-out link, or null for an unknown code.
     */
    public function recipientForCode(string $code): ?SmsBroadcastRecipient
    {
        return SmsBroadcastRecipient::with('customer')->where('opt_out_code', $code)->first();
    }

    /**
     * Opts the link's customer out of every later broadcast. Idempotent —
     * tapping the link twice keeps the first opt-out time.
     */
    public function optOut(SmsBroadcastRecipient $recipient): void
    {
        Customer::whereKey($recipient->customer_id)
            ->whereNull('marketing_opted_out_at')
            ->update(['marketing_opted_out_at' => now()]);
    }

    private function buildRecipients(SmsBroadcast $broadcast): void
    {
        $now = now();

        $rows = collect($this->audience($broadcast->branch_id)['recipients'])
            ->map(fn (string $phone, int $customerId) => [
                'sms_broadcast_id' => $broadcast->id,
                'customer_id' => $customerId,
                'phone' => $phone,
                'opt_out_code' => Str::random(8),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values();

        $rows->chunk(500)->each(fn ($chunk) => SmsBroadcastRecipient::insertOrIgnore($chunk->all()));

        $broadcast->update(['recipient_count' => $broadcast->recipients()->count()]);
    }
}

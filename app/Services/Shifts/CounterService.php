<?php

namespace App\Services\Shifts;

use App\Exceptions\CounterException;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\Terminal;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as CookieValue;

/**
 * Counters (Express POS step 7a, owner 2026-11-15): several cashiers as
 * named counters per branch, each with a till of its own.
 *
 * A branch with no counters works as it always has — one shared shift.
 * Once it has counters, POS sales are only taken on a device a manager
 * has set up as one of them: the device keeps a secret in a long-lived
 * cookie, and the counter keeps only its SHA-256, so a copied database
 * can't be used to impersonate a counter.
 */
class CounterService
{
    public const COOKIE = 'express_counter';

    /** Ten years: a counter stays set up until a manager says otherwise. */
    private const COOKIE_MINUTES = 60 * 24 * 365 * 10;

    private Terminal|false|null $current = null;

    /** The request $current was read for — a new request reads it afresh. */
    private ?object $readFor = null;

    /**
     * The counter this request's device is set up as, if any.
     */
    public function current(): ?Terminal
    {
        if ($this->readFor !== request()) {
            $this->current = null;
            $this->readFor = request();
        }

        if ($this->current === null) {
            $token = request()->cookie(self::COOKIE);

            $this->current = is_string($token) && $token !== ''
                ? (Terminal::where('device_token_hash', hash('sha256', $token))->where('is_active', true)->first() ?? false)
                : false;
        }

        return $this->current ?: null;
    }

    /**
     * The device's counter, only if it belongs to this branch.
     */
    public function currentAt(int $branchId): ?Terminal
    {
        $counter = $this->current();

        return $counter && (int) $counter->branch_id === $branchId ? $counter : null;
    }

    public function branchHasCounters(int $branchId): bool
    {
        return Terminal::where('branch_id', $branchId)->where('is_active', true)->exists();
    }

    /**
     * A branch with counters takes POS sales only on one of them.
     */
    public function canTakeSalesHere(int $branchId): bool
    {
        return ! $this->branchHasCounters($branchId) || $this->currentAt($branchId) !== null;
    }

    /**
     * @throws CounterException while the branch's shared shift is open —
     *                          its till would otherwise be left with no way
     *                          to close it.
     */
    public function create(Branch $branch, string $name): Terminal
    {
        if (! $this->branchHasCounters($branch->id)
            && Shift::where('branch_id', $branch->id)->whereNull('terminal_id')->whereNull('ended_at')->exists()) {
            throw CounterException::sharedShiftOpen();
        }

        return Terminal::create(['branch_id' => $branch->id, 'name' => trim($name)]);
    }

    /**
     * @throws CounterException while the counter's till is open
     */
    public function update(Terminal $counter, string $name, bool $active, ?int $receiptPrinterId = null): Terminal
    {
        if (! $active && $counter->is_active && $this->openTill($counter)) {
            throw CounterException::tillOpen($counter->name);
        }

        $counter->update(['name' => trim($name), 'is_active' => $active, 'receipt_printer_id' => $receiptPrinterId]);

        return $counter;
    }

    /**
     * Sets this device up as the counter, replacing any device it had
     * before. Returns the cookie the response must carry.
     */
    public function pair(Terminal $counter): CookieValue
    {
        $token = Str::random(64);
        $counter->update(['device_token_hash' => hash('sha256', $token), 'paired_at' => now()]);
        $this->current = null;

        return Cookie::make(self::COOKIE, $token, self::COOKIE_MINUTES, httpOnly: true, sameSite: 'lax');
    }

    /** The counter forgets its device; that device is no longer a counter. */
    public function unpair(Terminal $counter): void
    {
        $counter->update(['device_token_hash' => null, 'paired_at' => null]);
        $this->current = null;
    }

    /** This device stops being a counter (the counter keeps no device). */
    public function forgetThisDevice(): CookieValue
    {
        if ($counter = $this->current()) {
            $this->unpair($counter);
        }

        return Cookie::forget(self::COOKIE);
    }

    public function openTill(Terminal $counter): ?Shift
    {
        return Shift::where('terminal_id', $counter->id)->whereNull('ended_at')->latest('started_at')->first();
    }
}

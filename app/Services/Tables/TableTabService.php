<?php

namespace App\Services\Tables;

use App\Exceptions\PaymentException;
use App\Exceptions\TableTabException;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\TableTab;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tables (owner, 2026-11-20; the `tables` module). Eat-in guests order in
 * rounds: each round is an ordinary POS order on the table's open bill
 * (orders.table_tab_id), sent to the kitchen straight away, its payment
 * pending. The bill is settled once, by cash or MoMo, at a till: every
 * round is then paid, and moves to that till (orders.shift_id), which
 * counts the money. A till can't close while a bill it took rounds for is
 * still open. Staff make tables as they need them; a bill can move to a
 * free table. One bill, one payment (no splitting, owner 2026-11-20).
 */
class TableTabService
{
    /** A branch's first tables, made the first time it uses tables (owner, 2026-11-20). */
    public const STARTER_TABLES = ['1', '2', '3'];

    /** Round statuses that are not on the bill. */
    public const OFF_THE_BILL = ['cancelled', 'rejected', 'refunded', 'abandoned', 'failed'];

    /**
     * A branch's active tables, each with its open bill (rounds, total,
     * since when).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tablesFor(int $branchId): Collection
    {
        if (! DiningTable::where('branch_id', $branchId)->exists()) {
            foreach (self::STARTER_TABLES as $name) {
                DiningTable::firstOrCreate(['branch_id' => $branchId, 'name' => $name]);
            }
        }

        return DiningTable::where('branch_id', $branchId)->where('is_active', true)
            ->with(['openTab.orders' => fn ($q) => $q->select('id', 'table_tab_id', 'reference', 'status', 'total', 'placed_at')])
            ->get()
            ->sortBy(fn (DiningTable $table) => [strlen($table->name), $table->name])
            ->values()
            ->map(fn (DiningTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'tab' => $table->openTab ? $this->summary($table->openTab) : null,
            ]);
    }

    /**
     * @return array{id: int, opened_at: string, rounds: int, total: int, customer_name: ?string, customer_phone: ?string}
     */
    public function summary(TableTab $tab): array
    {
        $rounds = $this->rounds($tab);

        return [
            'id' => $tab->id,
            'reference' => $tab->reference,
            'opened_at' => $tab->opened_at->clone()->timezone('Africa/Accra')->format('H:i'),
            'rounds' => $rounds->count(),
            'total' => (int) $rounds->sum('total'),
            'customer_name' => $tab->customer_name,
            'customer_phone' => $tab->customer_phone,
        ];
    }

    /**
     * A bill's own number (owner, 2026-11-20): the business's POS prefix,
     * T, and four characters read off a receipt easily, e.g. YMGS-T-4KQ7.
     */
    public function newReference(): string
    {
        $prefix = app(SettingsService::class)->prefix(SettingsService::ORDER_REFERENCE_PREFIX_POS);
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = "{$prefix}-T-{$code}";
        } while (TableTab::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * "Bill YMGS-T-4KQ7 · round 2 of 3" for one round, or null for an
     * order that isn't a table round.
     *
     * @return array{bill: string, round: int, of: int}|null
     */
    public function roundOf(Order $order): ?array
    {
        if (! $order->table_tab_id || ! $order->tableTab) {
            return null;
        }

        $ids = $order->tableTab->orders()->whereNotIn('status', self::OFF_THE_BILL)->orderBy('id')->pluck('id')->all();
        $position = array_search($order->id, $ids, true);

        return [
            'bill' => (string) $order->tableTab->reference,
            'round' => $position === false ? 0 : $position + 1,
            'of' => count($ids),
        ];
    }

    public function rename(DiningTable $table, string $name): DiningTable
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $clash = DiningTable::where('branch_id', $table->branch_id)->where('name', $name)->whereKeyNot($table->id)->first();

        if ($clash && $clash->is_active) {
            throw TableTabException::nameTaken($name);
        }
        // A deleted table's old name can be used again.
        $clash?->update(['name' => mb_substr($clash->name.' (#'.$clash->id.')', 0, 40)]);

        $table->update(['name' => $name]);

        return $table;
    }

    /** Free tables only; their past bills stay in reports and history. */
    public function delete(DiningTable $table): void
    {
        if (TableTab::where('dining_table_id', $table->id)->whereNull('settled_at')->exists()) {
            throw TableTabException::tableInUse($table->name);
        }

        $table->update(['is_active' => false]);
    }

    /** The table's customer, kept with its bill until it's paid. */
    public function setCustomer(TableTab $tab, ?string $name, ?string $phone): TableTab
    {
        $tab->update([
            'customer_name' => filled($name) ? trim($name) : null,
            'customer_phone' => filled($phone) ? $phone : null,
        ]);

        return $tab;
    }

    /**
     * The rounds on the bill (not cancelled or rejected), oldest first.
     *
     * @return Collection<int, Order>
     */
    public function rounds(TableTab $tab): Collection
    {
        return $tab->orders->whereNotIn('status', self::OFF_THE_BILL)->sortBy('id')->values();
    }

    public function createTable(int $branchId, string $name): DiningTable
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $table = DiningTable::firstOrCreate(['branch_id' => $branchId, 'name' => $name], ['is_active' => true]);

        if (! $table->is_active) {
            $table->update(['is_active' => true]);
        }

        return $table;
    }

    /**
     * The table's open bill, opened now if it has none. Locks the table so
     * two tills never open two bills on it at once.
     */
    public function openTabFor(DiningTable $table, User $by): TableTab
    {
        return DB::transaction(function () use ($table, $by) {
            $locked = DiningTable::whereKey($table->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                throw TableTabException::tableInactive();
            }

            return TableTab::where('dining_table_id', $locked->id)->whereNull('settled_at')->first()
                ?? TableTab::create([
                    'branch_id' => $locked->branch_id,
                    'dining_table_id' => $locked->id,
                    'reference' => $this->newReference(),
                    'opened_by' => $by->id,
                    'opened_at' => now(),
                ]);
        });
    }

    /**
     * Pays the bill: every round on it becomes paid by $method, in $till.
     * A bill with nothing on it (every round cancelled) just closes.
     */
    public function settle(TableTab $tab, string $method, ?string $reference, User $by, ?Shift $till, string $actorType = 'staff'): TableTab
    {
        $reference = $reference !== null && trim($reference) !== '' ? trim($reference) : null;

        // Only when the owner checks MoMo by transaction ID (2026-11-20).
        if ($method === 'momo' && $reference === null && app(SettingsService::class)->checksMomoTransactionId()) {
            throw TableTabException::momoNeedsReference();
        }

        return DB::transaction(function () use ($tab, $method, $reference, $by, $till, $actorType) {
            $tab = TableTab::whereKey($tab->id)->lockForUpdate()->firstOrFail();

            if (! $tab->isOpen()) {
                throw TableTabException::alreadySettled();
            }

            if ($reference !== null && Payment::where('provider_reference', $reference)->exists()) {
                throw PaymentException::duplicateTransactionReference($reference);
            }

            $rounds = $this->rounds($tab->load('orders'));
            $tillId = $till !== null && $till->branch_id === $tab->branch_id ? $till->id : null;

            foreach ($rounds as $i => $order) {
                $order->payment_method = $method;
                $order->payment_status = 'paid';
                if ($tillId !== null) {
                    $order->shift_id = $tillId;
                }
                $order->save();

                $payment = $order->payments()->whereIn('provider', Order::MANUALLY_SETTLED_PAYMENT_METHODS)->first()
                    ?? $order->payments()->make(['currency' => 'GHS']);
                $payment->fill([
                    'provider' => $method,
                    // One MoMo transfer pays the whole bill: its ID goes on the first round.
                    'provider_reference' => $i === 0 ? $reference : null,
                    'amount' => $order->total,
                    'status' => 'paid',
                    'verified_at' => now(),
                ])->save();

                $order->events()->create([
                    'from_status' => $order->status,
                    'to_status' => $order->status,
                    'actor_type' => $actorType,
                    'actor_id' => $by->id,
                    'shift_id' => $tillId,
                    'meta' => ['action' => 'table_tab_settled', 'table_tab_id' => $tab->id, 'payment_method' => $method],
                ]);
            }

            $tab->update([
                'settled_at' => now(),
                'settled_by' => $by->id,
                'shift_id' => $tillId,
                'payment_method' => $rounds->isEmpty() ? null : $method,
                'payment_reference' => $reference,
                'total' => (int) $rounds->sum('total'),
            ]);

            return $tab->fresh();
        });
    }

    /** Guests changed table: the whole bill moves to a free one. */
    public function move(TableTab $tab, DiningTable $to): TableTab
    {
        return DB::transaction(function () use ($tab, $to) {
            $tab = TableTab::whereKey($tab->id)->lockForUpdate()->firstOrFail();
            $to = DiningTable::whereKey($to->id)->lockForUpdate()->firstOrFail();

            if (! $tab->isOpen()) {
                throw TableTabException::alreadySettled();
            }
            if (! $to->is_active || $to->branch_id !== $tab->branch_id) {
                throw TableTabException::tableInactive();
            }
            if (TableTab::where('dining_table_id', $to->id)->whereNull('settled_at')->exists()) {
                throw TableTabException::tableTaken($to->name);
            }

            $tab->update(['dining_table_id' => $to->id]);

            return $tab->fresh('table');
        });
    }

    /**
     * Open bills holding rounds this till took: a counter's till, its own
     * rounds; a branch's shared till, every open bill at the branch.
     */
    public function openTabsBlocking(Shift $till): int
    {
        return TableTab::where('branch_id', $till->branch_id)
            ->whereNull('settled_at')
            ->whereHas('orders', fn ($q) => $q->whereNotIn('status', self::OFF_THE_BILL)
                ->when($till->terminal_id, fn ($q) => $q->where('shift_id', $till->id)))
            ->count();
    }
}

<?php

namespace App\Services\Tax;

use App\Models\Order;
use App\Models\OrderTax;
use App\Models\Refund;
use App\Services\Modules\ModuleService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Collection;

/**
 * Optional tax (Express POS step 9): VAT, NHIL, GETFund or any other levy
 * a business charges, set under Settings → Tax (the `tax` module).
 *
 * The business chooses:
 *  - inclusive: menu prices already include the tax (the tax is worked out
 *    of the price), or the tax is added on top at the till;
 *  - lines: each tax's name and rate, all on the same base (Ghana since
 *    2026: VAT 15%, NHIL 2.5%, GETFund 2.5% on the value of the sale).
 *
 * Only the food is taxed, never the delivery fee (owner, 2026-11-20);
 * order_taxes.delivery_amount stays 0.
 *
 * Every order keeps its tax as it was at the sale (order_taxes): a rate
 * changed later never touches it. Bolt Food orders (Bolt's prices, kept
 * out of sales) and freebies (nothing charged) carry no tax.
 *
 * What the business keeps is never the tax: reports count sales before
 * tax (netFood()); a till expects the food money with its tax (grossFood()).
 */
class TaxService
{
    public const SETTING = 'tax_config';

    public const MAX_LINES = 6;

    /** Not taxed: Bolt Food (Bolt's own prices, out of sales) and freebies. */
    public const UNTAXED_PAYMENT_METHODS = ['bolt_food', Order::COMPLIMENTARY];

    /** Ghana from 1 January 2026 (VAT Act 2025, Act 1151). */
    public const GHANA_PRESET = [
        ['name' => 'VAT', 'rate_basis_points' => 1500],
        ['name' => 'NHIL', 'rate_basis_points' => 250],
        ['name' => 'GETFund', 'rate_basis_points' => 250],
    ];

    /** @var array{inclusive: bool, lines: list<array{name: string, rate_basis_points: int}>}|null */
    private ?array $config = null;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ModuleService $modules,
    ) {}

    /**
     * @return array{inclusive: bool, lines: list<array{name: string, rate_basis_points: int}>}
     */
    public function config(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $stored = json_decode((string) $this->settings->get(self::SETTING), true);
        $stored = is_array($stored) ? $stored : [];

        return $this->config = [
            'inclusive' => (bool) ($stored['inclusive'] ?? true),
            'lines' => collect($stored['lines'] ?? [])
                ->filter(fn ($line) => is_array($line) && trim((string) ($line['name'] ?? '')) !== '' && (int) ($line['rate_basis_points'] ?? 0) > 0)
                ->map(fn (array $line) => ['name' => trim((string) $line['name']), 'rate_basis_points' => (int) $line['rate_basis_points']])
                ->take(self::MAX_LINES)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array{name: string, rate_basis_points: int}>  $lines
     */
    public function save(bool $inclusive, array $lines): void
    {
        $this->settings->set(self::SETTING, json_encode([
            'inclusive' => $inclusive,
            'lines' => array_values($lines),
        ]));
        $this->config = null;
    }

    /** The Tax module is on and at least one tax is set up. */
    public function charging(): bool
    {
        return $this->modules->enabled(ModuleService::TAX) && $this->config()['lines'] !== [];
    }

    public function appliesTo(?string $paymentMethod): bool
    {
        return $this->charging() && ! in_array($paymentMethod, self::UNTAXED_PAYMENT_METHODS, true);
    }

    /** The tax rates together, e.g. 2000 for 20%. */
    public function combinedRate(): int
    {
        return (int) collect($this->config()['lines'])->sum('rate_basis_points');
    }

    /**
     * The tax on a new order's food after discounts. `added` is what goes
     * on top of the total (0 when the prices include the tax).
     *
     * @return array{lines: list<array{name: string, rate_basis_points: int, inclusive: bool, food_amount: int, delivery_amount: int}>, tax_total: int, added: int}
     */
    public function quote(int $food, ?string $paymentMethod): array
    {
        if (! $this->appliesTo($paymentMethod)) {
            return ['lines' => [], 'tax_total' => 0, 'added' => 0];
        }

        $config = $this->config();
        $rates = array_column($config['lines'], 'rate_basis_points');
        $foodTax = $this->split(max(0, $food), $rates, $config['inclusive']);

        $lines = [];
        foreach ($config['lines'] as $i => $line) {
            $lines[] = [
                'name' => $line['name'],
                'rate_basis_points' => $line['rate_basis_points'],
                'inclusive' => $config['inclusive'],
                'food_amount' => $foodTax[$i],
                'delivery_amount' => 0,
            ];
        }

        $total = array_sum($foodTax);

        return ['lines' => $lines, 'tax_total' => $total, 'added' => $config['inclusive'] ? 0 : $total];
    }

    /**
     * Each tax line's share of $amount. Added on top: the amount times
     * each rate. Included in the price: the tax inside the amount at the
     * combined rate, shared between the lines by rate (the last line takes
     * the rounding).
     *
     * @param  list<int>  $rates  basis points
     * @return list<int>
     */
    public function split(int $amount, array $rates, bool $inclusive): array
    {
        if ($amount <= 0 || $rates === []) {
            return array_fill(0, count($rates), 0);
        }

        if (! $inclusive) {
            return array_map(fn (int $rate) => (int) round($amount * $rate / 10000), $rates);
        }

        $combined = array_sum($rates);
        $total = (int) round($amount * $combined / (10000 + $combined));
        $shares = [];
        foreach ($rates as $i => $rate) {
            $shares[] = $i === array_key_last($rates)
                ? $total - array_sum($shares)
                : (int) round($total * $rate / $combined);
        }

        return $shares;
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, tax_total: int, added: int}  $quote
     */
    public function record(Order $order, array $quote): void
    {
        foreach ($quote['lines'] as $line) {
            $order->taxes()->create($line);
        }
    }

    /**
     * Works the order's total out again after its delivery fee changed
     * (adjusted, set on arrival, branch transfer): the fee isn't taxed, and
     * the tax on the food stays as it was at the sale. Doesn't save the
     * order.
     */
    public function retotal(Order $order): void
    {
        $added = (int) $order->tax_total > 0
            ? (int) $order->taxes()->where('inclusive', false)->sum('food_amount')
            : 0;

        $order->total = (int) $order->subtotal - (int) $order->discount_total + (int) $order->delivery_fee + $added;
    }

    /**
     * The tax share of a refund: the refunded part of what the customer
     * paid, times the order's tax over its total. Needs the refund's order
     * with tax_total and total. 0 for an order without tax.
     */
    public function refundTax(Refund $refund): int
    {
        $order = $refund->order;

        if (! $order || (int) $order->tax_total === 0 || (int) $order->total <= 0) {
            return 0;
        }

        return (int) round(min((int) $refund->amount, (int) $order->total) * (int) $order->tax_total / (int) $order->total);
    }

    /**
     * Each order's tax, split for reports — one query, and none at all
     * when no order in the list carries tax.
     *
     * @param  iterable<Order>  $orders
     * @return array<int, array{food: int, food_added: int, delivery: int, delivery_added: int}>
     */
    public function breakdown(iterable $orders): array
    {
        $ids = collect($orders)->filter(fn (Order $order) => (int) $order->tax_total > 0)->pluck('id')->unique()->values();
        $result = [];

        foreach ($ids->chunk(1000) as $chunk) {
            OrderTax::whereIn('order_id', $chunk->all())
                ->selectRaw('order_id, sum(food_amount) as food, sum(case when inclusive then 0 else food_amount end) as food_added, sum(delivery_amount) as delivery, sum(case when inclusive then 0 else delivery_amount end) as delivery_added')
                ->groupBy('order_id')
                ->get()
                ->each(function ($row) use (&$result) {
                    $result[(int) $row->order_id] = [
                        'food' => (int) $row->food,
                        'food_added' => (int) $row->food_added,
                        'delivery' => (int) $row->delivery,
                        'delivery_added' => (int) $row->delivery_added,
                    ];
                });
        }

        return $result;
    }

    /**
     * @param  array<int, array{food: int, food_added: int, delivery: int, delivery_added: int}>  $breakdown
     * @return array{food: int, food_added: int, delivery: int, delivery_added: int}
     */
    public function of(Order $order, array $breakdown): array
    {
        return $breakdown[$order->id] ?? ['food' => 0, 'food_added' => 0, 'delivery' => 0, 'delivery_added' => 0];
    }

    /**
     * Sales before tax: the food after discounts, less any tax inside the
     * prices.
     *
     * @param  array<int, array{food: int, food_added: int, delivery: int, delivery_added: int}>  $breakdown
     */
    public function netFood(Order $order, array $breakdown): int
    {
        $tax = $this->of($order, $breakdown);

        return (int) $order->subtotal - (int) $order->discount_total - ($tax['food'] - $tax['food_added']);
    }

    /**
     * What the customer paid for the food, tax included — what a till
     * expects.
     *
     * @param  array<int, array{food: int, food_added: int, delivery: int, delivery_added: int}>  $breakdown
     */
    public function grossFood(Order $order, array $breakdown): int
    {
        return (int) $order->subtotal - (int) $order->discount_total + $this->of($order, $breakdown)['food_added'];
    }

    /**
     * The settings for the POS and checkout pages' running totals
     * (partials/tax-script mirrors split()), or null when no tax is
     * charged.
     *
     * @return array{inclusive: bool, untaxed: list<string>, lines: list<array{label: string, rate_basis_points: int}>}|null
     */
    public function clientConfig(): ?array
    {
        if (! $this->charging()) {
            return null;
        }

        $config = $this->config();

        return [
            'inclusive' => $config['inclusive'],
            'untaxed' => self::UNTAXED_PAYMENT_METHODS,
            'lines' => array_map(fn (array $line) => ['label' => self::label($line['name'], $line['rate_basis_points']), 'rate_basis_points' => $line['rate_basis_points']], $config['lines']),
        ];
    }

    /** "VAT 15%", "NHIL 2.5%". */
    public static function label(string $name, int $basisPoints): string
    {
        return $name.' '.rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.').'%';
    }

    /**
     * @return Collection<int, OrderTax>
     */
    public function linesFor(Order $order): Collection
    {
        return (int) $order->tax_total > 0 ? $order->taxes()->orderBy('id')->get() : collect();
    }
}

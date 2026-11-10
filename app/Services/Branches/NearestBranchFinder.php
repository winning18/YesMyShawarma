<?php

namespace App\Services\Branches;

use App\Models\Branch;
use App\Services\Delivery\DeliveryFeeCalculator;

/**
 * "Find my nearest branch" — the nearest branch that can take an order
 * right now (open per its working hours AND accepting orders), owner
 * decision 2026-11-10. When every branch is closed it falls back to the
 * nearest one still accepting orders (an order placed while closed just
 * waits for staff, see OrderCreationService), then to the nearest active
 * one at all.
 *
 * Straight-line distance, the same measure delivery fees are priced on.
 * The coordinates are only compared here, never stored.
 */
class NearestBranchFinder
{
    /**
     * Checkout only suggests moving the order when another open branch is
     * at least this much closer to the delivery pin (owner, 2026-11-10) —
     * any less and the suggestion is more nagging than help.
     */
    public const SUGGEST_SWITCH_KM = 2;

    public function __construct(
        private readonly WorkingHoursService $workingHours,
        private readonly DeliveryFeeCalculator $distances,
    ) {}

    /**
     * closer_closed is the actual nearest branch, when it was skipped for
     * being closed — so the customer can be told why they got another.
     *
     * @return array{branch: Branch, distance_km: float, closer_closed: ?Branch}|null
     */
    public function find(float $lat, float $lng): ?array
    {
        $byDistance = Branch::where('is_active', true)->get()
            ->map(fn (Branch $branch) => ['branch' => $branch, 'distance_km' => $this->distances->distanceKm($branch, $lat, $lng)])
            ->sortBy('distance_km')
            ->values();

        if ($byDistance->isEmpty()) {
            return null;
        }

        $pick = $byDistance->first(fn (array $row) => $row['branch']->is_accepting_orders && $this->workingHours->isOpenNow($row['branch']))
            ?? $byDistance->first(fn (array $row) => $row['branch']->is_accepting_orders)
            ?? $byDistance->first();

        $nearest = $byDistance->first()['branch'];

        return [
            'branch' => $pick['branch'],
            'distance_km' => round($pick['distance_km'], 1),
            'closer_closed' => $nearest->is($pick['branch']) ? null : $nearest,
        ];
    }

    public function distanceKm(Branch $branch, float $lat, float $lng): float
    {
        return round($this->distances->distanceKm($branch, $lat, $lng), 1);
    }
}

<?php

namespace App\Services\Delivery;

use App\Models\Branch;

/**
 * Distance-based delivery pricing — replaces the old per-zone flat fee.
 * The rate and minimum are plain constants for now rather than admin-
 * configurable rows, since the business is still iterating on the model
 * itself (see conversation history: "we'll later make necessary changes
 * as the need arises"). Bump these here when the real numbers change.
 */
class DeliveryFeeCalculator
{
    public const RATE_PER_KM_PESEWAS = 500;

    public const MINIMUM_ORDER_TOTAL_PESEWAS = 2000;

    /**
     * Straight-line (haversine) distance from the branch to the given
     * point, in kilometres, times the flat per-km rate. This is
     * deliberately not the rider's actual travelled path — there's no
     * live rider location tracking in this app (out of scope for v1) —
     * it's the same displacement measure the old zone-radius matching
     * used, just priced continuously instead of bucketed into zones.
     */
    public function calculate(Branch $branch, float $lat, float $lng): int
    {
        $distanceKm = $this->distanceMetres((float) $branch->lat, (float) $branch->lng, $lat, $lng) / 1000;

        return (int) round($distanceKm * self::RATE_PER_KM_PESEWAS);
    }

    private function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMetres = 6371000;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        return $earthRadiusMetres * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

<?php

namespace App\Services\Delivery;

use App\Models\Branch;
use App\Models\DeliveryZone;

class DeliveryZoneMatcher
{
    /**
     * Radius-based zone matching within a single, already-known branch (per
     * schema.md: "Start radius-based. Upgrade to polygons only if radii
     * prove too blunt in practice."). If a point falls inside more than one
     * of the branch's zones, the nearest centre wins.
     *
     * This does not choose *which branch* serves a customer — schema.md's
     * "match the customer's coordinates to a zone, take the branch that
     * owns it" cross-branch routing is a separate, not-yet-built concern.
     * Here the branch is already fixed; this only prices delivery to it.
     */
    public function matchForBranch(Branch $branch, float $lat, float $lng): ?DeliveryZone
    {
        return $branch->deliveryZones()
            ->where('is_active', true)
            ->get()
            ->map(fn (DeliveryZone $zone) => [
                'zone' => $zone,
                'distance' => $this->distanceMetres((float) $zone->centre_lat, (float) $zone->centre_lng, $lat, $lng),
            ])
            ->filter(fn (array $candidate) => $candidate['distance'] <= $candidate['zone']->radius_metres)
            ->sortBy('distance')
            ->first()['zone'] ?? null;
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

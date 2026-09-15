<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Services\Delivery\DeliveryFeeCalculator;
use Tests\TestCase;

class DeliveryFeeCalculatorTest extends TestCase
{
    /**
     * Same haversine formula the calculator itself uses, kept independent
     * here so this test still catches a broken rounding/minimum step even
     * if the distance math is ever touched — the two live orders.md
     * requirements aren't "distance is correct" (unchanged, already
     * trusted) but "always a whole cedi" and "never below GHS 10".
     */
    private function rawFeePesewas(Branch $branch, float $lat, float $lng): float
    {
        $earthRadiusMetres = 6371000;
        $dLat = deg2rad($lat - (float) $branch->lat);
        $dLng = deg2rad($lng - (float) $branch->lng);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad((float) $branch->lat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;
        $distanceKm = ($earthRadiusMetres * 2 * atan2(sqrt($a), sqrt(1 - $a))) / 1000;

        return $distanceKm * DeliveryFeeCalculator::RATE_PER_KM_PESEWAS;
    }

    public function test_fee_rounds_to_the_nearest_whole_cedi(): void
    {
        $branch = new Branch(['lat' => 5.5560, 'lng' => -0.1969]);
        $lat = 5.6037;
        $lng = -0.1870;

        $raw = $this->rawFeePesewas($branch, $lat, $lng);
        $this->assertGreaterThan(
            DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $raw,
            'fixture must price above the minimum so rounding is tested in isolation from the floor',
        );

        $expected = (int) round($raw / 100) * 100;

        $fee = (new DeliveryFeeCalculator)->calculate($branch, $lat, $lng);

        $this->assertSame($expected, $fee);
        $this->assertSame(0, $fee % 100, 'a delivery fee must always be a whole cedi amount, never with change');
    }

    public function test_fee_never_goes_below_ten_cedis(): void
    {
        $branch = new Branch(['lat' => 5.5560, 'lng' => -0.1969]);

        // Same point as the branch — the raw, unrounded fee is 0.
        $fee = (new DeliveryFeeCalculator)->calculate($branch, 5.5560, -0.1969);

        $this->assertSame(DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $fee);
        $this->assertSame(1000, $fee, 'GHS 10 minimum, expressed in pesewas');
    }

    public function test_a_fee_that_rounds_below_ten_cedis_is_floored_to_ten(): void
    {
        $branch = new Branch(['lat' => 5.5560, 'lng' => -0.1969]);
        // Close enough that the raw fee is a few cedis — well under the
        // GHS 10 floor even before rounding.
        $lat = 5.5595;
        $lng = -0.1969;

        $raw = $this->rawFeePesewas($branch, $lat, $lng);
        $this->assertLessThan(DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $raw);

        $fee = (new DeliveryFeeCalculator)->calculate($branch, $lat, $lng);

        $this->assertSame(DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $fee);
    }
}

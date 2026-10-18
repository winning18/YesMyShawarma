<?php

namespace App\Services\Seo;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Services\Branches\WorkingHoursService;
use App\Services\Customers\CustomerService;

/**
 * Restaurant structured data (schema.org) for a branch — lets Google show
 * address, phone, hours and the menu link directly in search results and
 * Maps. Real data throughout: address/phone/geo from the branch's own
 * row, hours from branch_working_hours (WorkingHoursService — the
 * admin-set weekly schedule, never the flat opens_at/closes_at columns),
 * price range from the live menu. A branch with no configured schedule
 * simply omits openingHoursSpecification rather than guessing.
 *
 * Shared by the home page (every active branch) and each branch's own
 * page, so the two can never describe a branch differently.
 */
class RestaurantSchemaBuilder
{
    /** ISO weekday (WorkingHoursService's own 1=Mon..7=Sun) to schema.org's plain day name. */
    private const ISO_DAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    private ?string $priceRange = null;

    public function __construct(
        private readonly WorkingHoursService $workingHours,
        private readonly CustomerService $customers,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forActiveBranches(): array
    {
        return Branch::where('is_active', true)->orderBy('id')->get()
            ->map(fn (Branch $branch) => $this->forBranch($branch))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forBranch(Branch $branch): array
    {
        $hours = $this->workingHours->forBranch($branch->id)
            ->filter(fn (array $day) => $day['opens_at'] && $day['closes_at'])
            ->map(fn (array $day) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => self::ISO_DAYS[$day['day_of_week']],
                'opens' => substr($day['opens_at'], 0, 5),
                'closes' => substr($day['closes_at'], 0, 5),
            ])
            ->values();

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => config('app.name').': '.$branch->name,
            // Stored phones aren't always E.164 (an admin typed one as
            // "026-370-2929") — Google expects the international form.
            'telephone' => $this->customers->normalizeGhanaPhone($branch->phone),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $branch->address,
                'addressLocality' => 'Accra',
                'addressRegion' => 'Greater Accra',
                'postalCode' => $branch->ghanapost_code,
                'addressCountry' => 'GH',
            ]),
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $branch->lat,
                'longitude' => (float) $branch->lng,
            ],
            'servesCuisine' => ['Shawarma', 'Burgers', 'Fast food'],
            'hasMenu' => route('menu.index'),
            'acceptsReservations' => false,
            'priceRange' => $this->priceRange(),
            'url' => route('branches.show', $branch),
        ];

        if ($branch->imageUrl()) {
            $schema['image'] = $branch->imageUrl();
        }

        if ($hours->isNotEmpty()) {
            $schema['openingHoursSpecification'] = $hours->all();
        }

        return $schema;
    }

    /**
     * "GH₵22–GH₵95" from the cheapest and dearest active item, rounded to
     * whole cedis — computed once per request.
     */
    private function priceRange(): string
    {
        if ($this->priceRange !== null) {
            return $this->priceRange;
        }

        $min = MenuItem::where('is_active', true)->min('base_price');
        $max = MenuItem::where('is_active', true)->max('base_price');

        return $this->priceRange = ($min === null)
            ? 'GH₵'
            : 'GH₵'.(int) floor($min / 100).'–GH₵'.(int) ceil($max / 100);
    }
}

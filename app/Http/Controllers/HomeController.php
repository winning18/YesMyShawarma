<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Branches\WorkingHoursService;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Home page menu-item marquees, in display order. Direction alternates
     * per row on purpose (a visual zig-zag), and hot-dogs/loaded-fries are
     * deliberately merged into one row rather than two.
     *
     * @var list<array{title: string, slugs: list<string>, direction: string}>
     */
    private const MENU_SLIDERS = [
        ['title' => 'Shawarma favourites', 'slugs' => ['shawarma'], 'direction' => 'right'],
        ['title' => 'Burgers', 'slugs' => ['burgers'], 'direction' => 'left'],
        ['title' => 'Hot Dogs & Loaded Fries', 'slugs' => ['hot-dogs', 'loaded-fries'], 'direction' => 'right'],
        ['title' => 'Sandwiches', 'slugs' => ['sandwiches'], 'direction' => 'left'],
        ['title' => 'Drinks', 'slugs' => ['drinks'], 'direction' => 'right'],
    ];

    /** ISO weekday (WorkingHoursService's own 1=Mon..7=Sun) to schema.org's plain day name. */
    private const ISO_DAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    public function index(CustomerBranchSelection $selection, WorkingHoursService $workingHours): View
    {
        // Every category is hero-slide-eligible (Hero Slider dashboard
        // page), but only ones staff actually gave a photo appear on the
        // live carousel — otherwise a freshly created category would
        // immediately show an empty slide before anyone got to it.
        $heroSlides = Category::where('is_active', true)
            ->whereNotNull('hero_image_path')
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'name' => $category->name,
                'tagline' => $category->tagline ?? '',
                'imageUrl' => $category->heroImageUrl(),
            ])
            ->values();

        return view('home', [
            'heroSlides' => $heroSlides,
            'menuSliders' => $this->menuSliders($selection->current()),
            'restaurantSchema' => $this->restaurantSchema($workingHours),
        ]);
    }

    /**
     * Restaurant structured data (schema.org) for every active branch —
     * lets Google show address/phone/hours directly in search results and
     * Maps. Real data throughout: address/phone/geo from the branch's own
     * row, hours from branch_working_hours (WorkingHoursService — the
     * admin-set weekly schedule, same source branch-card.blade.php's
     * "Hours today" reads, never the flat opens_at/closes_at columns).
     * A branch with no configured schedule yet simply omits
     * openingHoursSpecification rather than guessing.
     *
     * @return list<array<string, mixed>>
     */
    private function restaurantSchema(WorkingHoursService $workingHours): array
    {
        return Branch::where('is_active', true)->get()
            ->map(function (Branch $branch) use ($workingHours) {
                $hours = $workingHours->forBranch($branch->id)
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
                    'telephone' => $branch->phone,
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => $branch->address,
                        'addressLocality' => 'Accra',
                        'addressCountry' => 'GH',
                    ],
                    'geo' => [
                        '@type' => 'GeoCoordinates',
                        'latitude' => (float) $branch->lat,
                        'longitude' => (float) $branch->lng,
                    ],
                    'priceRange' => 'GH₵',
                    'url' => route('branches.index'),
                ];

                if ($branch->imageUrl()) {
                    $schema['image'] = $branch->imageUrl();
                }

                if ($hours->isNotEmpty()) {
                    $schema['openingHoursSpecification'] = $hours->all();
                }

                return $schema;
            })
            ->values()
            ->all();
    }

    private function menuSliders(?Branch $branch): Collection
    {
        return collect(self::MENU_SLIDERS)
            ->map(fn (array $slider) => [
                'title' => $slider['title'],
                'direction' => $slider['direction'],
                // First slug only — enough for the "Show all" link's
                // ?category= filter on the menu page. A slider spanning
                // several categories (e.g. Hot Dogs & Loaded Fries) just
                // lands on the first of the group, which sits right at the
                // start of that section since they're ordered adjacently.
                'categorySlug' => $slider['slugs'][0],
                'items' => $this->itemsForSlugs($slider['slugs'], $branch),
            ])
            ->filter(fn (array $slider) => $slider['items']->isNotEmpty())
            ->values();
    }

    /**
     * Items stay on the marquee even when sold out — see
     * home.blade.php's gray-out treatment — only $item->is_active (a
     * permanent removal, not a stock state) drops an item entirely.
     * Availability is branch_menu_item's pivot (schema.md), so it can
     * only be known once a branch is selected (MenuController's own
     * resolveBranch pattern); with none selected yet every active item is
     * treated as available, matching menu.show's own redirect-to-branch
     * behaviour for that case.
     */
    private function itemsForSlugs(array $slugs, ?Branch $branch): Collection
    {
        $categoryIds = Category::whereIn('slug', $slugs)->pluck('id');

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        $items = MenuItem::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $availableIds = $branch
            ? $branch->menuItems()
                ->whereIn('menu_items.id', $items->pluck('id'))
                ->wherePivot('is_available', true)
                ->pluck('menu_items.id')
            : $items->pluck('id');

        // map(), not each() — each() stops iterating the instant a
        // callback returns exactly false, and $item->isAvailable = false
        // (the sold-out case) IS exactly false, so a single sold-out item
        // used to silently cut the loop short and leave every item after
        // it with isAvailable unset — read as falsy by Blade, graying out
        // the rest of the category too.
        return $items->map(function (MenuItem $item) use ($availableIds) {
            $item->isAvailable = $availableIds->contains($item->id);

            return $item;
        });
    }
}

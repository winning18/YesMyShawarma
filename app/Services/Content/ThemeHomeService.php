<?php

namespace App\Services\Content;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Review;
use App\Services\Branches\BranchStatusService;
use App\Services\Branches\WorkingHoursService;
use App\Services\Modules\ModuleService;
use App\Services\Settings\BusinessProfile;
use Illuminate\Support\Collection;

/**
 * What the Photo-led and One-page website themes' home pages show beyond
 * the Classic home page's own data (WebsiteThemeService). Everything comes
 * from the business's own menu, branches, settings and reviews; nothing
 * here is written for one business.
 */
class ThemeHomeService
{
    public function __construct(
        private readonly BusinessProfile $profile,
        private readonly SitePageService $pages,
        private readonly WorkingHoursService $workingHours,
        private readonly BranchStatusService $branchStatus,
        private readonly ModuleService $modules,
    ) {}

    /**
     * The big photo at the top: the first Hero Slider photo, else the
     * first dish with a photo, else none (the theme draws a colour block).
     *
     * @param  Collection<int, array{name: string, tagline: string, imageUrl: ?string}>  $heroSlides
     * @return array{imageUrl: ?string, headline: string, eyebrow: string}
     */
    public function hero(Collection $heroSlides): array
    {
        $slide = $heroSlides->first();

        return [
            'imageUrl' => $slide['imageUrl'] ?? $this->photographedItems(1)->first()?->imageUrl(),
            'headline' => ($slide['tagline'] ?? '') ?: ($this->profile->tagline() ?: __('Good food, made fresh and ready when you are.')),
            'eyebrow' => $this->profile->name(),
        ];
    }

    /**
     * The quick-facts strip under the hero: branches, today's hours,
     * delivery or pickup, and the year the business started (when known).
     *
     * @return list<array{value: string, label: string}>
     */
    public function quickFacts(?Branch $branch): array
    {
        $facts = [];
        $count = Branch::where('is_active', true)->count();

        if ($count > 1) {
            $facts[] = ['value' => (string) $count, 'label' => __('Branches')];
        } elseif ($branch) {
            $facts[] = ['value' => $branch->name, 'label' => __('Find us')];
        }

        if ($branch) {
            $today = $this->workingHours->todayLabel($branch);
            $facts[] = $today
                ? ['value' => $today, 'label' => $count > 1 ? __('Open today at :branch', ['branch' => $branch->name]) : __('Open today')]
                : ['value' => $this->workingHours->isOpenNow($branch) ? __('Open now') : __('Closed today'), 'label' => $branch->name];
        }

        $facts[] = $this->modules->enabled(ModuleService::DELIVERY)
            ? ['value' => __('Delivery'), 'label' => __('or pickup')]
            : ['value' => __('Pickup'), 'label' => __('Order ahead, collect hot')];

        if ($year = $this->pages->about()['founded_year']) {
            $facts[] = ['value' => (string) $year, 'label' => __('Serving since')];
        }

        return $facts;
    }

    /**
     * Photo-led's "Explore the menu" tiles: each active category with its
     * Hero Slider photo, else its first dish photo.
     *
     * @return Collection<int, array{name: string, slug: string, imageUrl: ?string}>
     */
    public function categoryTiles(): Collection
    {
        return Category::where('is_active', true)
            ->whereHas('menuItems', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Category $category) => [
                'name' => $category->name,
                'slug' => $category->slug,
                'imageUrl' => $category->heroImageUrl() ?? MenuItem::where('category_id', $category->id)
                    ->where('is_active', true)->whereNotNull('image_path')->orderBy('sort_order')->first()?->imageUrl(),
            ])
            ->values();
    }

    /**
     * Photo-led's featured dishes: this month's most ordered when there
     * are any, else the first dishes with photos.
     *
     * @param  Collection<int, MenuItem>  $popularItems
     * @return Collection<int, MenuItem>
     */
    public function featured(Collection $popularItems): Collection
    {
        $available = $popularItems->filter(fn (MenuItem $item) => $item->isAvailable ?? true);

        return ($available->isNotEmpty() ? $available : $this->photographedItems(6))->take(6)->values();
    }

    /**
     * Photo-led's gallery: up to six dish photos.
     *
     * @return Collection<int, MenuItem>
     */
    public function gallery(): Collection
    {
        return $this->photographedItems(6);
    }

    /**
     * The latest approved reviews with something written, when the
     * Reviews module is on.
     *
     * @return Collection<int, Review>
     */
    public function reviews(int $limit = 3): Collection
    {
        if (! $this->modules->enabled(ModuleService::REVIEWS)) {
            return collect();
        }

        return Review::with('branch')
            ->where('status', Review::STATUS_APPROVED)
            ->whereNotNull('comment')->where('comment', '!=', '')
            ->latest('moderated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * One-page's branches section: every active branch with its card data.
     *
     * @return Collection<int, Branch>
     */
    public function branches(): Collection
    {
        return Branch::where('is_active', true)->orderBy('id')->get()
            ->each(fn (Branch $branch) => $this->branchStatus->withStatus($branch));
    }

    /**
     * One-page's about section: what Website content → About us says, when
     * it's published.
     *
     * @return array{heading: ?string, mission: ?string, story: mixed}|null
     */
    public function about(): ?array
    {
        if (! $this->pages->isPublished('about')) {
            return null;
        }

        return [
            'heading' => $this->pages->heading('about'),
            'mission' => $this->pages->about()['mission'],
            'story' => $this->pages->body('about'),
        ];
    }

    /**
     * One-page's contact section.
     *
     * @return array{phones: list<array{name: string, tel: string, display: string}>, emails: list<string>}
     */
    public function contact(): array
    {
        return [
            'phones' => $this->profile->branchPhones(),
            'emails' => $this->profile->publicEmails(),
        ];
    }

    /** @return Collection<int, MenuItem> */
    private function photographedItems(int $limit): Collection
    {
        return MenuItem::where('is_active', true)->whereNotNull('image_path')
            ->orderBy('category_id')->orderBy('sort_order')
            ->limit($limit)->get();
    }
}

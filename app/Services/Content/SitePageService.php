<?php

namespace App\Services\Content;

use App\Models\FaqItem;
use App\Models\SitePage;
use App\Services\Modules\ModuleService;
use App\Services\Settings\BusinessProfile;
use App\Services\Settings\SettingsService;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Attributes\AttributesExtension;

/**
 * The customer website's own words (Express POS step 2c): each page's
 * heading, search title, search description and text, edited under
 * Settings → Website content (SitePageManagementController). Text is
 * Markdown.
 *
 * A new install starts with plain search titles built from the business
 * name, and no About, FAQ or policy pages — a page with nothing written
 * isn't linked anywhere and answers 404 until someone writes it. Yes My
 * Shawarma's content was copied in by the 2026_11_15_120000 migration.
 *
 * Every page can use :name (the business name) and :city; PAGES lists the
 * extra placeholders each one takes.
 */
class SitePageService
{
    /**
     * label: shown in the editor. placeholders: extra ones this page fills
     * in. heading/body/meta: which fields the editor shows.
     *
     * @var array<string, array{label: string, placeholders: list<string>, heading: bool, body: bool, meta: bool}>
     */
    public const PAGES = [
        'home' => ['label' => 'Home page', 'placeholders' => [':branches'], 'heading' => false, 'body' => false, 'meta' => true],
        'menu' => ['label' => 'Menu', 'placeholders' => [], 'heading' => false, 'body' => false, 'meta' => true],
        'menu-item' => ['label' => 'Each menu item', 'placeholders' => [':item'], 'heading' => false, 'body' => false, 'meta' => true],
        'branches' => ['label' => 'Branches', 'placeholders' => [':branches'], 'heading' => false, 'body' => false, 'meta' => true],
        'branch' => ['label' => 'Each branch page', 'placeholders' => [':branch', ':area', ':address', ':phone'], 'heading' => true, 'body' => true, 'meta' => true],
        'contact' => ['label' => 'Contact us', 'placeholders' => [], 'heading' => false, 'body' => false, 'meta' => true],
        'reviews' => ['label' => 'Reviews', 'placeholders' => [], 'heading' => false, 'body' => false, 'meta' => true],
        'faq' => ['label' => 'FAQ', 'placeholders' => [], 'heading' => true, 'body' => false, 'meta' => true],
        'about' => ['label' => 'About us', 'placeholders' => [], 'heading' => true, 'body' => true, 'meta' => true],
        'terms' => ['label' => 'Terms & Conditions', 'placeholders' => [], 'heading' => true, 'body' => true, 'meta' => true],
        'refunds' => ['label' => 'Refund Policy', 'placeholders' => [], 'heading' => true, 'body' => true, 'meta' => true],
        'privacy' => ['label' => 'Privacy Policy', 'placeholders' => [], 'heading' => true, 'body' => true, 'meta' => true],
        'cookies' => ['label' => 'Cookie Policy', 'placeholders' => [], 'heading' => true, 'body' => true, 'meta' => true],
        'default' => ['label' => 'Every other page', 'placeholders' => [], 'heading' => false, 'body' => false, 'meta' => true],
        'llms' => ['label' => 'Summary for AI assistants (llms.txt)', 'placeholders' => [':url'], 'heading' => false, 'body' => true, 'meta' => false],
    ];

    /**
     * What a page says before anyone writes its own: [heading, search
     * title, search description]. A null description falls back to the
     * "Every other page" one.
     *
     * @var array<string, array{0: ?string, 1: ?string, 2: ?string}>
     */
    private const DEFAULTS = [
        'home' => [null, ':name: Order Online', null],
        'menu' => [null, 'Menu · :name', null],
        'menu-item' => [null, ':item · :name', null],
        'branches' => [null, 'Our Branches · :name', null],
        'branch' => [null, ':name :branch', null],
        'contact' => [null, 'Contact us · :name', null],
        'reviews' => [null, 'Reviews · :name', null],
        'faq' => ['Frequently asked questions', 'FAQ · :name', null],
        'about' => ['About us', 'About us · :name', null],
        'terms' => ['Terms & Conditions', 'Terms & Conditions · :name', null],
        'refunds' => ['Refund Policy', 'Refund Policy · :name', null],
        'privacy' => ['Privacy Policy', 'Privacy Policy · :name', null],
        'cookies' => ['Cookie Policy', 'Cookie Policy · :name', null],
        'default' => [null, null, null],
        'llms' => [null, null, null],
    ];

    /** The pages that only exist once something is written on them. */
    public const OPTIONAL_PAGES = ['about', 'faq', 'terms', 'refunds', 'privacy', 'cookies'];

    public const ABOUT_MISSION = 'about_mission';

    public const ABOUT_VISION = 'about_vision';

    public const ABOUT_FOUNDED_YEAR = 'about_founded_year';

    public const ABOUT_CUSTOMERS_LABEL = 'about_customers_label';

    /** @var array<string, SitePage>|null */
    private ?array $pages = null;

    public function __construct(
        private readonly BusinessProfile $profile,
        private readonly SettingsService $settings,
    ) {}

    /**
     * The stored row, or an empty unsaved one.
     */
    public function page(string $slug): SitePage
    {
        $this->pages ??= SitePage::all()->keyBy('slug')->all();

        return $this->pages[$slug] ?? new SitePage(['slug' => $slug]);
    }

    public function heading(string $slug, array $replace = []): ?string
    {
        $heading = $this->page($slug)->title ?: self::DEFAULTS[$slug][0];

        return $heading === null ? null : $this->fill($heading, $replace);
    }

    /**
     * The page's search/share title and description, placeholders filled.
     *
     * @param  array<string, string>  $replace  ':placeholder' => value
     * @return array{title: string, description: string}
     */
    public function meta(string $slug, array $replace = []): array
    {
        $page = $this->page($slug);

        return [
            'title' => $this->fill($page->meta_title ?: (self::DEFAULTS[$slug][1] ?? ':name'), $replace),
            'description' => $this->fill($page->meta_description ?: $this->defaultDescription(), $replace),
        ];
    }

    /**
     * The page's text as HTML, or null when nothing is written.
     */
    public function body(string $slug, array $replace = []): ?HtmlString
    {
        $body = trim((string) $this->page($slug)->body);

        if ($body === '') {
            return null;
        }

        // Values typed elsewhere (a branch address) go in as plain text.
        $replace = array_map(fn (string $value) => preg_replace('/([\\\\`*_\[\]<>#])/', '\\\\$1', $value), $replace);

        return new HtmlString(Str::markdown($this->fill($body, $replace), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ], [new AttributesExtension]));
    }

    /**
     * Whether an optional page (About, FAQ, the policies) has anything on
     * it — the footer, sitemap and llms.txt only link to those that do.
     */
    public function isPublished(string $slug): bool
    {
        return match ($slug) {
            'faq' => FaqItem::exists(),
            'about' => trim((string) $this->page('about')->body) !== '' || $this->about()['mission'] || $this->about()['vision'],
            default => trim((string) $this->page($slug)->body) !== '',
        };
    }

    /**
     * @return array<string, bool> slug => published, for OPTIONAL_PAGES
     */
    public function published(): array
    {
        return collect(self::OPTIONAL_PAGES)->mapWithKeys(fn (string $slug) => [$slug => $this->isPublished($slug)])->all();
    }

    /**
     * @return array{mission: ?string, vision: ?string, founded_year: ?int, customers_label: ?string}
     */
    public function about(): array
    {
        $year = (int) $this->settings->get(self::ABOUT_FOUNDED_YEAR);

        return [
            'mission' => $this->settings->get(self::ABOUT_MISSION) ?: null,
            'vision' => $this->settings->get(self::ABOUT_VISION) ?: null,
            'founded_year' => $year ?: null,
            'customers_label' => $this->settings->get(self::ABOUT_CUSTOMERS_LABEL) ?: null,
        ];
    }

    /**
     * @param  array{title?: ?string, meta_title?: ?string, meta_description?: ?string, body?: ?string}  $values
     */
    public function update(string $slug, array $values): SitePage
    {
        $fields = collect(['title', 'meta_title', 'meta_description', 'body'])
            ->mapWithKeys(fn (string $field) => [$field => ($value = trim((string) ($values[$field] ?? ''))) === '' ? null : $value])
            ->all();

        $page = SitePage::updateOrCreate(['slug' => $slug], $fields);
        $this->pages = null;

        return $page;
    }

    /**
     * @param  array{mission?: ?string, vision?: ?string, founded_year?: int|string|null, customers_label?: ?string}  $values
     */
    public function updateAbout(array $values): void
    {
        foreach ([
            self::ABOUT_MISSION => 'mission',
            self::ABOUT_VISION => 'vision',
            self::ABOUT_FOUNDED_YEAR => 'founded_year',
            self::ABOUT_CUSTOMERS_LABEL => 'customers_label',
        ] as $key => $field) {
            $value = trim((string) ($values[$field] ?? ''));
            $this->settings->set($key, $value === '' ? null : $value);
        }
    }

    /**
     * The plain-text summary for AI assistants at /llms.txt: what the
     * business wrote, or one built from its profile and live pages.
     */
    public function llmsText(): string
    {
        $written = trim((string) $this->page('llms')->body);

        if ($written !== '') {
            return $this->fill($written, [':url' => rtrim(url('/'), '/')])."\n";
        }

        $published = $this->published();
        $link = fn (string $label, string $route, string $about = '') => '- ['.$label.']('.route($route).')'.($about !== '' ? ': '.$about : '');

        $lines = ['# '.$this->profile->name(), ''];

        if ($summary = $this->profile->tagline() ?: $this->defaultDescription()) {
            $lines = [...$lines, '> '.$this->fill($summary), ''];
        }

        $lines = [...$lines, '## Ordering', '',
            $link('Menu', 'menu.index', 'Full menu with prices in Ghanaian cedis (GHS).'),
            $link('Branches', 'branches.index', 'Branch locations, phone numbers and opening hours.'),
            $link('Track an order', 'tracking.lookup', 'Look up the status of an existing order.'),
            '', '## About', '',
        ];

        if ($published['about']) {
            $lines[] = $link('About us', 'about');
        }

        $lines[] = $link('Contact us', 'contact', 'Contact form and branch phone numbers.');

        if ($published['faq']) {
            $lines[] = $link('FAQ', 'faq', 'Answers to common ordering questions.');
        }

        $policies = array_filter([
            'terms' => [self::DEFAULTS['terms'][0], 'policy.terms'],
            'refunds' => [self::DEFAULTS['refunds'][0], 'policy.refunds'],
            'privacy' => [self::DEFAULTS['privacy'][0], 'policy.privacy'],
            'cookies' => [self::DEFAULTS['cookies'][0], 'policy.cookies'],
        ], fn (string $slug) => $published[$slug], ARRAY_FILTER_USE_KEY);

        if ($policies !== []) {
            $lines = [...$lines, '', '## Policies', ''];

            foreach ($policies as [$label, $route]) {
                $lines[] = $link($label, $route);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Placeholders: :name and :city everywhere, plus the page's own.
     *
     * @param  array<string, string>  $replace
     */
    public function fill(string $text, array $replace = []): string
    {
        return strtr($text, [':name' => $this->profile->name(), ':city' => (string) $this->profile->city(), ...$replace]);
    }

    private function defaultDescription(): string
    {
        return $this->page('default')->meta_description
            ?: $this->profile->tagline()
            ?: (app(ModuleService::class)->enabled(ModuleService::DELIVERY)
                ? 'Order online from :name for delivery or pickup.'
                : 'Order online from :name for pickup.');
    }
}

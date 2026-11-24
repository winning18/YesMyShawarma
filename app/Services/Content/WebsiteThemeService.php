<?php

namespace App\Services\Content;

use App\Models\MenuItem;
use App\Services\Settings\SettingsService;
use App\Support\CustomerUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * The customer website's look (owner, 2026-11-24): every business picks one
 * of three themes under Settings → Website content → Website look, and every
 * theme is open to every business. A theme changes the header, the home
 * page, the menu and the item page; every other customer page keeps one
 * shared design wearing the theme's heading font. Colours always come from
 * the business's own brand (BusinessProfile). Classic is today's design and
 * the default, so a business that never chooses sees no change.
 *
 * A theme's own views live in resources/views/themes/<theme>/; view() falls
 * back to the Classic view for anything a theme doesn't override.
 */
class WebsiteThemeService
{
    public const CLASSIC = 'classic';

    public const PHOTO = 'photo';

    public const ONE_PAGE = 'onepage';

    public const DEFAULT = self::CLASSIC;

    /** Shown on the Website look page and the onboarding form. */
    public const THEMES = [
        self::CLASSIC => [
            'label' => 'Classic',
            'summary' => 'Menu first: a slideshow of your categories, then rows of dishes to swipe through. Works with or without many photos.',
            'font' => 'Inter',
        ],
        self::PHOTO => [
            'label' => 'Photo-led',
            'summary' => 'Big food photos: a full-width photo at the top and large photo cards on the menu. Best when most dishes have a good photo.',
            'font' => 'Fraunces and Inter',
        ],
        self::ONE_PAGE => [
            'label' => 'One-page',
            'summary' => 'Everything on one scrolling page: menu, about, branches and contact. Best for a short menu, a food truck or a single branch.',
            'font' => 'Bricolage Grotesque and Inter',
        ],
    ];

    /** Previewing a theme lasts this long after the link is made. */
    private const PREVIEW_MINUTES = 60;

    private const PREVIEW_SESSION_KEY = 'website_theme_preview';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly Request $request,
    ) {}

    /** The theme the business chose: what its customers see. */
    public function chosen(): string
    {
        return $this->valid($this->settings->get(SettingsService::WEBSITE_THEME)) ?? self::DEFAULT;
    }

    /** The theme this page is drawn in: a staff preview, else the chosen one. */
    public function current(): string
    {
        return $this->previewing() ?? $this->chosen();
    }

    /** The theme being previewed in this browser, if any. */
    public function previewing(): ?string
    {
        if (! $this->request->hasSession()) {
            return null;
        }

        return $this->valid($this->request->session()->get(self::PREVIEW_SESSION_KEY));
    }

    public function set(string $theme): void
    {
        if ($this->valid($theme) === null) {
            throw new InvalidArgumentException(__('Unknown website look.'));
        }

        $this->settings->set(SettingsService::WEBSITE_THEME, $theme);
    }

    /**
     * The view to render for a themed page: themes.<theme>.<name> when the
     * theme has its own, otherwise the Classic view $name.
     */
    public function view(string $name): string
    {
        $themed = 'themes.'.$this->current().'.'.str_replace('/', '.', $name);

        return $this->current() !== self::CLASSIC && View::exists($themed) ? $themed : $name;
    }

    /**
     * A link that opens the website in $theme for whoever follows it, for
     * an hour, without changing what customers see. Signed, so nobody can
     * make one up; it works on the business's own website domain too.
     */
    public function previewUrl(string $theme): string
    {
        $until = now()->addMinutes(self::PREVIEW_MINUTES)->timestamp;

        return CustomerUrl::route('home', [
            'theme_preview' => $theme,
            'until' => $until,
            'sig' => $this->signature($theme, $until),
        ]);
    }

    /**
     * Starts or ends a preview from this request's query string. True when
     * it did either (the caller then redirects to a clean address).
     */
    public function handlePreviewRequest(): bool
    {
        $theme = $this->request->query('theme_preview');

        if (! is_string($theme) || ! $this->request->hasSession()) {
            return false;
        }

        if ($theme === 'off') {
            $this->request->session()->forget(self::PREVIEW_SESSION_KEY);

            return true;
        }

        $until = (int) $this->request->query('until');
        $signature = (string) $this->request->query('sig');

        if ($this->valid($theme) !== null && $until >= now()->timestamp
            && hash_equals($this->signature($theme, $until), $signature)) {
            $this->request->session()->put(self::PREVIEW_SESSION_KEY, $theme);
        }

        return true;
    }

    /**
     * How many dishes on sale have a photo, for the Photo-led warning.
     *
     * @return array{with: int, total: int}
     */
    public function photoCoverage(): array
    {
        $items = MenuItem::where('is_active', true);

        return [
            'with' => (clone $items)->whereNotNull('image_path')->count(),
            'total' => $items->count(),
        ];
    }

    private function valid(?string $theme): ?string
    {
        return $theme !== null && isset(self::THEMES[$theme]) ? $theme : null;
    }

    private function signature(string $theme, int $until): string
    {
        return hash_hmac('sha256', 'website-theme-preview|'.$theme.'|'.$until, (string) config('app.key'));
    }
}

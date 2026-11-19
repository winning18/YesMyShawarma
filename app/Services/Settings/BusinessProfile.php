<?php

namespace App\Services\Settings;

use App\Models\Branch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Who the business is (Express POS, owner 2026-11-15): its name, wording,
 * contact details, social links, logo, app icon, brand colours and main
 * branch — everything that used to be written into the code for Yes My
 * Shawarma. Stored in `settings`, edited by the owner on the Business
 * profile page (BusinessProfileController).
 *
 * A fresh install starts neutral: the name from APP_NAME, no logo (the
 * name shows as text instead), no socials, the original colours. Yes My
 * Shawarma's own values were copied in by the
 * 2026_11_15_100000_fill_business_profile migration.
 */
class BusinessProfile
{
    public const NAME = 'business_name';

    public const LEGAL_NAME = 'business_legal_name';

    public const TAGLINE = 'business_tagline';

    public const PUBLIC_EMAILS = 'business_public_emails';

    public const CONTACT_RECIPIENTS = 'business_contact_recipients';

    public const INSTAGRAM = 'business_instagram_url';

    public const FACEBOOK = 'business_facebook_url';

    public const TIKTOK = 'business_tiktok_url';

    public const LOGO = 'business_logo_path';

    public const ICON = 'business_icon_path';

    public const MAIN_BRANCH = 'business_main_branch_id';

    /** Where the business is — search results and Google's restaurant data. */
    public const CITY = 'business_city';

    public const REGION = 'business_region';

    /** What it serves, comma-separated ("Shawarma, Burgers, Fast food"). */
    public const CUISINES = 'business_cuisines';

    /** A line or two at the bottom of every printed receipt (step 8a). */
    public const RECEIPT_MESSAGE = 'business_receipt_message';

    /**
     * Two brand colours, each with the lighter and darker shade the
     * customer site uses for backgrounds and hovers. Defaults are the
     * palette the site was built with.
     */
    public const COLOURS = [
        'brand_primary' => '#fffa00',
        'brand_primary_light' => '#fff9b3',
        'brand_primary_dark' => '#ccc800',
        'brand_accent' => '#ff0000',
        'brand_accent_light' => '#ffe6e6',
        'brand_accent_dark' => '#9a0200',
    ];

    private const DIRECTORY = 'business';

    /**
     * Every setting, read once for this profile object (one query per
     * page instead of a dozen). Fresh for each new BusinessProfile — the
     * container builds a new one each time it's asked — so nothing goes
     * stale across requests or queued jobs.
     *
     * @var array<string, ?string>|null
     */
    private ?array $values = null;

    public function __construct(private readonly SettingsService $settings) {}

    private function setting(string $key): ?string
    {
        $this->values ??= $this->settings->all();

        return $this->values[$key] ?? null;
    }

    private function store(string $key, ?string $value): void
    {
        $this->settings->set($key, $value);
        $this->values = null;
    }

    public function name(): string
    {
        return $this->setting(self::NAME) ?: (string) config('app.name');
    }

    /**
     * The company that operates the business, for legal pages — often
     * the same as the name.
     */
    public function legalName(): string
    {
        return $this->setting(self::LEGAL_NAME) ?: $this->name();
    }

    public function tagline(): ?string
    {
        return $this->setting(self::TAGLINE) ?: null;
    }

    public function receiptMessage(): ?string
    {
        return $this->setting(self::RECEIPT_MESSAGE) ?: null;
    }

    public function city(): ?string
    {
        return $this->setting(self::CITY) ?: null;
    }

    public function region(): ?string
    {
        return $this->setting(self::REGION) ?: null;
    }

    /**
     * @return list<string>
     */
    public function cuisines(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->setting(self::CUISINES)))));
    }

    /**
     * Email addresses shown to customers on the Contact page.
     *
     * @return list<string>
     */
    public function publicEmails(): array
    {
        return $this->list(self::PUBLIC_EMAILS);
    }

    /**
     * Where contact-form messages are sent.
     *
     * @return list<string>
     */
    public function contactRecipients(): array
    {
        return $this->list(self::CONTACT_RECIPIENTS);
    }

    /**
     * Only the networks the business has filled in.
     *
     * @return array<string, string> network => url
     */
    public function socialLinks(): array
    {
        return array_filter([
            'instagram' => $this->setting(self::INSTAGRAM),
            'facebook' => $this->setting(self::FACEBOOK),
            'tiktok' => $this->setting(self::TIKTOK),
        ]);
    }

    public function logoUrl(): ?string
    {
        return $this->publicUrl(self::LOGO);
    }

    /**
     * The logo as a file on disk, for PDF reports (DomPDF reads files,
     * not URLs).
     */
    public function logoFilePath(): ?string
    {
        $path = $this->setting(self::LOGO);

        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null;
    }

    /**
     * One square image (512×512 recommended) used as the favicon, the
     * home-screen icon and the app manifest's icons.
     */
    public function iconUrl(): ?string
    {
        return $this->publicUrl(self::ICON);
    }

    /**
     * @return array<string, string> setting key => #rrggbb
     */
    public function colours(): array
    {
        $colours = [];

        foreach (self::COLOURS as $key => $default) {
            $value = $this->setting($key);
            $colours[$key] = $value && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : $default;
        }

        return $colours;
    }

    /**
     * "r g b" channels per colour, for the CSS variables the stylesheet's
     * brand colours read (tailwind.config.js).
     *
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        $vars = [];

        foreach ($this->colours() as $key => $hex) {
            [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
            $vars['--'.Str::of($key)->replace('_', '-')] = "{$r} {$g} {$b}";
        }

        return $vars;
    }

    /**
     * Every active branch with a phone number, for the footer and the
     * Contact page: its name, the tel: link and the number as people
     * write it ("+233 (0) 243 635 265").
     *
     * @return list<array{name: string, tel: string, display: string}>
     */
    public function branchPhones(): array
    {
        return Branch::where('is_active', true)->whereNotNull('phone')->where('phone', '!=', '')->orderBy('id')->get(['name', 'phone'])
            ->map(fn (Branch $branch) => [
                'name' => $branch->name,
                'tel' => $branch->phone,
                'display' => preg_match('/^\+233(\d{3})(\d{3})(\d{3})$/', $branch->phone, $m) ? "+233 (0) {$m[1]} {$m[2]} {$m[3]}" : $branch->phone,
            ])
            ->all();
    }

    /**
     * Whose address heads general (all-branch) reports. Falls back to the
     * first branch created.
     */
    public function mainBranch(): ?Branch
    {
        $id = (int) $this->setting(self::MAIN_BRANCH);

        return ($id ? Branch::find($id) : null) ?? Branch::orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $values  validated form input
     */
    public function update(array $values, ?UploadedFile $logo = null, ?UploadedFile $icon = null, bool $removeLogo = false, bool $removeIcon = false): void
    {
        $text = [
            self::NAME => 'name',
            self::LEGAL_NAME => 'legal_name',
            self::TAGLINE => 'tagline',
            self::PUBLIC_EMAILS => 'public_emails',
            self::CONTACT_RECIPIENTS => 'contact_recipients',
            self::INSTAGRAM => 'instagram_url',
            self::FACEBOOK => 'facebook_url',
            self::TIKTOK => 'tiktok_url',
            self::MAIN_BRANCH => 'main_branch_id',
            self::CITY => 'city',
            self::REGION => 'region',
            self::CUISINES => 'cuisines',
            self::RECEIPT_MESSAGE => 'receipt_message',
        ];

        foreach ($text as $key => $field) {
            $value = trim((string) ($values[$field] ?? ''));
            $this->store($key, $value === '' ? null : $value);
        }

        foreach (['primary', 'accent'] as $colour) {
            $this->setColour($colour, $values[$colour.'_colour'] ?? null);
        }

        $this->storeImage(self::LOGO, 'logo', $logo, $removeLogo);
        $this->storeImage(self::ICON, 'icon', $icon, $removeIcon);
    }

    /**
     * A changed base colour gets its light and dark shades worked out:
     * light = 70% towards white (backgrounds), dark = 20% towards black
     * (hovers). An unchanged colour keeps its shades exactly.
     */
    private function setColour(string $name, ?string $hex): void
    {
        $hex = strtolower(trim((string) $hex));

        if (! preg_match('/^#[0-9a-f]{6}$/', $hex) || $hex === $this->colours()['brand_'.$name]) {
            return;
        }

        $this->store('brand_'.$name, $hex);
        $this->store('brand_'.$name.'_light', $this->mix($hex, '#ffffff', 0.7));
        $this->store('brand_'.$name.'_dark', $this->mix($hex, '#000000', 0.2));
    }

    private function mix(string $from, string $to, float $amount): string
    {
        $a = sscanf($from, '#%02x%02x%02x');
        $b = sscanf($to, '#%02x%02x%02x');

        return '#'.implode('', array_map(fn (int $x, int $y) => sprintf('%02x', (int) round($x + ($y - $x) * $amount)), $a, $b));
    }

    private function storeImage(string $key, string $name, ?UploadedFile $file, bool $remove): void
    {
        $previous = $this->setting($key);

        if (($file || $remove) && $previous) {
            Storage::disk('public')->delete($previous);
            $this->store($key, null);
        }

        if ($file) {
            // A new name each time, so browsers and Cloudflare never serve the old file.
            $path = $file->storeAs(self::DIRECTORY, $name.'-'.Str::lower(Str::random(8)).'.'.$file->extension(), 'public');
            $this->store($key, $path);
        }
    }

    private function publicUrl(string $key): ?string
    {
        $path = $this->setting($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * @return list<string>
     */
    private function list(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $this->setting($key)))));
    }
}

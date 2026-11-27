<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Services\Modules\ModuleService;
use InvalidArgumentException;

/**
 * The kind of business an install serves (owner, 2026-11-25; "Pharmacy and
 * retail plan", step R1): a restaurant, a pharmacy or a shop. Set once at
 * onboarding (expresspos:new-client --type), never from the app; Solusys
 * fixes a wrong choice with expresspos:business-type. No setting means a
 * restaurant, so every install from before keeps working exactly as it did.
 *
 * The type decides:
 * - which modules a business can have (allowsModule(); ModuleService treats
 *   one its type can't have as off, so it disappears completely);
 * - whether there's a kitchen: the kitchen board's steps, kitchen tickets
 *   and printers, Modifiers, the menu Time table and "What you serve" are
 *   a restaurant's only (`@restaurant` in Blade, the `restaurant` route
 *   middleware). A pharmacy's or shop's POS sale completes as it's paid;
 *   its website and delivery orders are worked on the Orders board;
 * - the words staff and customers see: lang/business-types/<type>/en.json
 *   is laid over the app's own English (applyWording()), so "Menu Editor"
 *   reads "Products" without a single view changing.
 */
class BusinessType
{
    public const SETTING = 'business_type';

    public const RESTAURANT = 'restaurant';

    public const PHARMACY = 'pharmacy';

    public const SHOP = 'shop';

    /** @var array<string, string> type => label */
    public const TYPES = [
        self::RESTAURANT => 'Restaurant',
        self::PHARMACY => 'Pharmacy',
        self::SHOP => 'Shop',
    ];

    /**
     * Modules only a restaurant can have (owner, 2026-11-25). Website and
     * Delivery are open to every type, chosen at onboarding.
     */
    public const RESTAURANT_ONLY_MODULES = [
        ModuleService::TABLES,
        ModuleService::STOCK,
        ModuleService::BOLT_FOOD,
        ModuleService::MARKET_LIST,
        ModuleService::FREEBIES,
        ModuleService::WHATSAPP_REWARD,
    ];

    private ?string $type = null;

    private ?string $wordingFor = null;

    public function current(): string
    {
        if ($this->type === null) {
            $stored = Setting::where('key', self::SETTING)->value('value');
            $this->type = isset(self::TYPES[$stored]) ? $stored : self::RESTAURANT;
        }

        return $this->type;
    }

    public function label(): string
    {
        return self::TYPES[$this->current()];
    }

    public function isRestaurant(): bool
    {
        return $this->current() === self::RESTAURANT;
    }

    /** A kitchen works orders step by step: restaurants only. */
    public function hasKitchen(): bool
    {
        return $this->isRestaurant();
    }

    public function allowsModule(string $module): bool
    {
        return self::typeAllowsModule($this->current(), $module);
    }

    public static function typeAllowsModule(string $type, string $module): bool
    {
        return $type === self::RESTAURANT || ! in_array($module, self::RESTAURANT_ONLY_MODULES, true);
    }

    /**
     * Onboarding (ClientSetupService) and Solusys's fix command only:
     * never from the app.
     */
    public function set(string $type): void
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Unknown business type {$type}. Use restaurant, pharmacy or shop.");
        }

        Setting::updateOrCreate(['key' => self::SETTING], ['value' => $type]);
        $this->type = $type;
        $this->applyWording();
    }

    /**
     * Lays the type's words over the app's own (called when the translator
     * is first made, and again by set()). A restaurant has none: the app's
     * own words are a restaurant's.
     */
    public function applyWording(): void
    {
        $type = $this->current();
        if ($type === self::RESTAURANT || $this->wordingFor === $type) {
            return;
        }

        $translator = app('translator');
        $translator->addJsonPath(lang_path('business-types/'.$type));
        // Lines already loaded were read without these words.
        $translator->setLoaded([]);
        $this->wordingFor = $type;
    }
}

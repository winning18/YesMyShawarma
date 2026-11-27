<?php

namespace App\Services\Modules;

use App\Models\Setting;
use App\Services\Settings\BusinessType;

/**
 * Module switches (Express POS step 3). Core — counter POS, kitchen board,
 * menu, branches, staff, shifts, sales reports, order history — is always
 * on; everything listed here is a module the business owner switches on
 * or off under Settings → Modules (ModuleController, business.manage).
 *
 * A module that's off disappears completely (CLAUDE.md product rule 3):
 * its routes answer 404 (the `module:` route middleware), its menu items,
 * POS options and report lines are hidden (`@module` in Blade), its
 * services refuse its actions and its scheduled jobs do nothing. Its data
 * is kept, so switching it back on brings everything back.
 *
 * A module the business's type can't have (BusinessType: Tables, Recipes
 * and stock, Bolt Food… are a restaurant's only) is off whatever is
 * stored, and isn't offered on the Modules page.
 *
 * Stored as settings `module_<key>` = '1' / '0'. No stored switch means
 * off on a real install (config/modules.php); Yes My Shawarma's are all
 * stored on (2026_11_15_130000_switch_on_every_module).
 */
class ModuleService
{
    public const WEBSITE = 'website';

    public const PAYSTACK = 'paystack';

    public const DELIVERY = 'delivery';

    public const BOLT_FOOD = 'bolt_food';

    public const PROMOTIONS = 'promotions';

    public const WHATSAPP_REWARD = 'whatsapp_reward';

    public const FREEBIES = 'freebies';

    public const MARKET_LIST = 'market_list';

    public const STOCK = 'stock';

    public const SMS = 'sms';

    public const REFUNDS = 'refunds';

    public const REVIEWS = 'reviews';

    public const TAX = 'tax';

    public const TABLES = 'tables';

    /**
     * label/description: shown on the Modules page. requires: modules
     * that must be on for this one to work. ready: false while its switch
     * isn't built yet — such a module stays on whatever is stored (it's
     * shown on the page as coming soon).
     *
     * @var array<string, array{label: string, description: string, requires: list<string>, ready: bool}>
     */
    public const MODULES = [
        self::WEBSITE => ['label' => 'Website ordering', 'description' => 'Customer website, online checkout, order tracking and customer accounts.', 'requires' => [], 'ready' => true],
        self::PAYSTACK => ['label' => 'Paystack', 'description' => 'Online card and MoMo payment at website checkout.', 'requires' => [self::WEBSITE], 'ready' => true],
        self::DELIVERY => ['label' => 'Delivery and riders', 'description' => 'Delivery orders, the rider app, distance-based delivery fees and fee collections.', 'requires' => [], 'ready' => true],
        self::BOLT_FOOD => ['label' => 'Bolt Food', 'description' => 'Take Bolt Food orders at the POS, kept out of sales with their own reference prefix and reports.', 'requires' => [], 'ready' => true],
        self::PROMOTIONS => ['label' => 'Promotions', 'description' => 'Promo codes and automatic offers such as buy 2 get 1 free.', 'requires' => [], 'ready' => true],
        self::WHATSAPP_REWARD => ['label' => 'WhatsApp group reward', 'description' => 'A fixed amount off the food for orders from your WhatsApp customer group.', 'requires' => [], 'ready' => true],
        self::FREEBIES => ['label' => 'Freebies', 'description' => 'Free orders for customers and staff, approved by a manager.', 'requires' => [], 'ready' => true],
        self::MARKET_LIST => ['label' => 'Market list and profit', 'description' => 'Record purchases, approve them, and see expenses and profit by period.', 'requires' => [], 'ready' => true],
        self::STOCK => ['label' => 'Recipes and stock', 'description' => 'Stock items, recipes that use them up as orders are accepted, and low-stock alerts.', 'requires' => [], 'ready' => true],
        self::SMS => ['label' => 'Customer SMS', 'description' => 'Order updates, refund notices and SMS broadcasts to customers.', 'requires' => [], 'ready' => true],
        self::REFUNDS => ['label' => 'Refunds and damage reports', 'description' => 'Request, approve and complete refunds; damage reports with photos.', 'requires' => [], 'ready' => true],
        self::REVIEWS => ['label' => 'Reviews', 'description' => 'Customers review their order after it arrives; approved reviews show on the website.', 'requires' => [self::WEBSITE], 'ready' => true],
        self::TABLES => ['label' => 'Tables', 'description' => 'Eat-in tables at the POS: guests order in rounds that go to the kitchen straight away, and pay one bill at the end.', 'requires' => [], 'ready' => true],
        self::TAX => ['label' => 'VAT and levies', 'description' => 'Charge VAT, NHIL, GETFund or other levies on sales, shown on receipts, with a tax report for filing.', 'requires' => [], 'ready' => true],
    ];

    /** @var array<string, ?string>|null module key => stored '1'/'0' */
    private ?array $stored = null;

    public function enabled(string $module): bool
    {
        if (! isset(self::MODULES[$module])) {
            throw new \InvalidArgumentException("Unknown module {$module}.");
        }

        if (! app(BusinessType::class)->allowsModule($module)) {
            return false;
        }

        if (! self::MODULES[$module]['ready']) {
            return true;
        }

        foreach (self::MODULES[$module]['requires'] as $required) {
            if (! $this->enabled($required)) {
                return false;
            }
        }

        return $this->switchedOn($module);
    }

    /**
     * What a module still needs before it can do anything (2026-11-24): a
     * Paystack or SMS module switched on with no keys yet fails quietly
     * otherwise. Null when nothing's missing. Keys are added by Solusys
     * Digital in Express POS Admin, never typed into the app.
     */
    public function missingSetup(string $module): ?string
    {
        return match ($module) {
            self::PAYSTACK => config('services.paystack.secret_key')
                ? null
                : __('Not working yet: Paystack\'s keys haven\'t been added, so customers can only pay cash. Ask Solusys Digital (026 370 2929) to add them.'),
            self::SMS => config('services.arkesel.api_key')
                ? null
                : __('Not working yet: the SMS key hasn\'t been added, so no texts are sent. Ask Solusys Digital (026 370 2929) to add it.'),
            default => null,
        };
    }

    /**
     * The switch itself, ignoring what it depends on — what the Modules
     * page shows as ticked.
     */
    public function switchedOn(string $module): bool
    {
        $this->stored ??= Setting::where('key', 'like', 'module%')->pluck('value', 'key')
            ->filter(fn (?string $value, string $key) => str_starts_with($key, 'module_'))
            ->mapWithKeys(fn (?string $value, string $key) => [substr($key, 7) => $value])
            ->all();

        $value = $this->stored[$module] ?? null;

        return $value === null ? (bool) config('modules.default_on') : $value === '1';
    }

    /**
     * The modules this business's type can have, as listed on the Modules page.
     *
     * @return array<string, array{label: string, description: string, requires: list<string>, ready: bool}>
     */
    public function available(): array
    {
        return array_filter(self::MODULES, fn (string $module) => app(BusinessType::class)->allowsModule($module), ARRAY_FILTER_USE_KEY);
    }

    public function set(string $module, bool $on): void
    {
        if (! isset(self::MODULES[$module])) {
            throw new \InvalidArgumentException("Unknown module {$module}.");
        }

        Setting::updateOrCreate(['key' => 'module_'.$module], ['value' => $on ? '1' : '0']);
        $this->stored = null;
    }

    /**
     * @param  array<string, bool>  $switches  module key => on
     */
    public function update(array $switches): void
    {
        foreach (self::MODULES as $module => $config) {
            if ($config['ready'] && array_key_exists($module, $switches) && app(BusinessType::class)->allowsModule($module)) {
                $this->set($module, $switches[$module]);
            }
        }
    }
}

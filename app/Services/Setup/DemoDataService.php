<?php

namespace App\Services\Setup;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\OptionGroup;
use App\Models\User;
use App\Services\Branches\WorkingHoursService;
use App\Services\Modules\ModuleService;
use App\Services\Settings\BusinessProfile;
use App\Services\Settings\BusinessType;
use App\Services\Settings\SettingsService;
use App\Services\Users\PinService;
use App\Services\Users\UserManagementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fills an empty install with "Express POS Demo", the made-up restaurant on
 * the shared staging install (staging.pos.solusysdigital.com, owner
 * 2026-11-24): two branches, a Ghanaian menu with options, delivery areas,
 * opening hours, tables, a staff account for every role and every module
 * on. All of it comes from database/seeders/data/demo_business.php; nothing
 * here is a real business. Built on ClientSetupService, so the demo is set
 * up exactly as a real client is.
 */
class DemoDataService
{
    public function __construct(
        private readonly ClientSetupService $setup,
        private readonly UserManagementService $users,
        private readonly PinService $pins,
        private readonly WorkingHoursService $workingHours,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return list<array{role: string, name: string, email: string, temporary_password: string, staff_code: ?string, pin: ?string}>
     */
    public function fill(): array
    {
        $data = require database_path('seeders/data/demo_business.php');
        $logo = $this->drawLogo($data['colours']);
        [$first, $second] = $data['branches'];
        $owner = collect($data['staff'])->firstWhere('role', 'owner');

        try {
            $result = $this->setup->setUp([
                'business' => $data['business'],
                'brand' => ['primary' => $data['colours']['primary'], 'accent' => $data['colours']['accent'], 'background' => $data['colours']['background'], 'logo' => $logo],
                'branch' => [...$first, 'opens_at' => $data['hours']['opens_at'], 'closes_at' => $data['hours']['closes_at']],
                'owner' => ['name' => $owner['name'], 'email' => $owner['email'], 'phone' => null],
                'support_email' => null,
                'modules' => array_keys(ModuleService::MODULES),
                'theme' => 'classic',
            ]);
        } finally {
            @unlink($logo);
        }

        return DB::transaction(function () use ($data, $result, $second) {
            $this->settings->set(BusinessProfile::TAGLINE, $data['tagline']);

            $branches = collect([$result['branch'], Branch::create([
                ...$second,
                'slug' => Str::slug($second['name']),
                'opens_at' => $data['hours']['opens_at'],
                'closes_at' => $data['hours']['closes_at'],
                'is_accepting_orders' => true,
                'is_active' => true,
            ])]);

            $this->menu($data, $branches);
            $this->deliveryAreas($data);

            foreach ($branches as $branch) {
                $this->workingHours->save($branch->id, array_fill_keys(range(1, 7), $data['hours']));
                foreach ($data['tables'] as $table) {
                    DiningTable::create(['branch_id' => $branch->id, 'name' => $table, 'is_active' => true]);
                }
            }

            $accounts = [[
                'role' => 'owner', 'name' => $result['owner']['user']->name, 'email' => $result['owner']['user']->email,
                'temporary_password' => $result['owner']['temporary_password'], 'staff_code' => null, 'pin' => null,
            ]];

            foreach (collect($data['staff'])->reject(fn (array $person) => $person['role'] === 'owner') as $person) {
                $made = $this->users->create(['name' => $person['name'], 'email' => $person['email'], 'phone' => null], $person['role'], $result['branch']->id);
                $pin = null;
                if ($person['pin'] ?? false) {
                    $pin = (string) random_int(1000, 9999);
                    $this->pins->set($made['user'], $pin);
                }
                $accounts[] = [
                    'role' => $person['role'], 'name' => $person['name'], 'email' => $person['email'],
                    'temporary_password' => $made['temporary_password'],
                    'staff_code' => $pin ? User::find($made['user']->id)->staff_code : null,
                    'pin' => $pin,
                ];
            }

            return $accounts;
        });
    }

    /**
     * Only the demo menu (with its options) and delivery areas, on an
     * install that has no menu yet: for a test client in Express POS Admin
     * (owner, 2026-11-25), so its POS and website have something in them.
     * Every item is on sale at every branch.
     *
     * @return int how many dishes were added
     */
    public function fillMenu(): int
    {
        if (MenuItem::exists()) {
            throw new RuntimeException('This install already has a menu; the demo menu only goes on an empty one.');
        }
        // The demo menu is a restaurant's; demo products for a pharmacy or shop come with R2.
        if (! app(BusinessType::class)->isRestaurant()) {
            throw new RuntimeException('The demo menu is for restaurants; there are no demo products for a '.strtolower(app(BusinessType::class)->label()).' yet.');
        }

        $data = require database_path('seeders/data/demo_business.php');

        DB::transaction(function () use ($data) {
            $this->menu($data, Branch::all());
            $this->deliveryAreas($data);
        });

        return MenuItem::count();
    }

    private function deliveryAreas(array $data): void
    {
        foreach ($data['delivery_areas'] as $name) {
            DeliveryArea::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }

    /** @param  Collection<int, Branch>  $branches */
    private function menu(array $data, $branches): void
    {
        $groups = collect($data['option_groups'])->map(function (array $group) {
            $model = OptionGroup::create(['name' => $group['name'], 'min_select' => $group['min'], 'max_select' => $group['max'], 'is_required' => $group['required']]);
            foreach ($group['options'] as [$name, $delta]) {
                $model->options()->create(['name' => $name, 'price_delta' => $delta, 'is_active' => true]);
            }

            return $model;
        });

        foreach ($data['categories'] as $c => $category) {
            $model = Category::create([
                'name' => $category['name'], 'slug' => Str::slug($category['name']), 'sort_order' => $c + 1,
                'is_active' => true, 'is_beverage' => $category['beverage'] ?? false,
            ]);

            foreach ($category['items'] as $i => [$name, $description, $price, $optionKeys]) {
                $item = MenuItem::create([
                    'category_id' => $model->id, 'name' => $name, 'slug' => Str::slug($name), 'description' => $description,
                    'base_price' => $price, 'sort_order' => $i + 1, 'is_active' => true,
                ]);
                foreach ($optionKeys as $sort => $key) {
                    $item->optionGroups()->attach($groups[$key]->id, ['sort_order' => $sort + 1]);
                }
                foreach ($branches as $branch) {
                    $branch->menuItems()->syncWithoutDetaching([$item->id => ['is_available' => true]]);
                }
            }
        }
    }

    /**
     * A plain logo for the demo, drawn here so no image file has to be kept
     * (a plate in the main colour, a fork bar in the accent), 800×240 PNG.
     *
     * @param  array{primary: string, accent: string}  $colours
     */
    private function drawLogo(array $colours): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('The demo logo needs PHP\'s GD extension.');
        }

        $image = imagecreatetruecolor(800, 240);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        $colour = fn (string $hex) => imagecolorallocate($image, ...sscanf($hex, '#%02x%02x%02x'));
        imagefilledellipse($image, 120, 120, 200, 200, $colour($colours['primary']));
        imagefilledellipse($image, 120, 120, 120, 120, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 260, 70, 760, 110, $colour($colours['primary']));
        imagefilledrectangle($image, 260, 140, 620, 170, $colour($colours['accent']));

        $path = tempnam(sys_get_temp_dir(), 'demo-logo').'.png';
        imagepng($image, $path);

        return $path;
    }
}

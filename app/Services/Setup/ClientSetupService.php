<?php

namespace App\Services\Setup;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Modules\ModuleService;
use App\Services\Settings\SettingsService;
use App\Services\Users\UserManagementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fills in a fresh install for a new Express POS client (step 10, the
 * `expresspos:new-client` command): the business name, its first branch,
 * the owner's account, Solusys Digital's support account, and the modules
 * it starts with. Every other module stays off (CLAUDE.md product rule 3).
 *
 * Only ever on an empty install — never on Yes My Shawarma or a client
 * already set up. Nothing here is any one business's: it's all input.
 */
class ClientSetupService
{
    /**
     * Marks an install set up by this command. Data migrations that copy
     * Yes My Shawarma's values in ("if the install has orders…") must skip
     * an install with this setting (CLAUDE.md product rule 5), or a
     * client's install would get Yes My Shawarma's values once it has
     * orders of its own.
     */
    public const SETUP_MARKER = 'client_setup_at';

    public function __construct(
        private readonly UserManagementService $users,
        private readonly ModuleService $modules,
        private readonly SettingsService $settings,
    ) {}

    public function isFreshInstall(): bool
    {
        return ! Branch::exists() && ! User::exists() && ! DB::table('orders')->exists();
    }

    /**
     * @param  array{business: string, branch: array{name: string, phone: string, address: string, lat: float, lng: float, opens_at: string, closes_at: string}, owner: array{name: string, email: string, phone: ?string}, support_email: ?string, modules: list<string>}  $input
     * @return array{branch: Branch, owner: array{user: User, temporary_password: string}, support: ?array{user: User, temporary_password: string}}
     */
    public function setUp(array $input): array
    {
        if (! $this->isFreshInstall()) {
            throw new RuntimeException('This install already has branches, users or orders. New-client setup only runs on an empty install.');
        }

        $unknown = array_diff($input['modules'], array_keys(ModuleService::MODULES));
        if ($unknown !== []) {
            throw new RuntimeException('Unknown module(s): '.implode(', ', $unknown).'.');
        }

        Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        return DB::transaction(function () use ($input) {
            $this->settings->set(self::SETUP_MARKER, now()->toIso8601String());
            $this->settings->set('business_name', trim($input['business']));

            $branch = Branch::create([
                ...$input['branch'],
                'slug' => Str::slug($input['branch']['name']) ?: 'main',
                'is_accepting_orders' => true,
                'is_active' => true,
            ]);
            $branch->menuItems()->attach(MenuItem::pluck('id'), ['is_available' => true]);
            $this->settings->set('business_main_branch_id', (string) $branch->id);

            // Every module stored, on or off, so nothing depends on the
            // install's default.
            foreach (array_keys(ModuleService::MODULES) as $module) {
                $this->modules->set($module, in_array($module, $input['modules'], true));
            }

            $owner = $this->users->create(['name' => $input['owner']['name'], 'email' => $input['owner']['email'], 'phone' => $input['owner']['phone'] ?? null], 'owner', $branch->id);

            $support = null;
            if (($input['support_email'] ?? null) && strcasecmp($input['support_email'], $input['owner']['email']) !== 0) {
                $support = $this->users->create(['name' => 'Solusys Digital Support', 'email' => $input['support_email']], 'owner', $branch->id);
            }

            return ['branch' => $branch, 'owner' => $owner, 'support' => $support];
        });
    }
}

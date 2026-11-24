<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Content\WebsiteThemeService;
use App\Services\Modules\ModuleService;
use App\Services\Settings\BusinessProfile;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What this install is, as one line of JSON (owner, 2026-11-24): the
 * business, its main branch, owner, switched-on modules, brand colours,
 * website look and domain, and the logo file. Express POS Admin's
 * clients:register-existing reads it to add an install that never went
 * through onboarding (Yes My Shawarma, at the MVP release).
 */
#[Signature('expresspos:describe')]
#[Description('Describe this install as JSON, for Express POS Admin')]
class DescribeInstallCommand extends Command
{
    public function handle(BusinessProfile $profile, ModuleService $modules, SettingsService $settings, WebsiteThemeService $themes): int
    {
        $branch = $profile->mainBranch();
        $ownerId = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'owner')->where('model_has_roles.model_type', User::class)
            ->orderBy('model_has_roles.model_id')->value('model_has_roles.model_id');
        $owner = $ownerId ? User::find($ownerId) : null;
        $colours = $profile->colours();

        $this->line(json_encode([
            'name' => $profile->name(),
            'branch' => $branch ? [
                'name' => $branch->name, 'phone' => (string) $branch->phone, 'address' => (string) $branch->address,
                'lat' => (float) $branch->lat, 'lng' => (float) $branch->lng,
                'opens_at' => substr((string) $branch->opens_at, 0, 5), 'closes_at' => substr((string) $branch->closes_at, 0, 5),
            ] : null,
            'owner' => $owner ? ['name' => $owner->name, 'email' => $owner->email, 'phone' => $owner->phone] : null,
            'modules' => array_values(array_filter(array_keys(ModuleService::MODULES), fn (string $key) => $modules->switchedOn($key))),
            'colours' => ['primary' => $colours['brand_primary'], 'accent' => $colours['brand_accent'], 'background' => $colours['brand_background']],
            'theme' => $themes->chosen(),
            'website_domain' => $settings->websiteDomain(),
            'logo' => $profile->logoFilePath(),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}

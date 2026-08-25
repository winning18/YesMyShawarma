<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ManagerSeeder extends Seeder
{
    /**
     * One manager account for local dev / demo login. Depends on
     * RolesAndPermissionsSeeder and BranchSeeder having already run.
     */
    public function run(): void
    {
        $branch = Branch::where('slug', 'ga-odumase')->first();

        if (! $branch) {
            return;
        }

        $manager = User::firstOrCreate(
            ['email' => 'manager@yesmyshawarma.test'],
            [
                'name' => 'Kofi Manager',
                'phone' => '+233240000003',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);

        if (! $manager->hasRole('manager')) {
            $manager->assignRole('manager');
        }
    }
}

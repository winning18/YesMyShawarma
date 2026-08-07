<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class OwnerSeeder extends Seeder
{
    /**
     * One owner account for local dev / demo login. Depends on
     * RolesAndPermissionsSeeder and BranchSeeder having already run.
     */
    public function run(): void
    {
        $branch = Branch::where('slug', 'ga-odumase')->first();

        if (! $branch) {
            return;
        }

        $owner = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);

        if (! $owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }
    }
}

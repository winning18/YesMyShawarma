<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class StaffSeeder extends Seeder
{
    /**
     * One staff account for local dev / demo login. Depends on
     * RolesAndPermissionsSeeder and BranchSeeder having already run.
     */
    public function run(): void
    {
        $branch = Branch::where('slug', 'ga-odumase')->first();

        if (! $branch) {
            return;
        }

        $staff = User::firstOrCreate(
            ['email' => 'staff@yesmyshawarma.test'],
            [
                'name' => 'Ama Staff',
                'phone' => '+233240000002',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);

        if (! $staff->hasRole('staff')) {
            $staff->assignRole('staff');
        }
    }
}

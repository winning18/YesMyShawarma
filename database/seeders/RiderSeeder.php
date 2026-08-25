<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RiderSeeder extends Seeder
{
    /**
     * One rider account for local dev / demo login. Depends on
     * RolesAndPermissionsSeeder and BranchSeeder having already run.
     */
    public function run(): void
    {
        $branch = Branch::where('slug', 'ga-odumase')->first();

        if (! $branch) {
            return;
        }

        $rider = User::firstOrCreate(
            ['email' => 'rider@yesmyshawarma.test'],
            [
                'name' => 'Kwame Rider',
                'phone' => '+233240000001',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);

        if (! $rider->hasRole('rider')) {
            $rider->assignRole('rider');
        }
    }
}

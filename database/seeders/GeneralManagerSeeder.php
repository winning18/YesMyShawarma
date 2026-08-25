<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class GeneralManagerSeeder extends Seeder
{
    /**
     * One general manager account for local dev / demo login, assigned at
     * every live branch — a single-branch assignment wouldn't demonstrate
     * what actually distinguishes this role from 'manager' (see
     * RolesAndPermissionsSeeder's matrix comment: the multi-branch
     * aggregate dashboard). Depends on RolesAndPermissionsSeeder and
     * BranchSeeder having already run.
     */
    public function run(): void
    {
        $branches = Branch::whereIn('slug', ['ga-odumase', 'pokuase-y-junction'])->get();

        if ($branches->isEmpty()) {
            return;
        }

        $generalManager = User::firstOrCreate(
            ['email' => 'gm@yesmyshawarma.test'],
            [
                'name' => 'Abena General Manager',
                'phone' => '+233240000004',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $registrar = app(PermissionRegistrar::class);

        foreach ($branches as $branch) {
            $registrar->setPermissionsTeamId($branch->id);

            if (! $generalManager->hasRole('general_manager')) {
                $generalManager->assignRole('general_manager');
            }
        }
    }
}

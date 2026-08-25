<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(BranchSeeder::class);
        $this->call(MenuSeeder::class);
        $this->call(DeliveryAreaSeeder::class);
        $this->call(StaffSeeder::class);
        $this->call(RiderSeeder::class);
        $this->call(ManagerSeeder::class);
        $this->call(GeneralManagerSeeder::class);
        $this->call(OwnerSeeder::class);
    }
}

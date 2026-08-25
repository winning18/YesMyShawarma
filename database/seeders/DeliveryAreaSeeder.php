<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use Illuminate\Database\Seeder;

class DeliveryAreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = require database_path('seeders/data/delivery_areas.php');

        foreach ($areas as $name) {
            DeliveryArea::firstOrCreate(['name' => $name]);
        }
    }
}

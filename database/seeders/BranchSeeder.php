<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = require database_path('seeders/data/branches.php');

        foreach ($branches as $branch) {
            Branch::firstOrCreate(['slug' => $branch['slug']], $branch);
        }
    }
}

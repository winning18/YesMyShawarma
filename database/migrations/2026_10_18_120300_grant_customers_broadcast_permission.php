<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data only, no schema: deploys run `migrate` but never the seeder, so
     * this is how the new customers.broadcast permission reaches
     * production. The seeder is idempotent (firstOrCreate + syncPermissions)
     * and stays the single source of the role matrix.
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data only, no schema: deploys run `migrate` but never the seeder, so
     * this is how freebies.approve reaches production.
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

<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data only, no schema: deploys run `migrate` but never the seeder, so
     * this is how staff get delivery_fees.collect (rider cash-ups at their
     * own till, owner 2026-11-20).
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        //
    }
};

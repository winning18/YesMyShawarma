<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data only, no schema: fills each menu item's description from the
     * owner-approved copy (database/seeders/data/menu_item_descriptions.php)
     * — only where it's still empty, so anything already written in the
     * dashboard is never overwritten. Bumps updated_at, which the sitemap
     * uses as lastmod, so Google sees the pages changed.
     */
    public function up(): void
    {
        $descriptions = require database_path('seeders/data/menu_item_descriptions.php');

        foreach ($descriptions as $slug => $description) {
            DB::table('menu_items')
                ->where('slug', $slug)
                ->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))
                ->update(['description' => $description, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

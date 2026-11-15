<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Express POS step 2b: menu and branch rules that were written into the
     * code for Yes My Shawarma become data each business sets.
     *
     *  - categories.is_beverage: which categories are drinks (no longer the
     *    hard-coded "drinks" slug) — drinks never count toward buy-X-get-Y
     *    and never get the WhatsApp reward.
     *  - promotions.free_menu_item_id: what a buy-X-get-Y offer gives away,
     *    chosen per promotion (was always Chicken Shawarma).
     *  - branches.areas_served: the neighbourhoods a branch serves, shown on
     *    its page (was config/delivery_areas.php).
     *
     * Yes My Shawarma's current values are copied in; the home page's
     * category strips (a setting) only on an install that already has
     * orders, like the business profile.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_beverage')->default(false)->after('is_active');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->foreignId('free_menu_item_id')->nullable()->after('free_quantity')->constrained('menu_items')->nullOnDelete();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->json('areas_served')->nullable()->after('address');
        });

        DB::table('categories')->where('slug', 'drinks')->update(['is_beverage' => true]);

        $chickenShawarma = DB::table('menu_items')->where('slug', 'chicken-shawarma')->value('id');
        if ($chickenShawarma) {
            DB::table('promotions')->where('type', 'buy_x_get_y_free')->whereNull('free_menu_item_id')->update(['free_menu_item_id' => $chickenShawarma]);
        }

        foreach (require database_path('seeders/data/branch_areas.php') as $slug => $areas) {
            DB::table('branches')->where('slug', $slug)->whereNull('areas_served')->update(['areas_served' => json_encode($areas)]);
        }

        if (DB::table('orders')->exists() && ! DB::table('settings')->where('key', 'home_menu_sliders')->exists()) {
            DB::table('settings')->insert([
                'key' => 'home_menu_sliders',
                'value' => json_encode([
                    ['title' => 'Shawarma favourites', 'slugs' => ['shawarma']],
                    ['title' => 'Burgers', 'slugs' => ['burgers']],
                    ['title' => 'Hot Dogs & Loaded Fries', 'slugs' => ['hot-dogs', 'loaded-fries']],
                    ['title' => 'Sandwiches', 'slugs' => ['sandwiches']],
                    ['title' => 'Drinks', 'slugs' => ['drinks']],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('areas_served'));
        Schema::table('promotions', fn (Blueprint $table) => $table->dropConstrainedForeignId('free_menu_item_id'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('is_beverage'));
        DB::table('settings')->where('key', 'home_menu_sliders')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Set only for the synthetic line a buy_x_get_y_free promotion
            // grants on top of what the customer actually added — its own
            // separate row (unit_price_snapshot/line_total both 0, no
            // options), never folded into the paid row it was earned from,
            // so it shows distinctly on the receipt/kitchen ticket. Still a
            // real unit for stock purposes — RecipeStockService deducts it
            // exactly like any other row, with no is_free check of its own.
            $table->boolean('is_free')->default(false)->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};

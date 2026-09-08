<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_item_options', function (Blueprint $table) {
            // Only ever settable above 1 for an option from a multi-select
            // group (see MenuPricingService) — a single-select group's
            // option is always exactly one, same as before this column
            // existed. Fixed for the whole line, not multiplied by the
            // order item's own quantity.
            $table->unsignedInteger('quantity')->default(1)->after('price_delta_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_options', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};

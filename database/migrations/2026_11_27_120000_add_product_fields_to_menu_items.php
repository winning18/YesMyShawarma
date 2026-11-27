<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products for pharmacies and shops (owner-approved 2026-11-27; plan step
     * R2): a product is a menu item with a code, a barcode, the unit it's
     * sold in and what it costs. All optional, so a restaurant's items are
     * untouched. cost_price is pesewas, like every amount.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('sku', 64)->nullable()->unique()->after('slug');
            $table->string('barcode', 64)->nullable()->unique()->after('sku');
            $table->string('unit_name', 30)->nullable()->after('base_price');
            $table->unsignedBigInteger('cost_price')->nullable()->after('unit_name');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropUnique(['barcode']);
            $table->dropColumn(['sku', 'barcode', 'unit_name', 'cost_price']);
        });
    }
};

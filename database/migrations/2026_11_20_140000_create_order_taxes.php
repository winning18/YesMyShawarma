<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Express POS step 9: optional tax (VAT, NHIL, levies).
     *
     *  - orders.tax_total: all the tax on the order, in pesewas — whether
     *    it was inside the prices (inclusive) or added on top.
     *  - order_taxes: one row per tax line as it was at the sale (name,
     *    rate in basis points, inclusive or added), with the tax on the
     *    food and on the delivery fee kept apart. Later rate changes never
     *    touch an order already placed.
     *
     * Nothing is backfilled: orders so far carry no tax. Yes My Shawarma
     * gets the Tax switch on (CLAUDE.md product rule 7) but no tax lines,
     * so nothing is charged and nothing changes on the day.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_total')->default(0)->after('delivery_fee');
        });

        Schema::create('order_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedInteger('rate_basis_points');
            $table->boolean('inclusive');
            $table->unsignedBigInteger('food_amount')->default(0);
            $table->unsignedBigInteger('delivery_amount')->default(0);
            $table->timestamps();
        });

        if (DB::table('orders')->exists() && ! DB::table('settings')->where('key', 'module_tax')->exists()) {
            DB::table('settings')->insert(['key' => 'module_tax', 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_taxes');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('tax_total'));
        DB::table('settings')->where('key', 'module_tax')->delete();
    }
};

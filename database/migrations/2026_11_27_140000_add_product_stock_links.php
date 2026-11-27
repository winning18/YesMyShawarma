<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock for pharmacies and shops (owner-approved 2026-11-27; plan step
     * R4): a branch's stock row for a product, what caused each movement,
     * the cost when sold, and whether a returned item went back on the
     * shelf. All empty or default on a restaurant's rows.
     */
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->nullable()->after('branch_id')->constrained()->cascadeOnDelete();
            $table->unique(['branch_id', 'menu_item_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('source_type', 40)->nullable()->after('shift_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_snapshot')->nullable()->after('list_price_snapshot');
        });

        Schema::table('refund_items', function (Blueprint $table) {
            $table->boolean('restocked')->default(false)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('refund_items', fn (Blueprint $table) => $table->dropColumn('restocked'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('cost_snapshot'));
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'menu_item_id']);
            $table->dropConstrainedForeignId('menu_item_id');
        });
    }
};

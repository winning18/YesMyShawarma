<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The scan-first POS (owner-approved 2026-11-27; plan step R3): which
     * pack a line sold and how many of the product's unit that is (for
     * stock, R4), and a price changed at the till: the price before and the
     * manager who approved it. All empty on a restaurant's lines.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_pack_id')->nullable()->after('menu_item_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('units')->default(1)->after('quantity');
            $table->unsignedBigInteger('list_price_snapshot')->nullable()->after('unit_price_snapshot');
            $table->foreignId('price_changed_by')->nullable()->after('list_price_snapshot')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_pack_id');
            $table->dropConstrainedForeignId('price_changed_by');
            $table->dropColumn(['units', 'list_price_snapshot']);
        });
    }
};

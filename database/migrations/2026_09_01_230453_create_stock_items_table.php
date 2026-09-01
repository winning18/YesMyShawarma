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
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Free-text display label ("pieces", "kg", "packs", "bottles",
            // "cans") — no unit-conversion logic, purely what's shown next
            // to the quantity.
            $table->string('unit');
            // Denormalised running total — stock_movements is the source
            // of truth, same relationship as orders/order_events.
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('low_stock_threshold', 10, 2);
            // Null until quantity first crosses below low_stock_threshold;
            // cleared once a restock brings it back at/above threshold —
            // the de-bounce that stops every subsequent sale re-sending
            // the owner SMS while already low.
            $table->timestamp('low_stock_alerted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};

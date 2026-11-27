<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pack sizes (owner-approved 2026-11-27; plan step R2): a product sold
     * by the strip, box or crate as well as by its own unit, each pack with
     * its own price and barcode. `units` is how many of the product's unit
     * it holds (a box of 100 tablets: 100). Amounts in pesewas.
     */
    public function up(): void
    {
        Schema::create('product_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('units');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('cost_price')->nullable();
            $table->string('barcode', 64)->nullable()->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['menu_item_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_packs');
    }
};

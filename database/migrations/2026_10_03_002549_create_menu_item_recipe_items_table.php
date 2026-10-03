<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_recipe_items', function (Blueprint $table) {
            $table->id();
            // Recipes are per-branch, not shared across branches — unlike
            // menu_item_components, which decomposes a combo into other
            // menu_items/options purely for reporting. stock_items are
            // themselves per-branch (each branch independently creates
            // and names its own "Sausages", "Cheese", etc. — there's no
            // shared ingredient catalog anywhere in this app), so a
            // recipe has to say "at this branch" to know which stock_item
            // row it's actually pointing at.
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // 'menu_item' -> source_menu_item_id set (the item's own base
            // recipe, e.g. Loaded Fries -> 2x Sausages). 'option' ->
            // source_option_id set (a customer-chosen extra also
            // consuming stock, e.g. "Extra cheese" -> 2x Cheese slices).
            // Exactly one of the two FKs is set, depending on this —
            // enforced in the form request, not a DB constraint, matching
            // menu_item_components' own validation style for this exact
            // shape.
            $table->string('source_type');
            $table->foreignId('source_menu_item_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('source_option_id')->nullable()->constrained('options')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            // How much of stock_item one unit of the source consumes —
            // multiplied by the order item's (or order_item_option's) own
            // quantity at deduction time, same pattern as
            // menu_item_components.quantity.
            $table->decimal('quantity', 10, 2);
            $table->timestamps();

            $table->unique(
                ['branch_id', 'source_type', 'source_menu_item_id', 'source_option_id', 'stock_item_id'],
                'menu_item_recipe_items_unique_source_stock_item'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_recipe_items');
    }
};

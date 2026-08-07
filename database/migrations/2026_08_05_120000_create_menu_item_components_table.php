<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_components', function (Blueprint $table) {
            $table->id();
            // The combo item being configured (e.g. "Signature (Chicken,
            // Cheese & Sausage)") — not the component itself.
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            // 'base' -> component_menu_item_id set (e.g. Chicken Shawarma,
            // recorded under its own category on the Today report).
            // 'modifier' -> component_option_id set (e.g. Cheese, recorded
            // under Modifiers). Exactly one of the two FKs is set,
            // depending on this — enforced in the form request, not a DB
            // constraint, matching this app's existing validation style
            // for similarly-shaped optional-FK data.
            $table->string('component_type');
            $table->foreignId('component_menu_item_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('component_option_id')->nullable()->constrained('options')->cascadeOnDelete();
            // How many of this component one unit of the combo implies —
            // usually 1, but a combo could bake in two of something.
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index('menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_components');
    }
};

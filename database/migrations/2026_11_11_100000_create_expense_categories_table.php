<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "kinds" market-list purchases are grouped by in reports (owner,
     * 2026-11-11). Filled here from database/seeders/data/expense_categories.php
     * rather than by a seeder, because deploys never run seeders.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('keywords');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (require database_path('seeders/data/expense_categories.php') as $i => $category) {
            DB::table('expense_categories')->insert([
                'name' => $category['name'],
                'keywords' => json_encode($category['keywords']),
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};

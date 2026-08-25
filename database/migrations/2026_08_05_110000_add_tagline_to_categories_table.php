<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Every category is now hero-slide-eligible (no more hardcoded
            // slug whitelist) — this replaces Category::HERO_SLIDE_TAGLINES'
            // per-slug tagline map with a plain, staff-editable field.
            $table->string('tagline')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('tagline');
        });
    }
};

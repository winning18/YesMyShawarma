<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved addresses (customer My account, owner 2026-11-11) carry exactly
     * what checkout collects: the named delivery area or a typed-in one,
     * and an optional GhanaPost code — checkout never required it.
     */
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->foreignId('delivery_area_id')->nullable()->after('label')->constrained()->nullOnDelete();
            $table->string('area_other', 100)->nullable()->after('delivery_area_id');
            $table->string('ghanapost_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_area_id');
            $table->dropColumn('area_other');
        });
    }
};

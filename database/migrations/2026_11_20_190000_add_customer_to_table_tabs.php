<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A table keeps its customer until it pays (owner, 2026-11-20): shown
     * on every till, put on each round and on the printed bill.
     */
    public function up(): void
    {
        Schema::table('table_tabs', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('opened_at');
            $table->string('customer_phone', 20)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('table_tabs', fn (Blueprint $table) => $table->dropColumn(['customer_name', 'customer_phone']));
    }
};

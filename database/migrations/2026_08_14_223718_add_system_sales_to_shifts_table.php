<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // A snapshot, not a live-derivable value — "today's system
            // sales" is a moving target throughout the day, so what matters
            // for reconciliation is what the system knew at the exact
            // moment this shift was closed, not whatever the whole day
            // totals to by the time someone reads a report later. Null for
            // every role except staff, whose total_sales is validated
            // against this same figure at end time (ShiftController).
            $table->unsignedBigInteger('system_sales')->nullable()->after('total_sales');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('system_sales');
        });
    }
};

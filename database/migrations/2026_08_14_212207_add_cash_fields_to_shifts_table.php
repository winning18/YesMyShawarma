<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Optional at start (not every role is required to enter it —
            // see ShiftController), required for staff specifically at end,
            // enforced at the application layer, not the column itself.
            $table->unsignedBigInteger('starting_cash')->nullable()->after('started_at');
            $table->unsignedBigInteger('total_sales')->nullable()->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['starting_cash', 'total_sales']);
        });
    }
};

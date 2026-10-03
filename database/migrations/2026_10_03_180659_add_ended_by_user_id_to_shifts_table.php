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
        Schema::table('shifts', function (Blueprint $table) {
            // Shifts moved from per-person to per-branch (one open shift at
            // a branch at a time, joined rather than duplicated by every
            // staff member working there) — user_id now means "who opened
            // it", and the closer may be a different person entirely.
            $table->foreignId('ended_by_user_id')->nullable()->after('ended_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ended_by_user_id');
        });
    }
};

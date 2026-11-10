<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account suspension (owner, 2026-11-10) — null suspended_at = active.
     * A suspended account can't log in on any guard, is signed out on its
     * next request, and (customers) can't place web orders by phone. Kept
     * apart from soft deletes: a suspension is lifted, a deletion isn't.
     * See AccountSuspensionService.
     */
    public function up(): void
    {
        foreach (['users', 'customers'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('suspended_at')->nullable();
                $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('suspension_reason')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['users', 'customers'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('suspended_by');
                $table->dropColumn(['suspended_at', 'suspension_reason']);
            });
        }
    }
};

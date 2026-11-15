<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Express POS step 7a: several cashiers, as named counters (owner,
     * 2026-11-15).
     *
     *  - terminals: a branch's counters ("Counter 1"). A device becomes a
     *    counter when a manager sets it up; device_token_hash is the
     *    SHA-256 of the secret kept in that device's cookie.
     *  - shifts.terminal_id: the counter a till session belongs to; null
     *    is the branch's one shared shift (how every branch works until it
     *    has counters).
     *  - orders.shift_id: the till that took the money — the counter's
     *    till for a POS order, the till where a web order was accepted.
     *
     * Nothing is backfilled: a branch with no counters works exactly as
     * before.
     */
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('device_token_hash', 64)->nullable()->unique();
            $table->timestamp('paired_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('shift_id'));
        Schema::table('shifts', fn (Blueprint $table) => $table->dropConstrainedForeignId('terminal_id'));
        Schema::dropIfExists('terminals');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riders hand in the cash delivery fees they collect, from 2026-11-11
     * (owner) — before that every fee was the rider's. A collection is one
     * rider's cash fees at one branch for a period; each order can be in
     * one live collection only (the unique order_id). Orders themselves
     * are never written to.
     */
    public function up(): void
    {
        Schema::create('delivery_fee_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('rider_id')->constrained('users');
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedBigInteger('expected'); // pesewas
            $table->unsignedBigInteger('received'); // pesewas
            $table->unsignedInteger('order_count');
            $table->text('note')->nullable();
            $table->foreignId('collected_by')->constrained('users');
            $table->timestamp('collected_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'rider_id']);
        });

        Schema::create('delivery_fee_collection_orders', function (Blueprint $table) {
            $table->id();
            // Named by hand: the generated name is over MySQL's 64-character limit.
            $table->foreignId('delivery_fee_collection_id')->constrained('delivery_fee_collections', 'id', 'dfc_orders_collection_fk')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained();
            $table->unsignedBigInteger('cash_fee'); // pesewas, as it stood when collected
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'delivery_fee_hand_in_starts_on'],
            ['value' => '2026-11-11', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_fee_collection_orders');
        Schema::dropIfExists('delivery_fee_collections');
        DB::table('settings')->where('key', 'delivery_fee_hand_in_starts_on')->delete();
    }
};

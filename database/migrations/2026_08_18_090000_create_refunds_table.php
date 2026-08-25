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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Denormalised — every branch-owned table carries its own
            // branch_id and is covered by the branch global scope
            // (schema.md), rather than reaching it through order_id.
            $table->foreignId('branch_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->text('reason');
            // pending -> approved -> completed, or pending -> denied.
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            // Paystack's refund transaction reference — paystack-paid
            // orders only; null for cash/momo (nothing to call an API for).
            $table->string('provider_reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

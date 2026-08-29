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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Denormalised — every branch-owned table carries its own
            // branch_id and is covered by the branch global scope
            // (schema.md), rather than reaching it through order_id.
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            // pending -> approved, or pending -> rejected.
            $table->string('status')->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users');
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_note')->nullable();
            $table->timestamps();

            $table->unique('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

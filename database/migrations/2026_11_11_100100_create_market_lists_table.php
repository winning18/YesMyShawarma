<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            // Africa/Accra calendar date of the purchase — what reports
            // group by, not when it was typed in or approved.
            $table->date('purchased_on');
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('recorder_role', 32);
            // Set when it came from the expenses entered at shift close.
            $table->foreignId('shift_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedBigInteger('total')->default(0); // pesewas, sum of its items
            $table->string('receipt_path')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['status', 'purchased_on']);
        });

        Schema::create('market_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->unsignedBigInteger('amount'); // pesewas
            $table->timestamps();
        });

        Schema::create('market_list_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_list_id')->constrained()->cascadeOnDelete();
            // Null only for rows written by the one-off conversion of
            // pre-existing shift expenses — no person did that.
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action', 30);
            $table->text('note')->nullable();
            // No updated_at — immutable ledger, same as order_events.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_list_events');
        Schema::dropIfExists('market_list_items');
        Schema::dropIfExists('market_lists');
    }
};

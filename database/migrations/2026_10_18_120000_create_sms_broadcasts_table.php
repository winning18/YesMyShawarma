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
        Schema::create('sms_broadcasts', function (Blueprint $table) {
            $table->id();
            // The owner/GM's own text only — the per-recipient opt-out link
            // is appended at send time (SmsBroadcastService::composeFor()),
            // never stored here.
            $table->text('message');
            // Null = every customer. Set = only customers who have ordered
            // from this branch. Not a branch-owned row in the BranchScope
            // sense (customers are business-wide), so no global scope.
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            // scheduled -> sending -> sent, or scheduled -> cancelled.
            $table->string('status')->default('scheduled');
            // UTC, like every datetime here. "Send now" is just a
            // scheduled_at of now — one path through the scheduler.
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_broadcasts');
    }
};

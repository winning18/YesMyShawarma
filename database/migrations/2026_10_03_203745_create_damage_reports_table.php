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
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            // Denormalised — every branch-owned table carries its own
            // branch_id and is covered by the branch global scope
            // (schema.md), rather than reaching it through order_id.
            $table->foreignId('branch_id')->constrained();
            // Set only for a rider's order-specific report (damage found
            // while delivering). Null for staff's general item report.
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            // Set only when staff links the report to a specific stock
            // item — nullOnDelete, not cascade: the report is a standalone
            // audit record and must survive the stock item itself later
            // being removed, same reasoning as every other audit table in
            // this app (order_events, stock_movements) outliving the thing
            // it was about.
            $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            // Snapshot at filing time ('rider' or 'staff') — matches
            // order_events.actor_type's own reasoning: the reporter's role
            // could change later, this records what they were at the time.
            $table->string('reporter_role');
            $table->text('description');
            // Private disk only (config/filesystems.php's 'local', not
            // 'public') — never a publicly guessable URL, served through a
            // permission-gated route instead (DamageReportController).
            // Nullable not because filing is ever photo-less (it isn't —
            // DamageReportController::store() requires one) but because
            // DeleteExpiredDamageReportPhotos nulls it out once the file
            // itself is deleted, while the row stays for audit.
            $table->string('photo_path')->nullable();
            // Set the first time a *reviewer* (not the reporter) opens the
            // photo — starts the 24h window after which the scheduled
            // DeleteExpiredDamageReportPhotos command deletes the file
            // (this row stays, for audit, with photo_path nulled).
            $table->timestamp('photo_viewed_at')->nullable();
            // pending -> approved, or pending -> denied. No further states
            // — approving is an acknowledgement only, no automatic stock or
            // financial consequence (a deliberate scope decision, not an
            // oversight).
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};

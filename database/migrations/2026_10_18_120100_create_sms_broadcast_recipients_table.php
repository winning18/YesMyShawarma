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
        Schema::create('sms_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Snapshot of the normalised number actually texted — the
            // customer's phone could change later.
            $table->string('phone');
            // The /stop/{code} link in this recipient's SMS. Short on
            // purpose: every character of it is paid for in SMS length.
            $table->string('opt_out_code', 8)->unique();
            // pending -> sent | failed. Only pending rows are ever sent,
            // so a retried batch never texts the same person twice.
            $table->string('status')->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['sms_broadcast_id', 'customer_id']);
            // Two customer rows can't share a phone (customers.phone is
            // unique), but legacy rows stored in a non-E.164 form could
            // normalise to the same number — one text per number.
            $table->unique(['sms_broadcast_id', 'phone']);
            $table->index(['sms_broadcast_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_broadcast_recipients');
    }
};

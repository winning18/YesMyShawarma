<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner, 2026-11-14 — two kinds of POS order:
     *  - Freebies: payment_method 'complimentary', never sales, recorded
     *    with who got it and why, approved by a manager afterwards.
     *  - WhatsApp group orders: orders.source = 'whatsapp', GH₵10 off the
     *    food (stored in the existing discount_total).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Null = walk-in/phone/web; 'whatsapp' = the WhatsApp group.
            $table->string('source', 20)->nullable()->after('channel');
        });

        Schema::create('complimentary_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained();
            $table->string('recipient_type', 20); // customer | staff
            $table->foreignId('recipient_user_id')->nullable()->constrained('users');
            $table->string('recipient_name')->nullable();
            $table->string('reason');
            $table->string('status', 20); // pending | approved | rejected
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('recorder_role', 32);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'whatsapp_reward_amount'],
            ['value' => '1000', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('complimentary_orders');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('source'));
        DB::table('settings')->where('key', 'whatsapp_reward_amount')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Returns at the till (owner-approved 2026-11-27; plan step R3): a
     * refund of kind `return` lists the items that came back
     * (refund_items), and shift_id is the till the cash came out of, so it
     * comes off that till's expected cash. Every refund so far is a
     * `refund`, with no till.
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('kind', 20)->default('refund')->after('branch_id');
            $table->foreignId('shift_id')->nullable()->after('kind')->constrained()->nullOnDelete();
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn('kind');
        });
    }
};

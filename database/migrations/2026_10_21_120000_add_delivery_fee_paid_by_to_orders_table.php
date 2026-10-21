<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POS MoMo delivery orders only: how the customer pays the delivery
     * fee — 'momo' (sent with the food, the business holds it and staff
     * pay the rider) or 'rider_cash' (cash to the rider at the door).
     * Varies per order (owner, 2026-10-21). Null everywhere else, and on
     * MoMo orders placed before this existed — read as 'momo'. Cash and
     * Paystack orders never need it: who holds their fee follows from the
     * payment itself (see DeliveryFeeReportService::split()).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_fee_paid_by')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_fee_paid_by');
        });
    }
};

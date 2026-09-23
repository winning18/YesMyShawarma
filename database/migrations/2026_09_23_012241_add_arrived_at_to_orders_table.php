<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stamped when a rider taps "Arrived" for a delivery — a denormalised
     * timestamp, same reasoning as dispatched_at/delivered_at (order_events
     * remains the source of truth). Deliberately not a new order status:
     * the state machine stays dispatched -> delivered/failed exactly as
     * documented in orders.md, this just gates the rider's own "Mark
     * delivered" button and is the moment a still-unset delivery fee (the
     * customer never shared their location at checkout) gets calculated
     * from the rider's own position instead.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('arrived_at')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('arrived_at');
        });
    }
};

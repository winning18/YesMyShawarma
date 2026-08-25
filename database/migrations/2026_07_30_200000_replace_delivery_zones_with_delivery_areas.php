<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the old per-branch, radius/fee-per-zone model (never used in
     * production — zero rows existed) with a single shared list of named
     * areas. Delivery pricing is no longer per-zone at all: it's distance
     * (branch to the customer's shared live location) × a flat rate,
     * computed when the order is marked delivered — see
     * App\Services\Delivery\DeliveryFeeCalculator. This table exists purely
     * so the dropdown can tell a rider "this delivery is headed to Amasaman"
     * etc.
     */
    public function up(): void
    {
        Schema::dropIfExists('delivery_zones');

        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_areas');

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('delivery_fee');
            $table->unsignedBigInteger('min_order_total')->default(0);
            $table->unsignedInteger('radius_metres');
            $table->decimal('centre_lat', 10, 7);
            $table->decimal('centre_lng', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};

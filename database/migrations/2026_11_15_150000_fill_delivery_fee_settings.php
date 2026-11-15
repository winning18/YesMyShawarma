<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Delivery fees become each business's settings (Settings → General →
     * Delivery fees). Yes My Shawarma's current amounts — GH₵5 per km,
     * GH₵10 minimum fee, GH₵20 smallest order — are stored on an install
     * that already has orders, so nothing changes; an existing setting is
     * never overwritten.
     */
    private const VALUES = [
        'delivery_rate_per_km' => '500',
        'delivery_minimum_fee' => '1000',
        'delivery_minimum_order' => '2000',
    ];

    public function up(): void
    {
        if (! DB::table('orders')->exists()) {
            return;
        }

        foreach (self::VALUES as $key => $value) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::VALUES))->delete();
    }
};

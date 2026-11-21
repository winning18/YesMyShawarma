<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Checking MoMo by transaction ID became an owner setting (2026-11-20),
     * off for a new business. Yes My Shawarma has always checked, so it
     * keeps checking (CLAUDE.md product rule 5); a client install doesn't.
     */
    public function up(): void
    {
        $isClientInstall = DB::table('settings')->where('key', 'client_setup_at')->exists();

        if (DB::table('orders')->exists() && ! $isClientInstall
            && ! DB::table('settings')->where('key', 'momo_check_transaction_id')->exists()) {
            DB::table('settings')->insert(['key' => 'momo_check_transaction_id', 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'momo_check_transaction_id')->delete();
    }
};

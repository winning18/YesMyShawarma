<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The website's background became the business's third brand colour
     * (owner, 2026-11-24). Yes My Shawarma's site has always been black, so
     * black is stored for it (CLAUDE.md product rule 5); a client install
     * chooses its own at onboarding.
     */
    public function up(): void
    {
        $isClientInstall = DB::table('settings')->where('key', 'client_setup_at')->exists();

        if (! DB::table('orders')->exists() || $isClientInstall
            || DB::table('settings')->where('key', 'brand_background')->exists()) {
            return;
        }

        foreach (['brand_background' => '#000000', 'brand_background_light' => '#b3b3b3', 'brand_background_dark' => '#000000'] as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['brand_background', 'brand_background_light', 'brand_background_dark'])->delete();
    }
};

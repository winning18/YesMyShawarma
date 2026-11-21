<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A table bill's own number (owner, 2026-11-20), e.g. YMGS-T-4KQ7: on
     * the final receipt and every round's kitchen ticket, and on each
     * round in order history, so one number ties a table's rounds and its
     * receipt together. Bills already open or settled get one now.
     */
    public function up(): void
    {
        Schema::table('table_tabs', function (Blueprint $table) {
            $table->string('reference', 40)->nullable()->unique()->after('dining_table_id');
        });

        $prefix = DB::table('settings')->where('key', 'order_reference_prefix_pos')->value('value') ?: 'POS';
        foreach (DB::table('table_tabs')->whereNull('reference')->pluck('id') as $id) {
            DB::table('table_tabs')->where('id', $id)->update(['reference' => strtoupper($prefix).'-T-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        Schema::table('table_tabs', fn (Blueprint $table) => $table->dropColumn('reference'));
    }
};

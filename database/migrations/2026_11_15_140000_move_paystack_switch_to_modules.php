<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Express POS step 3b: the Paystack on/off that lived in Settings →
     * General (`paystack_enabled`, off when missing) becomes the Paystack
     * module switch. Its current value is carried over exactly — on an
     * install with orders a missing row meant off, so that's what it
     * stays — replacing the "on" that the switch-on-every-module
     * migration stored before this switch existed.
     */
    public function up(): void
    {
        $old = DB::table('settings')->where('key', 'paystack_enabled')->value('value');

        if ($old === null && ! DB::table('orders')->exists()) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'module_paystack'],
            ['value' => $old === '1' ? '1' : '0', 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('settings')->where('key', 'paystack_enabled')->delete();
    }

    public function down(): void
    {
        $module = DB::table('settings')->where('key', 'module_paystack')->value('value');

        if ($module !== null) {
            DB::table('settings')->updateOrInsert(['key' => 'paystack_enabled'], ['value' => $module, 'updated_at' => now(), 'created_at' => now()]);
        }
    }
};

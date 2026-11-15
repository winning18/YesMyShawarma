<?php

use App\Services\Modules\ModuleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Express POS step 3: modules can be switched off. Yes My Shawarma
     * uses every one, so an install that already has orders gets every
     * switch stored on — including those whose switch arrives in a later
     * part of step 3. A new install stores nothing: everything starts off
     * (config/modules.php). An existing switch is never overwritten.
     */
    public function up(): void
    {
        if (! DB::table('orders')->exists()) {
            return;
        }

        foreach (array_keys(ModuleService::MODULES) as $module) {
            if (! DB::table('settings')->where('key', 'module_'.$module)->exists()) {
                DB::table('settings')->insert(['key' => 'module_'.$module, 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'like', 'module%')->get(['id', 'key'])
            ->filter(fn ($row) => str_starts_with($row->key, 'module_'))
            ->each(fn ($row) => DB::table('settings')->where('id', $row->id)->delete());
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rider cash-ups (owner, 2026-11-20): a rider's end-of-day hand-in of
     * everything they must account for — the food money they collected at
     * doors, and the delivery fees too when the business has fees handed in
     * (setting delivery_fee_handling: 'handed_in', the default, or
     * 'kept_by_rider'). Received at a till, and counted in its cash-up.
     *
     * The fee-only delivery fee collections (2026-11-11) become the first
     * cash-ups, unchanged: every past hand-in is kept.
     *
     *  - rider_cashups: + shift_id (the till that received it), expected
     *    food / fee, fee_credit (fees the business owes a rider who keeps
     *    them, taken off what they hand in), fee_handling as it was. expected
     *    and received may now be negative: a rider owed more than they hold
     *    is paid out.
     *  - rider_cashup_orders: + food_amount, + fee_credit; cash_fee →
     *    fee_amount.
     *  - setting rider_cashup_food_starts_on: food money joins cash-ups from
     *    today on an install already taking orders (before, it went into
     *    the till that took the order); a new install, from the start.
     */
    public function up(): void
    {
        Schema::rename('delivery_fee_collections', 'rider_cashups');
        Schema::rename('delivery_fee_collection_orders', 'rider_cashup_orders');

        Schema::table('rider_cashup_orders', function (Blueprint $table) {
            $table->renameColumn('delivery_fee_collection_id', 'rider_cashup_id');
        });
        Schema::table('rider_cashup_orders', function (Blueprint $table) {
            $table->renameColumn('cash_fee', 'fee_amount');
            $table->unsignedBigInteger('food_amount')->default(0)->after('order_id');
            $table->unsignedBigInteger('fee_credit')->default(0);
        });

        Schema::table('rider_cashups', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('rider_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('expected_food')->default(0)->after('period_to');
            $table->unsignedBigInteger('expected_fee')->default(0)->after('expected_food');
            $table->unsignedBigInteger('fee_credit')->default(0)->after('expected_fee');
            $table->string('fee_handling', 16)->default('handed_in')->after('fee_credit');
        });
        Schema::table('rider_cashups', function (Blueprint $table) {
            $table->bigInteger('expected')->change();
            $table->bigInteger('received')->change();
        });

        DB::table('rider_cashups')->update(['expected_fee' => DB::raw('expected')]);

        $isClientInstall = DB::table('settings')->where('key', 'client_setup_at')->exists();
        if (DB::table('orders')->exists() && ! $isClientInstall
            && ! DB::table('settings')->where('key', 'rider_cashup_food_starts_on')->exists()) {
            DB::table('settings')->insert([
                'key' => 'rider_cashup_food_starts_on',
                'value' => Carbon::now('Africa/Accra')->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'rider_cashup_food_starts_on')->delete();

        Schema::table('rider_cashups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['expected_food', 'expected_fee', 'fee_credit', 'fee_handling']);
        });
        Schema::table('rider_cashup_orders', function (Blueprint $table) {
            $table->dropColumn(['food_amount', 'fee_credit']);
            $table->renameColumn('fee_amount', 'cash_fee');
        });
        Schema::table('rider_cashup_orders', function (Blueprint $table) {
            $table->renameColumn('rider_cashup_id', 'delivery_fee_collection_id');
        });

        Schema::rename('rider_cashup_orders', 'delivery_fee_collection_orders');
        Schema::rename('rider_cashups', 'delivery_fee_collections');
    }
};

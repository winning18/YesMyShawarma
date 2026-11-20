<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SMS broadcasts' "Send to" lists (owner, 2026-11-20): uploaded contacts
     * are their own list, apart from real customers, and a broadcast can
     * go to several lists at once.
     *
     * customers.imported_at: when a number first came in through Customers
     * → Import contacts. Earlier uploads were never recorded, so they're
     * filled in as the closest match: a customer with a phone, no account
     * and no orders, which is exactly what an upload created (owner
     * approved the approximation).
     *
     * sms_broadcasts.audience: the ticked lists. Older broadcasts keep
     * branch_id (SmsBroadcast::selection()).
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('imported_at')->nullable()->after('marketing_opted_out_at');
        });

        Schema::table('sms_broadcasts', function (Blueprint $table) {
            $table->json('audience')->nullable()->after('branch_id');
        });

        DB::table('customers')
            ->whereNotNull('phone')
            ->whereNull('password')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('orders')->whereColumn('orders.customer_id', 'customers.id'))
            ->update(['imported_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('sms_broadcasts', function (Blueprint $table) {
            $table->dropColumn('audience');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('imported_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidence photos are deleted once they've done their job (owner,
     * 2026-11-24): a receipt photo goes 24 hours after a reviewer has
     * viewed it and the list is approved. The list, its items and amounts
     * stay. This is when a reviewer first opened it.
     */
    public function up(): void
    {
        Schema::table('market_lists', function (Blueprint $table) {
            $table->timestamp('receipt_viewed_at')->nullable()->after('receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('market_lists', fn (Blueprint $table) => $table->dropColumn('receipt_viewed_at'));
    }
};

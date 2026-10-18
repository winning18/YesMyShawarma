<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when a customer taps the opt-out link in an SMS broadcast — they
     * are left out of every later broadcast. Order SMS are unaffected.
     * Separate from marketing_opt_in on purpose: that column means "agreed
     * to marketing" (nobody has, nothing collects it), while broadcasts go
     * to every customer who hasn't opted OUT — an owner decision.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('marketing_opted_out_at')->nullable()->after('marketing_opt_in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('marketing_opted_out_at');
        });
    }
};

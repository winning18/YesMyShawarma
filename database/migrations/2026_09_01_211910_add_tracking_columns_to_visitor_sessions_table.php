<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visitor_sessions', function (Blueprint $table) {
            // Captured once, when the session row is first created — never
            // overwritten on later visits from the same token, since these
            // describe how the visit *started*, not its most recent hit.
            $table->string('ip_address', 45)->nullable()->after('token');
            $table->string('user_agent')->nullable()->after('ip_address');
            $table->string('referrer')->nullable()->after('user_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitor_sessions', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'referrer']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Walk-in POS customers can now skip the phone field — MySQL's unique
     * index already permits multiple NULLs (unlike some other RDBMS), so
     * no index change is needed alongside this; see CustomerService::
     * findOrCreateByPhone()'s own handling of a null phone.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }
};

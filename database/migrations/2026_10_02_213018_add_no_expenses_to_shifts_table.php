<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Set when whoever ends the shift explicitly confirms there
            // were none, rather than adding shift_expenses rows — lets a
            // genuine zero-expense day satisfy the mandatory check without
            // a fake GHS 0.00 row, and lets the Today report tell "confirmed
            // zero" apart from "ended before this feature existed" (both
            // show no shift_expenses rows, but only one set this flag).
            $table->boolean('no_expenses')->default(false)->after('closing_note');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('no_expenses');
        });
    }
};

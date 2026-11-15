<?php

use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Users\StaffCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Express POS step 7b: till staff sign in at a counter with a Staff ID
     * and a PIN (owner, 2026-11-15).
     *
     *  - users.staff_code: "YMS-0042", unique; given to till staff.
     *  - users.pin: hashed like a password; set by a manager.
     *
     * Existing till staff get a Staff ID now. Nobody has a PIN until a
     * manager sets one, so everyone keeps signing in as they do today.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_code', 20)->nullable()->unique()->after('email');
            $table->string('pin')->nullable()->after('password');
        });

        $context = app(BranchContext::class);
        $codes = app(StaffCodeService::class);

        User::orderBy('id')->get()
            ->filter(fn (User $user) => $context->runsATill($user))
            ->each(fn (User $user) => $codes->ensureFor($user));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staff_code']);
            $table->dropColumn(['staff_code', 'pin']);
        });
    }
};

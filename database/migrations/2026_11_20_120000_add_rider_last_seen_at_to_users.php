<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a rider last used the rider app — what "logged in" means for
     * rider availability (RiderAssignmentService::loggedInRiderIds()).
     * Replaces reading sessions.user_id, which Laravel fills from whichever
     * guard a request happens to default to, so a rider's own Echo
     * subscription (/broadcasting/auth, default 'web' guard) blanked it a
     * second after their dashboard loaded. See orders.md's rider
     * assignment section.
     *
     * Filled from today's live rider sessions so nobody who counts as
     * available right now drops off on the day of the deploy.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('rider_last_seen_at')->nullable()->after('current_branch_id');
        });

        if (! Schema::hasTable(config('session.table', 'sessions'))) {
            return;
        }

        $riderIds = DB::table(config('permission.table_names.model_has_roles').' as mhr')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'mhr.role_id')
            ->where('roles.name', 'rider')
            ->pluck('mhr.model_id')
            ->unique();

        $activeSince = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        DB::table(config('session.table', 'sessions'))
            ->whereIn('user_id', $riderIds)
            ->where('last_activity', '>=', $activeSince)
            ->selectRaw('user_id, MAX(last_activity) as last_activity')
            ->groupBy('user_id')
            ->get()
            ->each(fn ($row) => DB::table('users')->where('id', $row->user_id)->update([
                'rider_last_seen_at' => Carbon::createFromTimestamp($row->last_activity),
            ]));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rider_last_seen_at');
        });
    }
};

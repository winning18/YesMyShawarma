<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The branch a user last selected — set by BranchContext::setCurrent()
     * whenever the session branch changes (the branch switcher, or
     * ResolveCurrentBranch auto-resolving a single-branch user). Exists
     * because a rider's availability for auto-assignment is decided
     * outside their own request (RiderAssignmentService, triggered by
     * whichever order just reached "ready"), where there's no session to
     * read a branch out of — this is the one place that fact is stored
     * durably enough to query for someone else's session.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_branch_id')->nullable()
                ->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_branch_id');
        });
    }
};

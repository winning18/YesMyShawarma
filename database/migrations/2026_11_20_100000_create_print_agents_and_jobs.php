<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Express POS step 8b: the Express POS Print Agent (a small Windows
     * program on the branch's PC) prints receipts, kitchen tickets and
     * opens the cash drawer with no print dialog (owner, 2026-11-15).
     *
     *  - print_agents: one per branch PC. Connected with a one-time code
     *    shown on the Printers page; it then holds a long secret, of which
     *    only the SHA-256 is kept here. last_seen_at says whether it's on.
     *  - print_jobs: the queue it collects from — what to print, on which
     *    printer, and whether it came out.
     */
    public function up(): void
    {
        Schema::create('print_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('pairing_code_hash', 64)->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10);
            $table->string('status', 10)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('print_agents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables (owner, 2026-11-20; the `tables` module): eat-in guests order
     * in rounds that go to the kitchen straight away, and pay one bill at
     * the end.
     *
     *  - dining_tables: a branch's tables, made by staff as needed (no
     *    fixed set).
     *  - table_tabs: one open bill on a table at a time; settled once, by
     *    cash or MoMo, at a till (shift_id).
     *  - orders.table_tab_id: each round is an ordinary POS order on the
     *    tab, payment pending until the tab is settled.
     *  - print_jobs.table_tab_id: the combined bill, printed by the agent.
     *
     * Yes My Shawarma gets the Tables switch on (CLAUDE.md product rule 7);
     * a client install starts with it off.
     */
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('table_tabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 10)->nullable();
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('total')->nullable(); // pesewas, as settled
            $table->timestamps();

            $table->index(['dining_table_id', 'settled_at']);
            $table->index(['branch_id', 'settled_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('table_tab_id')->nullable()->after('shift_id')->constrained()->nullOnDelete();
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->foreignId('table_tab_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });

        $isClientInstall = DB::table('settings')->where('key', 'client_setup_at')->exists();
        if (DB::table('orders')->exists() && ! $isClientInstall && ! DB::table('settings')->where('key', 'module_tables')->exists()) {
            DB::table('settings')->insert(['key' => 'module_tables', 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('print_jobs', fn (Blueprint $table) => $table->dropConstrainedForeignId('table_tab_id'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('table_tab_id'));
        Schema::dropIfExists('table_tabs');
        Schema::dropIfExists('dining_tables');
        DB::table('settings')->where('key', 'module_tables')->delete();
    }
};

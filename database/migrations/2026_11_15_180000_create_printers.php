<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Express POS step 8a: a branch's receipt and kitchen printers.
     *
     *  - printers: role 'receipt' or 'kitchen'; paper 58 or 80 mm;
     *    connection 'network' (address = IP[:port]) or 'usb' (address =
     *    the Windows printer name, used by the print agent in 8b);
     *    opens_drawer when a cash drawer is cabled to it.
     *  - terminals.receipt_printer_id: the receipt printer a counter uses.
     *
     * Nothing is backfilled: a branch with no printers shows no print
     * buttons and prints nothing, exactly as before.
     */
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('role', 10);
            $table->unsignedTinyInteger('paper_width')->default(80);
            $table->string('connection', 10)->default('network');
            $table->string('address', 120)->nullable();
            $table->boolean('opens_drawer')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::table('terminals', function (Blueprint $table) {
            $table->foreignId('receipt_printer_id')->nullable()->after('name')->constrained('printers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('terminals', fn (Blueprint $table) => $table->dropConstrainedForeignId('receipt_printer_id'));
        Schema::dropIfExists('printers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pharmacy layer (owner-approved 2026-11-27; plan step R5): medicine
     * details on a product, stock kept in batches with expiry dates, which
     * batch each sale came from, and prescriptions. Rules marked for the
     * pilot pharmacist to confirm are in schema.md's "Pharmacy" section.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('generic_name')->nullable()->after('description');
            $table->string('strength', 60)->nullable()->after('generic_name');
            $table->string('dosage_form', 40)->nullable()->after('strength');
            $table->string('fda_number', 60)->nullable()->after('dosage_form');
            // otc | pharmacy | pom | controlled; null = not a medicine.
            $table->string('medicine_class', 20)->nullable()->after('fda_number');
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('received_quantity', 10, 2)->default(0);
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['stock_item_id', 'expiry_date']);
        });

        Schema::create('order_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained()->cascadeOnDelete();
            $table->decimal('units', 10, 2);
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('patient_name');
            $table->string('patient_phone', 30)->nullable();
            $table->string('prescriber_name');
            $table->string('prescriber_facility')->nullable();
            $table->date('issued_on');
            $table->string('prescription_number', 60)->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('approved_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('order_item_batches');
        Schema::dropIfExists('stock_batches');
        Schema::table('menu_items', fn (Blueprint $table) => $table->dropColumn(['generic_name', 'strength', 'dosage_form', 'fda_number', 'medicine_class']));
    }
};

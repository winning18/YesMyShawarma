<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->unsignedInteger('value');
            $table->unsignedBigInteger('min_order_total')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_per_customer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // No rows here at all means "every branch" — this pivot only ever
        // narrows a promotion down, per PromotionService. Deliberately no
        // surrogate id: (promotion_id, branch_id) is the whole row.
        Schema::create('promotion_branch', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_branch');
        Schema::dropIfExists('promotions');
    }
};

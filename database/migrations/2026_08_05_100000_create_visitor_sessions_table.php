<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();
            // Random value handed out as a first-party cookie on the
            // customer site (TrackVisitorSession middleware) — anonymous,
            // no login required, so this is the only identity a visit has
            // until (if ever) it converts.
            $table->string('token', 64)->unique();
            // Null until conversion: the customer site doesn't scope
            // browsing to a branch upfront (branch is resolved from the
            // delivery zone at checkout, per schema.md), so which branch a
            // still-browsing visit belongs to genuinely isn't known yet.
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            // Null until this visit places an order; unique so a session
            // is credited with converting once, not once per order.
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            // created_at = first seen, updated_at = last seen (touched on
            // every request the tracking middleware sees for this token).
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // 0 (Sunday) .. 6 (Saturday) — matches Carbon::dayOfWeek, not an
            // ISO weekday number. starts_at/ends_at are plain local
            // (Africa/Accra) time-of-day, not UTC instants — this is a
            // recurring weekly pattern tied to the branch's own operating
            // rhythm (single-timezone business, per CLAUDE.md), not a point
            // in time, so the usual "datetimes stored UTC" rule doesn't
            // apply here the way it does to orders.placed_at etc.
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['menu_item_id', 'branch_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_schedules');
    }
};

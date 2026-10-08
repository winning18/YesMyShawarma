<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // Only set for type === 'buy_x_get_y_free' — every buy_quantity
            // units of the SAME menu item makes free_quantity of them free
            // (base unit_price_snapshot only; any options on those units
            // are still billed). Stored rather than hardcoded 3/1 so a
            // future "buy 3 get 2" never needs another migration.
            $table->unsignedInteger('buy_quantity')->nullable()->after('value');
            $table->unsignedInteger('free_quantity')->nullable()->after('buy_quantity');

            // Weekday ints (0=Sun..6=Sat, matching Carbon — same convention
            // as branch_working_hours/menu_item_schedules), e.g. [3] for
            // every Wednesday. Null/empty means this promotion never
            // auto-activates by day — unrelated to starts_at/ends_at, which
            // still bound the promotion's own overall campaign window.
            $table->json('recurring_days')->nullable()->after('ends_at');

            // True means this promotion applies with no code at all, the
            // moment recurring_days matches today — and, per the checkout/
            // POS UI, coupon-code entry is hidden entirely on any day an
            // automatic promotion is active, since orders.promotion_id only
            // ever holds one promotion at a time.
            $table->boolean('is_automatic')->default(false)->after('recurring_days');

            // Drives the homepage banner shown on an automatic promo's
            // active days — kept on this same row so the banner and the
            // live discount can never disagree about whether today counts.
            $table->string('banner_headline')->nullable()->after('is_automatic');
            $table->string('banner_image_path')->nullable()->after('banner_headline');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn([
                'buy_quantity', 'free_quantity', 'recurring_days',
                'is_automatic', 'banner_headline', 'banner_image_path',
            ]);
        });
    }
};

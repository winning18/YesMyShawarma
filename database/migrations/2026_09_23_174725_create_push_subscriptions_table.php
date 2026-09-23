<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per subscribed browser/device, not per user — the same
     * staff member logged in on a phone and a tablet gets a push on both.
     * `endpoint` (the browser's own push-service URL, e.g. an
     * fcm.googleapis.com or updates.push.services.mozilla.com URL) is
     * what PushManager treats as that device's identity, so it's the
     * natural unique key — re-subscribing the same browser (a renewed
     * subscription, or the same user logging in again) updates the row in
     * place rather than duplicating it. A plain `string`, not `text` —
     * MySQL can't put a unique index on a TEXT column without a prefix
     * length, and real endpoints are well under 512 characters.
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 512)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};

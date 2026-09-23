<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registers/removes this browser's PushSubscription so
 * NewOrderPushNotifier (realtime.md's "Web Push" section) has somewhere to
 * send to. One row per browser/device, not per user — endpoint is the
 * natural identity (PushSubscription.json's own endpoint URL), so
 * re-subscribing an already-known browser just updates it in place.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:512'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
            ],
        );

        return response()->json(['status' => 'subscribed']);
    }

    /**
     * Scoped to the requesting user too, not endpoint alone — a client
     * only ever has legitimate reason to unsubscribe its own browser, and
     * this keeps someone from unsubscribing a different account's known
     * endpoint by guessing it.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:512'],
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json(['status' => 'unsubscribed']);
    }
}

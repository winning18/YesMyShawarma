<?php

namespace App\Services\Notifications;

use App\Contracts\Notifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real SMS delivery via Arkesel's v2 API (bound in AppServiceProvider once
 * ARKESEL_API_KEY is configured — LogNotifier otherwise).
 *
 * An SMS failure must never take down the order flow it's attached to —
 * same reasoning as SafeBroadcast for broadcasts (realtime.md): whatever
 * triggered this (an order transition, an escalation) has already
 * succeeded by the time this runs, and Arkesel being briefly unreachable
 * is not a reason to turn that into a 500.
 */
class ArkeselNotifier implements Notifier
{
    public function notify(string $phone, string $message, array $context = []): void
    {
        $this->send($phone, $message, $context);
    }

    public function send(string $phone, string $message, array $context = []): bool
    {
        try {
            // Order and stock SMS go through the SendSms queue job, but
            // escalation and broadcasts call this directly from the
            // scheduler/queue — keep each attempt well short of Laravel's
            // 30s default so a stalled Arkesel can't hold a worker.
            $response = Http::connectTimeout(5)->timeout(10)->withHeaders([
                'api-key' => config('services.arkesel.api_key'),
            ])->post(config('services.arkesel.base_url'), [
                'sender' => config('services.arkesel.sender_id'),
                'message' => $message,
                // Arkesel expects "233XXXXXXXXX", no leading "+" —
                // CustomerService::normalizeGhanaPhone() always hands us
                // "+233XXXXXXXXX".
                'recipients' => [ltrim($phone, '+')],
            ]);

            if ($response->failed()) {
                Log::error('Arkesel SMS send failed', [
                    'phone' => $phone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    ...$context,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}

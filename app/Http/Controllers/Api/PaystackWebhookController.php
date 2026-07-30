<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaystackWebhook;
use App\Services\Payments\PaystackClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackClient $client): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        if (! $client->verifiesSignature($rawBody, $signature)) {
            Log::warning('Paystack webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 400);
        }

        $payload = $request->json()->all();

        ProcessPaystackWebhook::dispatch($payload);

        return response('OK', 200);
    }
}

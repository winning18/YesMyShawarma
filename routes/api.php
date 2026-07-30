<?php

use App\Http\Controllers\Api\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

// Paystack retries aggressively and signs every request itself — the
// signature check is the actual gate, not our own throttle. See
// payments.md: "Exempt from rate limiting. Add the route to the throttle
// exclusion list explicitly."
Route::post('/webhooks/paystack', PaystackWebhookController::class)
    ->withoutMiddleware(['throttle:api'])
    ->name('webhooks.paystack');

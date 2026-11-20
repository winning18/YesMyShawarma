<?php

use App\Http\Controllers\Api\NearestBranchController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\PrintAgentController;
use App\Http\Middleware\AuthenticatePrintAgent;
use Illuminate\Support\Facades\Route;

// Paystack retries aggressively and signs every request itself — the
// signature check is the actual gate, not our own throttle. See
// payments.md: "Exempt from rate limiting. Add the route to the throttle
// exclusion list explicitly."
Route::post('/webhooks/paystack', PaystackWebhookController::class)
    ->withoutMiddleware(['throttle:api'])
    ->name('webhooks.paystack');

// Read-only "nearest branch" for the coming mobile app — same
// NearestBranchFinder as the website's /branches/nearest. Selecting the
// branch is the app's own state, not the server's.
Route::get('/branches/nearest', NearestBranchController::class)->name('api.branches.nearest');

// The Express POS Print Agent (step 8b). It checks in every couple of
// seconds, so it's exempt from the general API throttle; connecting with a
// one-time code has its own tight limit.
Route::post('/print-agent/connect', [PrintAgentController::class, 'connect'])
    ->middleware('throttle:10,1')
    ->name('api.print-agent.connect');
Route::middleware(AuthenticatePrintAgent::class)->withoutMiddleware(['throttle:api'])->group(function () {
    Route::get('/print-agent/jobs', [PrintAgentController::class, 'jobs'])->name('api.print-agent.jobs');
    Route::post('/print-agent/jobs/{job}', [PrintAgentController::class, 'report'])->whereNumber('job')->name('api.print-agent.report');
});

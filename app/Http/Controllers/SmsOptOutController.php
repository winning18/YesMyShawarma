<?php

namespace App\Http\Controllers;

use App\Services\Sms\SmsBroadcastService;
use Illuminate\Http\Response;

/**
 * The /stop/{code} link at the end of every broadcast SMS. GET only shows
 * a confirm button; the opt-out itself is the POST — SMS apps and link
 * previews fetch links on their own, and a bare GET must never opt
 * someone out without them tapping anything.
 */
class SmsOptOutController extends Controller
{
    public function __construct(private readonly SmsBroadcastService $broadcasts) {}

    public function show(string $code): Response
    {
        $recipient = $this->broadcasts->recipientForCode($code) ?? abort(404);

        return response()
            ->view('sms-opt-out', ['code' => $code, 'done' => $recipient->customer->marketing_opted_out_at !== null])
            ->header('X-Robots-Tag', 'noindex');
    }

    public function store(string $code): Response
    {
        $recipient = $this->broadcasts->recipientForCode($code) ?? abort(404);

        $this->broadcasts->optOut($recipient);

        return response()
            ->view('sms-opt-out', ['code' => $code, 'done' => true])
            ->header('X-Robots-Tag', 'noindex');
    }
}

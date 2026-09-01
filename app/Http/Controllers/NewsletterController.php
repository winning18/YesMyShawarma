<?php

namespace App\Http\Controllers;

use App\Services\Newsletter\NewsletterService;
use App\Services\Spam\HoneypotGuard;
use App\Services\Spam\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(
        Request $request,
        NewsletterService $newsletter,
        HoneypotGuard $honeypot,
        TurnstileVerifier $turnstile,
    ): RedirectResponse {
        // See ContactController::submit() — a caught submission looks
        // identical to a real one to the caller, deliberately.
        if ($honeypot->isSpam($request)) {
            return back()->with('status', __("You're subscribed — thanks for signing up!"));
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        if (! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withErrors(['email' => __('Please try submitting the form again.')])->withInput();
        }

        $newsletter->subscribe($validated['email']);

        return back()->with('status', __("You're subscribed — thanks for signing up!"));
    }
}

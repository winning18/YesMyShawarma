<?php

namespace App\Http\Controllers;

use App\Services\Newsletter\NewsletterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request, NewsletterService $newsletter): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $newsletter->subscribe($validated['email']);

        return back()->with('status', __("You're subscribed — thanks for signing up!"));
    }
}

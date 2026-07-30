<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Contact\ContactMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        return view('contact', [
            'branches' => Branch::where('is_active', true)->get(),
        ]);
    }

    public function submit(Request $request, ContactMessageService $messages): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $messages->send(
            $validated['name'],
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
            $validated['message'],
        );

        return back()->with('status', __("Thanks — we've received your message and will get back to you soon."));
    }
}

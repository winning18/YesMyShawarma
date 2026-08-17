<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcePasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.force-password-change');
    }

    /**
     * current_password validates against the hash already on the user row
     * — for a just-created account that's the one-time password they were
     * handed, same rule PasswordController's ordinary change-password flow
     * uses, no separate mechanism needed.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // must_change_password isn't in User's #[Fillable(...)] on purpose
        // (see UserManagementService) — forceFill is deliberate here, not
        // a bypass of untrusted input.
        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        return redirect()->intended(route('dashboard'));
    }
}

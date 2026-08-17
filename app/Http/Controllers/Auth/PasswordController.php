<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // forceFill, not update() — must_change_password isn't in User's
        // #[Fillable(...)] (see UserManagementService), and belongs here
        // regardless of which form set the password: a legitimate
        // self-service change satisfies the "must change" requirement even
        // if it didn't go through ForcePasswordChangeController.
        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        return back()->with('status', 'password-updated');
    }
}

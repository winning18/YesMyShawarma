<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customers\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.register');
    }

    /**
     * A guest row may already exist for this phone from a past order (see
     * CLAUDE.md's identity model) — registering just sets a password on it
     * rather than failing as a duplicate, unless a password is already set,
     * in which case this phone genuinely already has an account.
     */
    public function store(Request $request, CustomerService $customers): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $phone = $customers->normalizeGhanaPhone($validated['phone']);

        $existing = Customer::where('phone', $phone)->first();

        if ($existing && $existing->password) {
            return back()->withErrors(['phone' => 'This phone number is already registered — please log in instead.']);
        }

        $customer = $customers->findOrCreateByPhone($phone, $validated['name']);
        $customer->update(['password' => $validated['password']]);

        Auth::guard('customer')->login($customer);

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}

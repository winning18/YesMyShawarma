<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Exceptions\CustomerAccountException;
use App\Exceptions\SmsCodeException;
use App\Http\Controllers\Controller;
use App\Services\Customers\CustomerAccountService;
use App\Services\Customers\CustomerService;
use App\Services\Customers\SmsCodeService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Customer "Forgot password" (owner, 2026-11-11): a code texted to the
 * account's phone, then a new password. Customers had no way back in
 * before this. A suspended account still can't log in afterwards
 * (EnsureAccountNotSuspended signs it straight out).
 */
class PasswordResetController extends Controller
{
    private const RESET_PHONE = 'customer_reset_phone';

    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function create(): View
    {
        return view('customer.auth.forgot-password');
    }

    public function sendCode(Request $request, CustomerService $customers, SmsCodeService $codes): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($customers) {
                if (! $customers->isValidGhanaPhone((string) $value)) {
                    $fail(__('Enter a valid Ghana phone number.'));
                }
            }],
        ]);

        $phone = $customers->normalizeGhanaPhone($validated['phone']);

        if (! $this->accounts->hasAccount($phone)) {
            return back()->withErrors(['phone' => CustomerAccountException::noAccount()->getMessage()])->withInput();
        }

        try {
            $codes->send($phone, SmsCodeService::PASSWORD_RESET);
        } catch (SmsCodeException $e) {
            return back()->withErrors(['phone' => $e->getMessage()])->withInput();
        }

        $request->session()->put(self::RESET_PHONE, $phone);

        return redirect()->route('customer.password.reset')->with('status', __('We texted a code to :phone.', ['phone' => $phone]));
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get(self::RESET_PHONE);

        if (! $phone) {
            return redirect()->route('customer.password.request');
        }

        return view('customer.auth.reset-password', ['phone' => $phone]);
    }

    public function update(Request $request, SmsCodeService $codes): RedirectResponse
    {
        $phone = $request->session()->get(self::RESET_PHONE);

        if (! $phone) {
            return redirect()->route('customer.password.request');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        try {
            $codes->verify($phone, SmsCodeService::PASSWORD_RESET, $validated['code']);
            $customer = $this->accounts->resetPassword($phone, $validated['password']);
        } catch (SmsCodeException|CustomerAccountException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget(self::RESET_PHONE);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('customer.account.show')->with('status', __('Your password was changed and you\'re logged in.'));
    }
}

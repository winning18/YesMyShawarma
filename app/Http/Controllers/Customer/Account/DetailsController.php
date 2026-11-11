<?php

namespace App\Http\Controllers\Customer\Account;

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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * My account → Details: name, email, phone (via a texted code), password
 * and SMS offers (owner, 2026-11-11).
 */
class DetailsController extends Controller
{
    private const PENDING_PHONE = 'account_pending_phone';

    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function show(Request $request): View
    {
        return view('customer.account.details', [
            'customer' => Auth::guard('customer')->user(),
            'pendingPhone' => $request->session()->get(self::PENDING_PHONE),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
        ]);

        $this->accounts->updateDetails($customer, $validated['name'], $validated['email'] ?? null);

        return back()->with('status', __('Your details were saved.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password:customer'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $this->accounts->changePassword(Auth::guard('customer')->user(), $validated['password']);

        return back()->with('status', __('Your password was changed.'));
    }

    public function sendPhoneCode(Request $request, CustomerService $customers, SmsCodeService $codes): RedirectResponse
    {
        $validated = $request->validateWithBag('phone', [
            'phone' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($customers) {
                if (! $customers->isValidGhanaPhone((string) $value)) {
                    $fail(__('Enter a valid Ghana phone number.'));
                }
            }],
        ]);

        $customer = Auth::guard('customer')->user();
        $phone = $customers->normalizeGhanaPhone($validated['phone']);

        try {
            $this->accounts->assertPhoneAvailable($customer, $phone);
            $codes->send($phone, SmsCodeService::PHONE_CHANGE, $customer->id);
        } catch (CustomerAccountException|SmsCodeException $e) {
            return back()->withErrors(['phone' => $e->getMessage()], 'phone')->withInput();
        }

        $request->session()->put(self::PENDING_PHONE, $phone);

        return back()->with('status', __('We texted a code to :phone.', ['phone' => $phone]));
    }

    public function confirmPhone(Request $request, SmsCodeService $codes): RedirectResponse
    {
        $validated = $request->validateWithBag('phone', ['code' => ['required', 'string', 'max:10']]);

        $customer = Auth::guard('customer')->user();
        $phone = $request->session()->get(self::PENDING_PHONE);

        if (! $phone) {
            return back()->withErrors(['code' => __('Request a code first.')], 'phone');
        }

        try {
            $codes->verify($phone, SmsCodeService::PHONE_CHANGE, $validated['code'], $customer->id);
            $this->accounts->changePhone($customer, $phone);
        } catch (SmsCodeException|CustomerAccountException $e) {
            return back()->withErrors(['code' => $e->getMessage()], 'phone');
        }

        $request->session()->forget(self::PENDING_PHONE);

        return back()->with('status', __('Your phone number is now :phone. Use it to log in from now on.', ['phone' => $phone]));
    }

    public function cancelPhone(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_PHONE);

        return back();
    }

    public function updateSmsOffers(Request $request): RedirectResponse
    {
        $wanted = $request->boolean('sms_offers');

        $this->accounts->setSmsOffers(Auth::guard('customer')->user(), $wanted);

        return back()->with('status', $wanted ? __('You\'ll get our SMS offers.') : __('You won\'t get SMS offers any more. Order updates still come by SMS.'));
    }
}

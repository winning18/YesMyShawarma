<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountSuspensionException;
use App\Models\Customer;
use App\Models\User;
use App\Services\Accounts\AccountSuspensionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Suspend / lift for staff accounts (Users page) and customers (Customers
 * list). Who may act on whom is AccountSuspensionService's call — it
 * refuses anything outside the rules, so these stay thin.
 */
class AccountSuspensionController extends Controller
{
    public function __construct(private readonly AccountSuspensionService $suspensions) {}

    public function suspendUser(Request $request, User $user): RedirectResponse
    {
        return $this->act(fn () => $this->suspensions->suspend($user, $request->user(), $this->reason($request)),
            __(':name is suspended and has been signed out.', ['name' => $user->name]));
    }

    public function liftUser(Request $request, User $user): RedirectResponse
    {
        return $this->act(fn () => $this->suspensions->lift($user, $request->user()),
            __(':name can log in again.', ['name' => $user->name]));
    }

    public function suspendCustomer(Request $request, Customer $customer): RedirectResponse
    {
        return $this->act(fn () => $this->suspensions->suspend($customer, $request->user(), $this->reason($request)),
            __(':who is suspended.', ['who' => $customer->name ?? $customer->phone]));
    }

    public function liftCustomer(Request $request, Customer $customer): RedirectResponse
    {
        return $this->act(fn () => $this->suspensions->lift($customer, $request->user()),
            __(':who can log in and order again.', ['who' => $customer->name ?? $customer->phone]));
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
    }

    private function act(callable $action, string $done): RedirectResponse
    {
        try {
            $action();
        } catch (AccountSuspensionException $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('status', $done);
    }
}

<?php

namespace App\Http\Controllers\Customer\Account;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Services\Customers\CustomerBranchSelection;
use App\Services\Customers\ReorderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * My account → Orders: every past order with Track and Order again.
 */
class OrdersController extends Controller
{
    public function index(): View
    {
        return view('customer.account.orders', [
            'orders' => Auth::guard('customer')->user()->orders()
                ->with(['items' => fn ($query) => $query->orderBy('id'), 'branch:id,name'])
                ->whereNotNull('placed_at')
                ->latest('placed_at')
                ->paginate(15),
        ]);
    }

    /**
     * At the branch they're ordering from now; failing that the order's
     * own branch (selected, so the menu and cart agree).
     */
    public function reorder(Order $order, ReorderService $reorders, CustomerBranchSelection $selection): RedirectResponse
    {
        abort_unless($order->customer_id === Auth::guard('customer')->id(), 404);

        $branch = $selection->current();

        if (! $branch || ! $branch->is_active) {
            $branch = Branch::where('is_active', true)->find($order->branch_id) ?? Branch::where('is_active', true)->orderBy('id')->first();
            abort_unless($branch, 404);
            $selection->set($branch->id);
        }

        $result = $reorders->reorder($order, $branch);

        if ($result['added'] === 0) {
            return back()->with('status', __('None of these items can be ordered at :branch right now.', ['branch' => $branch->name]));
        }

        $message = trans_choice(':count item from your order was added to your cart.|:count items from your order were added to your cart.', $result['added'], ['count' => $result['added']]);

        if ($result['skipped'] !== []) {
            $message .= ' '.__('Not available at :branch: :items.', ['branch' => $branch->name, 'items' => implode(', ', $result['skipped'])]);
        }

        return redirect()->route('cart.show')->with('status', $message);
    }
}

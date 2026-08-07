<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerManagementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('customers.view');

        $search = $request->string('search')->trim()->toString();

        return view('dashboard.customers.index', [
            'customers' => $this->baseQuery($search)->paginate(20)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('customers.view');

        $search = $request->string('search')->trim()->toString();
        $customers = $this->baseQuery($search)->get();

        return response()->streamDownload(function () use ($customers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Email', 'Orders', 'Lifetime value (GHS)', 'Last order', 'Location']);

            foreach ($customers as $customer) {
                fputcsv($out, [
                    $customer->name,
                    $customer->phone,
                    $customer->email,
                    $customer->orders_count,
                    number_format(($customer->lifetime_value ?? 0) / 100, 2),
                    $customer->last_order_at ? Carbon::parse($customer->last_order_at)->format('Y-m-d') : '',
                    $customer->location ?? '',
                ]);
            }

            fclose($out);
        }, 'customers.csv');
    }

    private function baseQuery(string $search): Builder
    {
        // The area name off their most recent delivery order — pickup
        // orders carry no address at all, and which named area a delivery
        // customer used is what actually says something about where
        // orders are coming from (delivery_areas is a staff-defined label,
        // per schema.md, not raw geocoding). customer_addresses is not
        // used here: saving to it is a distinct, opt-in action a customer
        // may never take (see OrderCreationService), so it's far sparser
        // than what's already sitting on every delivery order.
        $latestDeliveryArea = Order::query()
            ->selectRaw("delivery_address_snapshot->>'$.area_name'")
            ->whereColumn('orders.customer_id', 'customers.id')
            ->where('fulfilment_type', 'delivery')
            ->whereNotNull('delivery_address_snapshot')
            ->orderByDesc('placed_at')
            ->limit(1);

        $query = Customer::query()
            ->withCount('orders')
            ->withSum(['orders as lifetime_value' => fn ($q) => $q->whereNotIn('status', Order::NON_REVENUE_STATUSES)], 'total')
            ->withMax('orders as last_order_at', 'placed_at')
            ->addSelect(['location' => $latestDeliveryArea])
            ->orderByDesc('lifetime_value');

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }

        return $query;
    }
}

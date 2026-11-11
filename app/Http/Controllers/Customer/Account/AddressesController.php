<?php

namespace App\Http\Controllers\Customer\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\DeliveryArea;
use App\Services\Customers\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * My account → Addresses. Only ever the logged-in customer's own; anyone
 * else's address is a 404, never a 403, so ids can't be probed.
 */
class AddressesController extends Controller
{
    public function __construct(private readonly CustomerAddressService $addresses) {}

    public function index(): View
    {
        return view('customer.account.addresses', [
            'addresses' => Auth::guard('customer')->user()->addresses()->with('deliveryArea:id,name')->orderByDesc('is_default')->latest('id')->get(),
            'deliveryAreas' => DeliveryArea::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->addresses->save(Auth::guard('customer')->user(), $this->validated($request));

        return back()->with('status', __('Address saved.'));
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->own($address);

        $this->addresses->save($address->customer, $this->validated($request), $address);

        return back()->with('status', __('Address updated.'));
    }

    public function makeDefault(CustomerAddress $address): RedirectResponse
    {
        $this->own($address);

        $this->addresses->makeDefault($address);

        return back()->with('status', __('Default address changed.'));
    }

    public function destroy(CustomerAddress $address): RedirectResponse
    {
        $this->own($address);

        $this->addresses->delete($address);

        return back()->with('status', __('Address removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'area_id' => ['required'],
            'area_other' => ['required_if:area_id,other', 'nullable', 'string', 'max:100'],
            'ghanapost_code' => ['nullable', 'string', 'max:20'],
            'landmark' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ]);

        $areaId = $validated['area_id'] === 'other' ? null : (int) $validated['area_id'];
        abort_if($areaId !== null && ! DeliveryArea::whereKey($areaId)->exists(), 422);

        return [
            'label' => $validated['label'] ?? null,
            'delivery_area_id' => $areaId,
            'area_other' => $areaId ? null : ($validated['area_other'] ?? null),
            'ghanapost_code' => $validated['ghanapost_code'] ?? null,
            'landmark' => $validated['landmark'],
            'lat' => isset($validated['lat']) ? (float) $validated['lat'] : null,
            'lng' => isset($validated['lng']) ? (float) $validated['lng'] : null,
        ];
    }

    private function own(CustomerAddress $address): void
    {
        abort_unless($address->customer_id === Auth::guard('customer')->id(), 404);
    }
}

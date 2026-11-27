<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Stock → Suppliers (R4, 2026-11-27): shared by every branch. Pharmacies and shops; stock.manage. */
class SupplierController extends Controller
{
    public function index(): View
    {
        Gate::authorize('stock.manage');

        return view('dashboard.inventory.suppliers.index', ['suppliers' => Supplier::withCount('purchaseOrders')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('stock.manage');

        return view('dashboard.inventory.suppliers.form', ['supplier' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('stock.manage');
        $supplier = Supplier::create($this->validated($request));

        return redirect()->route('dashboard.inventory.suppliers.index')->with('status', __(':name added.', ['name' => $supplier->name]));
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('stock.manage');

        return view('dashboard.inventory.suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('stock.manage');
        $supplier->update($this->validated($request));

        return redirect()->route('dashboard.inventory.suppliers.index')->with('status', __(':name saved.', ['name' => $supplier->name]));
    }

    /** @return array<string, ?string> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}

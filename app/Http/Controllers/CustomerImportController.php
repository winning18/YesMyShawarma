<?php

namespace App\Http\Controllers;

use App\Services\Customers\CustomerImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Customers → Import contacts. Same people who send SMS broadcasts
 * (customers.broadcast) — the import exists so broadcasts reach outside
 * leads.
 */
class CustomerImportController extends Controller
{
    public function create(): View
    {
        Gate::authorize('customers.broadcast');

        return view('dashboard.customers.import', [
            'maxRows' => CustomerImportService::MAX_ROWS,
        ]);
    }

    public function store(Request $request, CustomerImportService $imports): RedirectResponse
    {
        Gate::authorize('customers.broadcast');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        return redirect()->route('dashboard.customers.import.create')
            ->with('importResult', $imports->import($request->file('file')->getRealPath()));
    }
}

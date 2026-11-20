<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaxSettingsRequest;
use App\Services\Settings\BusinessProfile;
use App\Services\Tax\TaxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Settings → Tax (Express POS step 9, the `tax` module): which taxes the
 * business charges, and whether its prices include them. Owner only
 * (business.manage), like the Business profile that holds the TIN.
 */
class TaxController extends Controller
{
    public function edit(TaxService $tax, BusinessProfile $profile): View
    {
        Gate::authorize('business.manage');

        return view('dashboard.tax.edit', [
            'config' => $tax->config(),
            'preset' => TaxService::GHANA_PRESET,
            'maxLines' => TaxService::MAX_LINES,
            'tin' => $profile->tin(),
        ]);
    }

    public function update(UpdateTaxSettingsRequest $request, TaxService $tax): RedirectResponse
    {
        Gate::authorize('business.manage');

        $validated = $request->validated();

        $tax->save(
            (bool) $validated['inclusive'],
            (bool) $validated['delivery_taxed'],
            collect($validated['lines'] ?? [])
                ->map(fn (array $line) => ['name' => trim($line['name']), 'rate_basis_points' => (int) round(((float) $line['rate']) * 100)])
                ->all(),
        );

        return back()->with('status', __('Tax settings saved. New orders use them; orders already placed keep their tax.'));
    }
}

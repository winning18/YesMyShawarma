<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Branch;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PromotionManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('promotions.manage');

        return view('dashboard.promotions.index', [
            'promotions' => Promotion::withCount('redemptions')->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('promotions.manage');

        return view('dashboard.promotions.create', [
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        Gate::authorize('promotions.manage');

        $validated = $request->validated();

        $promotion = Promotion::create([
            'code' => $validated['code'],
            'type' => $validated['type'],
            'value' => $validated['type'] === 'fixed'
                ? (int) round($validated['value'] * 100)
                : (int) $validated['value'],
            'min_order_total' => isset($validated['min_order_total']) ? (int) round($validated['min_order_total'] * 100) : null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'max_per_customer' => $validated['max_per_customer'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $promotion->branches()->sync($validated['branch_ids'] ?? []);

        return redirect()->route('dashboard.promotions.index')
            ->with('status', __(':code has been created.', ['code' => $promotion->code]));
    }

    public function edit(Promotion $promotion): View
    {
        Gate::authorize('promotions.manage');

        return view('dashboard.promotions.edit', [
            'promotion' => $promotion->load('branches'),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        Gate::authorize('promotions.manage');

        $validated = $request->validated();

        $promotion->update([
            'code' => $validated['code'],
            'type' => $validated['type'],
            'value' => $validated['type'] === 'fixed'
                ? (int) round($validated['value'] * 100)
                : (int) $validated['value'],
            'min_order_total' => isset($validated['min_order_total']) ? (int) round($validated['min_order_total'] * 100) : null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'max_per_customer' => $validated['max_per_customer'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $promotion->branches()->sync($validated['branch_ids'] ?? []);

        return redirect()->route('dashboard.promotions.edit', $promotion)
            ->with('status', __('Promotion updated.'));
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        Gate::authorize('promotions.manage');

        $promotion->delete();

        return redirect()->route('dashboard.promotions.index')
            ->with('status', __(':code has been removed.', ['code' => $promotion->code]));
    }
}

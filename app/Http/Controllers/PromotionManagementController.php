<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Branch;
use App\Models\Promotion;
use App\Services\Media\ImageUploadService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'value' => $this->valueInPesewas($validated),
            'buy_quantity' => $validated['buy_quantity'] ?? null,
            'free_quantity' => $validated['free_quantity'] ?? null,
            'min_order_total' => isset($validated['min_order_total']) ? Money::toPesewas($validated['min_order_total']) : null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_automatic' => $request->boolean('is_automatic'),
            'recurring_days' => $this->recurringDaysAsInts($validated),
            'banner_headline' => $validated['banner_headline'] ?? null,
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
            'value' => $this->valueInPesewas($validated),
            'buy_quantity' => $validated['buy_quantity'] ?? null,
            'free_quantity' => $validated['free_quantity'] ?? null,
            'min_order_total' => isset($validated['min_order_total']) ? Money::toPesewas($validated['min_order_total']) : null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_automatic' => $request->boolean('is_automatic'),
            'recurring_days' => $this->recurringDaysAsInts($validated),
            'banner_headline' => $validated['banner_headline'] ?? null,
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'max_per_customer' => $validated['max_per_customer'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $promotion->branches()->sync($validated['branch_ids'] ?? []);

        return redirect()->route('dashboard.promotions.edit', $promotion)
            ->with('status', __('Promotion updated.'));
    }

    public function updateBannerImage(Request $request, Promotion $promotion, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('promotions.manage');

        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $promotion->update([
            'banner_image_path' => $images->store('promotion-banner', $promotion->id, $request->file('image'), $promotion->banner_image_path),
        ]);

        return back()->with('status', __('Banner image updated.'));
    }

    public function destroyBannerImage(Promotion $promotion, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('promotions.manage');

        $images->delete($promotion->banner_image_path);
        $promotion->update(['banner_image_path' => null]);

        return back()->with('status', __('Banner image removed.'));
    }

    /**
     * value stays meaningless (and unused) for type buy_x_get_y_free —
     * buy_quantity/free_quantity drive that calculation instead — but the
     * column itself is NOT NULL, so it's stored as 0 rather than left to
     * whatever the request happened to submit.
     */
    private function valueInPesewas(array $validated): int
    {
        return match ($validated['type']) {
            'fixed' => Money::toPesewas($validated['value']),
            'buy_x_get_y_free' => 0,
            default => (int) $validated['value'],
        };
    }

    /**
     * $request->validate()'s 'integer' rule validates the submitted
     * checkbox values but never casts them — left as-is, this column
     * would store ["4"] instead of [4], and PromotionService::
     * findActiveAutomatic()'s own strict in_array($today, ..., true)
     * check (int vs string) would then never match any day at all,
     * regardless of what's actually configured.
     *
     * @return ?list<int>
     */
    private function recurringDaysAsInts(array $validated): ?array
    {
        return isset($validated['recurring_days']) ? array_map('intval', $validated['recurring_days']) : null;
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        Gate::authorize('promotions.manage');

        $promotion->delete();

        return redirect()->route('dashboard.promotions.index')
            ->with('status', __(':code has been removed.', ['code' => $promotion->code]));
    }
}

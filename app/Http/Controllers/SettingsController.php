<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * settings.manage (owner/manager/general_manager) — business-wide
 * configuration, starting with the order reference prefixes
 * OrderCreationService reads. Deliberately a single page for now rather
 * than per-feature settings sub-pages; split it out if/when this grows
 * enough to need its own navigation.
 */
class SettingsController extends Controller
{
    public function index(SettingsService $settings): View
    {
        Gate::authorize('settings.manage');

        return view('dashboard.settings.index', [
            'posPrefix' => $settings->get(SettingsService::ORDER_REFERENCE_PREFIX_POS, 'YMGS-POS'),
            'webPrefix' => $settings->get(SettingsService::ORDER_REFERENCE_PREFIX_WEB, 'YMGS-WEB'),
        ]);
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $validated = $request->validated();

        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_POS, strtoupper($validated['order_reference_prefix_pos']));
        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_WEB, strtoupper($validated['order_reference_prefix_web']));

        return back()->with('status', __('Settings updated.'));
    }
}

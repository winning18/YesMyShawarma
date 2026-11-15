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
            'posPrefix' => $settings->prefix(SettingsService::ORDER_REFERENCE_PREFIX_POS),
            'webPrefix' => $settings->prefix(SettingsService::ORDER_REFERENCE_PREFIX_WEB),
            'boltFoodPrefix' => $settings->prefix(SettingsService::ORDER_REFERENCE_PREFIX_BOLT_FOOD),
            'whatsappPrefix' => $settings->prefix(SettingsService::ORDER_REFERENCE_PREFIX_WHATSAPP),
            'freebiePrefix' => $settings->prefix(SettingsService::ORDER_REFERENCE_PREFIX_FREEBIE),
            'whatsappReward' => number_format(((int) $settings->get(SettingsService::WHATSAPP_REWARD_AMOUNT, '1000')) / 100, 2, '.', ''),
        ]);
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $validated = $request->validated();

        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_POS, strtoupper($validated['order_reference_prefix_pos']));
        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_WEB, strtoupper($validated['order_reference_prefix_web']));
        // A switched-off module's fields aren't on the form; theirs stay as they were.
        foreach ([
            'order_reference_prefix_bolt_food' => SettingsService::ORDER_REFERENCE_PREFIX_BOLT_FOOD,
            'order_reference_prefix_whatsapp' => SettingsService::ORDER_REFERENCE_PREFIX_WHATSAPP,
            'order_reference_prefix_freebie' => SettingsService::ORDER_REFERENCE_PREFIX_FREEBIE,
        ] as $field => $key) {
            if (isset($validated[$field])) {
                $settings->set($key, strtoupper($validated[$field]));
            }
        }
        if (isset($validated['whatsapp_reward'])) {
            $settings->set(SettingsService::WHATSAPP_REWARD_AMOUNT, (string) (int) round(((float) $validated['whatsapp_reward']) * 100));
        }

        return back()->with('status', __('Settings updated.'));
    }
}

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
            'boltFoodPrefix' => $settings->get(SettingsService::ORDER_REFERENCE_PREFIX_BOLT_FOOD, 'YMGS-BOLT'),
            'whatsappPrefix' => $settings->get(SettingsService::ORDER_REFERENCE_PREFIX_WHATSAPP, 'YMGS-WA'),
            'freebiePrefix' => $settings->get(SettingsService::ORDER_REFERENCE_PREFIX_FREEBIE, 'YMGS-FREE'),
            'whatsappReward' => number_format(((int) $settings->get(SettingsService::WHATSAPP_REWARD_AMOUNT, '1000')) / 100, 2, '.', ''),
            'paystackEnabled' => $settings->getBool(SettingsService::PAYSTACK_ENABLED),
        ]);
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $validated = $request->validated();

        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_POS, strtoupper($validated['order_reference_prefix_pos']));
        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_WEB, strtoupper($validated['order_reference_prefix_web']));
        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_BOLT_FOOD, strtoupper($validated['order_reference_prefix_bolt_food']));
        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_WHATSAPP, strtoupper($validated['order_reference_prefix_whatsapp']));
        $settings->set(SettingsService::ORDER_REFERENCE_PREFIX_FREEBIE, strtoupper($validated['order_reference_prefix_freebie']));
        $settings->set(SettingsService::WHATSAPP_REWARD_AMOUNT, (string) (int) round(((float) $validated['whatsapp_reward']) * 100));
        $settings->setBool(SettingsService::PAYSTACK_ENABLED, $request->boolean('paystack_enabled'));

        return back()->with('status', __('Settings updated.'));
    }
}

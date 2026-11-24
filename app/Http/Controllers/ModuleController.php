<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateModulesRequest;
use App\Services\Modules\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Settings → Modules: switch each module on or off (ModuleService). The
 * business owner only (business.manage, owner decision 2026-11-15).
 */
class ModuleController extends Controller
{
    public function edit(ModuleService $modules): View
    {
        Gate::authorize('business.manage');

        return view('dashboard.modules.edit', [
            'modules' => collect(ModuleService::MODULES)->map(fn (array $config, string $key) => [
                ...$config,
                'key' => $key,
                'on' => $modules->switchedOn($key),
                'missingSetup' => $modules->switchedOn($key) ? $modules->missingSetup($key) : null,
                'requiresLabels' => array_map(fn (string $required) => ModuleService::MODULES[$required]['label'], $config['requires']),
                'blockedBy' => collect($config['requires'])->reject(fn (string $required) => $modules->enabled($required))
                    ->map(fn (string $required) => ModuleService::MODULES[$required]['label'])->values()->all(),
            ]),
        ]);
    }

    public function update(UpdateModulesRequest $request, ModuleService $modules): RedirectResponse
    {
        Gate::authorize('business.manage');

        $modules->update(collect(ModuleService::MODULES)->keys()
            ->mapWithKeys(fn (string $module) => [$module => $request->boolean('modules.'.$module)])
            ->all());

        return back()->with('status', __('Modules saved.'));
    }
}

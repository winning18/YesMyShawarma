<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOptionGroupRequest;
use App\Http\Requests\StoreOptionRequest;
use App\Http\Requests\UpdateOptionGroupRequest;
use App\Http\Requests\UpdateOptionRequest;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Option groups (e.g. "Extras") are shared, reusable across menu items via
 * menu_item_option_group — not owned by any single item. No delete action
 * here deliberately: option_groups has no is_active column (schema.md) and
 * a hard delete would cascade-detach it from every item still using it.
 * If a group is no longer wanted, unassign it from items via each item's
 * edit form instead.
 */
class OptionGroupManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.option-groups.index', [
            'optionGroups' => OptionGroup::withCount('options')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.option-groups.create');
    }

    public function store(StoreOptionGroupRequest $request): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        $group = OptionGroup::create([
            ...$validated,
            'is_required' => $request->boolean('is_required'),
        ]);

        return redirect()->route('dashboard.option-groups.edit', $group)
            ->with('status', __(':name has been created.', ['name' => $group->name]));
    }

    public function edit(OptionGroup $optionGroup): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.option-groups.edit', [
            'optionGroup' => $optionGroup,
            'options' => $optionGroup->options()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateOptionGroupRequest $request, OptionGroup $optionGroup): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        $optionGroup->update([
            ...$validated,
            'is_required' => $request->boolean('is_required'),
        ]);

        return redirect()->route('dashboard.option-groups.edit', $optionGroup)
            ->with('status', __('Option group updated.'));
    }

    public function storeOption(StoreOptionRequest $request, OptionGroup $optionGroup): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        $optionGroup->options()->create([
            'name' => $validated['name'],
            'price_delta' => (int) round($validated['price_delta'] * 100),
        ]);

        return redirect()->route('dashboard.option-groups.edit', $optionGroup)
            ->with('status', __(':name added.', ['name' => $validated['name']]));
    }

    public function updateOption(UpdateOptionRequest $request, OptionGroup $optionGroup, Option $option): RedirectResponse
    {
        Gate::authorize('menu.edit_content');
        abort_unless($option->option_group_id === $optionGroup->id, 404);

        $validated = $request->validated();

        $option->update([
            'name' => $validated['name'],
            'price_delta' => (int) round($validated['price_delta'] * 100),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard.option-groups.edit', $optionGroup)
            ->with('status', __('Option updated.'));
    }

    public function destroyOption(OptionGroup $optionGroup, Option $option): RedirectResponse
    {
        Gate::authorize('menu.edit_content');
        abort_unless($option->option_group_id === $optionGroup->id, 404);

        $option->delete();

        return redirect()->route('dashboard.option-groups.edit', $optionGroup)
            ->with('status', __(':name removed.', ['name' => $option->name]));
    }
}

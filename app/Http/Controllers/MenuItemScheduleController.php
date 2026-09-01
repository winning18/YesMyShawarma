<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Branches\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A menu item with no schedule rows is left entirely to the manual
 * availability toggle (Item availability page) — the timetable is
 * opt-in per item, per branch. Once a schedule exists,
 * ApplyMenuItemSchedules (scheduled every 5 minutes) takes over
 * branch_menu_item.is_available for that item at that branch; staff can
 * still toggle it manually mid-window, but the next run reasserts the
 * schedule. Same branch-resolution requirement as the rest of Menu
 * Editor — see MenuItemManagementController's docblock.
 */
class MenuItemScheduleController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const DAY_NAMES = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

    public function index(BranchContext $context): View|RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $branch = $context->branch();

        if (! $branch) {
            return redirect()->guest(route('branches.select'))
                ->with('status', __('Select a branch to manage the timetable.'));
        }

        $categories = Category::orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Category $category) => [
                'category' => $category,
                'items' => MenuItem::where('category_id', $category->id)
                    ->orderBy('sort_order')->orderBy('name')
                    ->get()
                    ->map(fn (MenuItem $item) => [
                        'item' => $item,
                        'schedules' => $item->schedules()->where('branch_id', $branch->id)->orderBy('day_of_week')->get(),
                    ]),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();

        return view('dashboard.menu-items.timetable', [
            'branch' => $branch,
            'categories' => $categories,
            'dayNames' => self::DAY_NAMES,
        ]);
    }

    public function update(Request $request, MenuItem $menuItem, BranchContext $context): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $branch = $context->branch();
        abort_unless($branch, 422, 'No branch selected.');

        $validated = $request->validate([
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:0,6'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ]);

        $menuItem->schedules()->where('branch_id', $branch->id)->delete();

        foreach (array_unique($validated['days']) as $day) {
            $menuItem->schedules()->create([
                'branch_id' => $branch->id,
                'day_of_week' => $day,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
            ]);
        }

        return back()->with('status', __(':name timetable set at :branch.', ['name' => $menuItem->name, 'branch' => $branch->name]));
    }

    public function destroy(MenuItem $menuItem, BranchContext $context): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $branch = $context->branch();
        abort_unless($branch, 422, 'No branch selected.');

        $menuItem->schedules()->where('branch_id', $branch->id)->delete();

        return back()->with('status', __(':name timetable cleared at :branch. Back to manual availability.', [
            'name' => $menuItem->name,
            'branch' => $branch->name,
        ]));
    }
}

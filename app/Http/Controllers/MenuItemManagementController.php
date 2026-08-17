<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuItemComponentRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemComponent;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Services\Branches\BranchContext;
use App\Services\Media\ImageUploadService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Menu item content (name/description/price/category/photo) is global —
 * shared across every branch. Availability and price overrides are per
 * branch (the branch_menu_item pivot), so this index always operates on
 * the staff member's currently resolved branch, same reasoning as POS:
 * redirect()->guest() to branch selection when none is resolved (owner's
 * cross-branch default), landing back here once picked.
 */
class MenuItemManagementController extends Controller
{
    public function index(BranchContext $context): View|RedirectResponse
    {
        $this->authorizeAny(['menu.toggle_availability', 'menu.edit_content']);

        $branch = $context->branch();

        if (! $branch) {
            return redirect()->guest(route('branches.select'))
                ->with('status', __('Select a branch to manage the menu.'));
        }

        return view('dashboard.menu-items.index', [
            'branch' => $branch,
            'categories' => $this->categoriesWithItems($branch),
            'canToggleAvailability' => Gate::allows('menu.toggle_availability'),
            'canEditContent' => Gate::allows('menu.edit_content'),
        ]);
    }

    /**
     * A staff-focused, minimal counterpart to index() — name and the
     * availability toggle only, none of the content/price/photo editing
     * a plain-staff account can't use anyway. Same branch-resolution
     * requirement and redirect-back-here behaviour as index().
     */
    public function availability(BranchContext $context): View|RedirectResponse
    {
        Gate::authorize('menu.toggle_availability');

        $branch = $context->branch();

        if (! $branch) {
            return redirect()->guest(route('branches.select'))
                ->with('status', __('Select a branch to manage item availability.'));
        }

        return view('dashboard.menu-items.availability', [
            'branch' => $branch,
            'categories' => $this->categoriesWithItems($branch),
        ]);
    }

    /**
     * @return Collection<int, array{category: Category, items: Collection}>
     */
    private function categoriesWithItems(Branch $branch): Collection
    {
        return Category::orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Category $category) => [
                'category' => $category,
                'items' => MenuItem::where('category_id', $category->id)
                    ->orderBy('sort_order')->orderBy('name')
                    ->get()
                    ->map(fn (MenuItem $item) => [
                        'item' => $item,
                        'pivot' => $item->branches()->where('branches.id', $branch->id)->first()?->pivot,
                    ]),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }

    public function create(): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.menu-items.create', [
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'optionGroups' => OptionGroup::orderBy('name')->get(),
        ]);
    }

    public function store(StoreMenuItemRequest $request, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        $item = DB::transaction(function () use ($validated, $request) {
            $item = MenuItem::create([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                'description' => $validated['description'] ?? null,
                'base_price' => Money::toPesewas($validated['base_price']),
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active'),
            ]);

            // New items are sellable everywhere by default, same as
            // MenuSeeder attaches every item to every branch — a manager
            // adding an item expects it live at their branch immediately,
            // not silently unavailable until someone else opts it in.
            $item->branches()->attach(Branch::pluck('id'), ['is_available' => true]);

            $item->optionGroups()->sync($this->optionGroupSyncData($validated['option_group_ids'] ?? []));

            return $item;
        });

        // Stored on disk only after the transaction commits — a rolled-back
        // create() must never leave an orphaned file behind. The item's id
        // (needed as the stored filename) only exists once that's done.
        if ($request->hasFile('image')) {
            $item->update(['image_path' => $images->store('menu-items', $item->id, $request->file('image'), null)]);
        }

        return redirect()->route('dashboard.menu-items.edit', $item)
            ->with('status', __(':name has been created.', ['name' => $item->name]));
    }

    public function edit(MenuItem $menuItem): View
    {
        Gate::authorize('menu.edit_content');

        [$previousItem, $nextItem] = $this->siblingItems($menuItem);

        return view('dashboard.menu-items.edit', [
            'menuItem' => $menuItem,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'optionGroups' => OptionGroup::orderBy('name')->get(),
            'assignedOptionGroupIds' => $menuItem->optionGroups()->pluck('option_groups.id')->all(),
            'components' => $menuItem->components()->with(['componentMenuItem', 'componentOption'])->get(),
            'baseItemChoices' => MenuItem::where('id', '!=', $menuItem->id)->orderBy('name')->get(['id', 'name']),
            'modifierChoices' => Option::where('is_active', true)->with('optionGroup')->orderBy('name')->get(),
            'previousItem' => $previousItem,
            'nextItem' => $nextItem,
        ]);
    }

    /**
     * Same category-then-item ordering as the Menu list page (sort_order,
     * then name), flattened across category boundaries — "previous"/
     * "next" on the edit page walks this exact sequence, so paging
     * through every item for a bulk change matches what's seen on the
     * list. Every item regardless of is_active, matching that list too.
     *
     * @return array{0: ?MenuItem, 1: ?MenuItem}
     */
    private function siblingItems(MenuItem $menuItem): array
    {
        $orderedIds = MenuItem::query()
            ->join('categories', 'categories.id', '=', 'menu_items.category_id')
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name')
            ->orderBy('menu_items.sort_order')
            ->orderBy('menu_items.name')
            ->pluck('menu_items.id');

        $position = $orderedIds->search($menuItem->id);

        if ($position === false) {
            return [null, null];
        }

        // Collection::get() on a plain 0-indexed pluck() result is a key
        // (= offset) lookup — an out-of-range key (position - 1 at the
        // first item, or position + 1 past the last) simply returns null,
        // so first/last items correctly get no previous/next with no
        // extra bounds-checking needed.
        $previousId = $orderedIds->get($position - 1);
        $nextId = $orderedIds->get($position + 1);

        return [
            $previousId ? MenuItem::find($previousId) : null,
            $nextId ? MenuItem::find($nextId) : null,
        ];
    }

    public function storeComponent(StoreMenuItemComponentRequest $request, MenuItem $menuItem): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        $menuItem->components()->create([
            'component_type' => $validated['component_type'],
            'component_menu_item_id' => $validated['component_type'] === MenuItemComponent::TYPE_BASE
                ? $validated['component_menu_item_id'] : null,
            'component_option_id' => $validated['component_type'] === MenuItemComponent::TYPE_MODIFIER
                ? $validated['component_option_id'] : null,
            'quantity' => $validated['quantity'],
        ]);

        return redirect()->route('dashboard.menu-items.edit', $menuItem)
            ->with('status', __('Component added.'));
    }

    public function destroyComponent(MenuItem $menuItem, MenuItemComponent $component): RedirectResponse
    {
        Gate::authorize('menu.edit_content');
        abort_unless($component->menu_item_id === $menuItem->id, 404);

        $component->delete();

        return redirect()->route('dashboard.menu-items.edit', $menuItem)
            ->with('status', __('Component removed.'));
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $menuItem) {
            $menuItem->update([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'base_price' => Money::toPesewas($validated['base_price']),
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active'),
            ]);

            $menuItem->optionGroups()->sync($this->optionGroupSyncData($validated['option_group_ids'] ?? []));
        });

        return redirect()->route('dashboard.menu-items.edit', $menuItem)
            ->with('status', __('Menu item updated.'));
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $menuItem->delete();

        return redirect()->route('dashboard.menu-items.index')
            ->with('status', __(':name removed from the menu.', ['name' => $menuItem->name]));
    }

    public function toggleAvailability(MenuItem $menuItem, BranchContext $context): RedirectResponse
    {
        Gate::authorize('menu.toggle_availability');

        $branch = $context->branch();
        abort_unless($branch, 422, 'No branch selected.');

        $pivot = $menuItem->branches()->where('branches.id', $branch->id)->first()?->pivot;
        $available = ! ($pivot?->is_available ?? true);

        $menuItem->branches()->updateExistingPivot($branch->id, ['is_available' => $available]);

        return back()->with('status', __(':name is now :state at :branch.', [
            'name' => $menuItem->name,
            'state' => $available ? __('available') : __('sold out'),
            'branch' => $branch->name,
        ]));
    }

    public function updateImage(Request $request, MenuItem $menuItem, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $menuItem->update([
            'image_path' => $images->store('menu-items', $menuItem->id, $request->file('image'), $menuItem->image_path),
        ]);

        return back()->with('status', __(':item image updated.', ['item' => $menuItem->name]));
    }

    public function destroyImage(MenuItem $menuItem, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $images->delete($menuItem->image_path);
        $menuItem->update(['image_path' => null]);

        return back()->with('status', __(':item image removed.', ['item' => $menuItem->name]));
    }

    /**
     * @param  int[]  $optionGroupIds
     * @return array<int, array{sort_order: int}>
     */
    private function optionGroupSyncData(array $optionGroupIds): array
    {
        $data = [];
        foreach (array_values($optionGroupIds) as $index => $id) {
            $data[$id] = ['sort_order' => $index];
        }

        return $data;
    }

    private function authorizeAny(array $permissions): void
    {
        abort_unless(
            collect($permissions)->contains(fn (string $permission) => Gate::allows($permission)),
            403
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (MenuItem::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

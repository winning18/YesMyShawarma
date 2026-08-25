<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Branch info and branch photos are one feature, not two — a branch's
 * image is managed right alongside its own card on the index, rather than
 * a separate page staff have to know to go find.
 */
class BranchManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('branches.manage');

        return view('dashboard.branches.index', [
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('branches.manage');

        return view('dashboard.branches.create');
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        Gate::authorize('branches.manage');

        $validated = $request->validated();

        $branch = Branch::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
            'is_accepting_orders' => $request->boolean('is_accepting_orders'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard.branches.edit', $branch)
            ->with('status', __(':name has been created.', ['name' => $branch->name]));
    }

    public function edit(Branch $branch): View
    {
        Gate::authorize('branches.manage');

        return view('dashboard.branches.edit', ['branch' => $branch]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        Gate::authorize('branches.manage');

        $validated = $request->validated();

        $branch->update([
            ...$validated,
            'is_accepting_orders' => $request->boolean('is_accepting_orders'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard.branches.edit', $branch)
            ->with('status', __('Branch updated.'));
    }

    /**
     * Quick one-tap kill switch from the index — separate from the full
     * edit form since this is the action staff/owner reach for mid-rush,
     * per orders.md/schema.md's "manual kill switch" framing.
     */
    public function toggleAcceptingOrders(Branch $branch): RedirectResponse
    {
        Gate::authorize('branches.manage');

        $branch->update(['is_accepting_orders' => ! $branch->is_accepting_orders]);

        return back()->with('status', __(':name is now :state.', [
            'name' => $branch->name,
            'state' => $branch->is_accepting_orders ? __('accepting orders') : __('paused'),
        ]));
    }

    public function updateImage(Request $request, Branch $branch, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('branches.manage');

        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $branch->update([
            'image_path' => $images->store('branches', $branch->id, $request->file('image'), $branch->image_path),
        ]);

        return back()->with('status', __(':branch image updated.', ['branch' => $branch->name]));
    }

    public function destroyImage(Branch $branch, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('branches.manage');

        $images->delete($branch->image_path);
        $branch->update(['image_path' => null]);

        return back()->with('status', __(':branch image removed.', ['branch' => $branch->name]));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Branch::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

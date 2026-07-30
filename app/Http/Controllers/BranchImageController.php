<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BranchImageController extends Controller
{
    public function index(): View
    {
        Gate::authorize('branches.manage');

        return view('dashboard.branches.index', [
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Branch $branch, ImageUploadService $images): RedirectResponse
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

    public function destroy(Branch $branch, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('branches.manage');

        $images->delete($branch->image_path);
        $branch->update(['image_path' => null]);

        return back()->with('status', __(':branch image removed.', ['branch' => $branch->name]));
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffMemberRequest;
use App\Http\Requests\UpdateStaffMemberRequest;
use App\Models\StaffMember;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The public "Meet our staff" roster on the About page — gated behind
 * settings.manage (owner/manager/general_manager), same as
 * SettingsController, since it's reached from the Settings area of the
 * sidebar. Unlike branches/
 * categories (is_active only, never hard-deleted), a staff member can be
 * deleted outright — nothing else references staff_members, so there's no
 * data-integrity reason to keep a dead row around, and "someone who left"
 * is a real reason to remove them entirely rather than just hide them.
 */
class StaffMemberManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.manage');

        return view('dashboard.staff-members.index', [
            'staffMembers' => StaffMember::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('settings.manage');

        return view('dashboard.staff-members.create');
    }

    public function store(StoreStaffMemberRequest $request): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $validated = $request->validated();

        $staffMember = StaffMember::create([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard.staff-members.edit', $staffMember)
            ->with('status', __(':name has been added.', ['name' => $staffMember->name]));
    }

    public function edit(StaffMember $staffMember): View
    {
        Gate::authorize('settings.manage');

        return view('dashboard.staff-members.edit', ['staffMember' => $staffMember]);
    }

    public function update(UpdateStaffMemberRequest $request, StaffMember $staffMember): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $validated = $request->validated();

        $staffMember->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard.staff-members.edit', $staffMember)
            ->with('status', __('Staff member updated.'));
    }

    public function updateImage(Request $request, StaffMember $staffMember, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $staffMember->update([
            'photo_path' => $images->store('staff', $staffMember->id, $request->file('image'), $staffMember->photo_path),
        ]);

        return back()->with('status', __(':name photo updated.', ['name' => $staffMember->name]));
    }

    public function destroyImage(StaffMember $staffMember, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $images->delete($staffMember->photo_path);
        $staffMember->update(['photo_path' => null]);

        return back()->with('status', __(':name photo removed.', ['name' => $staffMember->name]));
    }

    public function destroy(StaffMember $staffMember, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $images->delete($staffMember->photo_path);
        $name = $staffMember->name;
        $staffMember->delete();

        return redirect()->route('dashboard.staff-members.index')
            ->with('status', __(':name has been deleted.', ['name' => $name]));
    }
}

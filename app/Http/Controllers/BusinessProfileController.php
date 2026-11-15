<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessProfileRequest;
use App\Models\Branch;
use App\Services\Settings\BusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Settings → Business profile: the business's identity (BusinessProfile).
 * Owner only (business.manage).
 */
class BusinessProfileController extends Controller
{
    public function edit(BusinessProfile $profile): View
    {
        Gate::authorize('business.manage');

        return view('dashboard.business-profile.edit', [
            'profile' => $profile,
            'colours' => $profile->colours(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'mainBranchId' => $profile->mainBranch()?->id,
        ]);
    }

    public function update(UpdateBusinessProfileRequest $request, BusinessProfile $profile): RedirectResponse
    {
        Gate::authorize('business.manage');

        $profile->update(
            $request->validated(),
            $request->file('logo'),
            $request->file('icon'),
            $request->boolean('remove_logo'),
            $request->boolean('remove_icon'),
        );

        return back()->with('status', __('Business profile saved.'));
    }
}

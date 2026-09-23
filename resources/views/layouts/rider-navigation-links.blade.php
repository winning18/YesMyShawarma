{{-- Shared between the desktop sidebar and the mobile drawer in layouts/rider.blade.php. --}}
<x-sidebar-link :href="route('rider.dashboard')" :active="request()->routeIs('rider.dashboard')">
    {{ __('Deliveries') }}
</x-sidebar-link>
<x-sidebar-link :href="route('rider.history')" :active="request()->routeIs('rider.history')">
    {{ __('Past deliveries') }}
</x-sidebar-link>
<x-sidebar-link :href="route('rider.profile.edit')" :active="request()->routeIs('rider.profile.edit')">
    {{ __('Profile') }}
</x-sidebar-link>

{{--
    Only shown once a rider actually holds the role at more than one
    branch — a rider assigned to just one has nothing to switch between,
    same as before this feature existed. Availability now hinges on
    users.current_branch_id (RiderAssignmentService), so a rider working a
    second branch today needs an actual way to say so, not just whichever
    branch ResolveCurrentBranch happened to leave them on last.
--}}
@if (app(\App\Services\Branches\BranchContext::class)->branchIdsFor(auth()->user())->count() > 1)
    <x-sidebar-link :href="route('branches.select')" :active="request()->routeIs('branches.select')">
        {{ __('Switch branch') }}
    </x-sidebar-link>
@endif

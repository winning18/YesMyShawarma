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

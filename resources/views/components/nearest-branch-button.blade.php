@props(['label' => __('Find my nearest branch')])

{{--
    Shared by the branch bar and the Branches page. Location is only asked
    for on a tap (owner decision 2026-11-10) — see findNearestBranch in
    resources/js/customer.js.
--}}
<span x-data="nearestBranchButton(@js(route('branches.nearest')), @js(['denied' => __('We couldn\'t get your location. Please choose a branch below.'), 'failed' => __('Something went wrong. Please choose a branch below.')]))" class="inline-flex flex-col items-center">
    <button type="button" @click="find()" :disabled="locating" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 font-semibold disabled:opacity-60']) }}>
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18s6-5.33 6-10a6 6 0 1 0-12 0c0 4.67 6 10 6 10Zm0-7.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd"/></svg>
        <span x-text="locating ? @js(__('Finding your nearest branch…')) : @js($label)">{{ $label }}</span>
    </button>
    <span x-show="error" x-text="error" x-cloak class="block text-xs text-brand-red-dark mt-1" role="alert"></span>
</span>

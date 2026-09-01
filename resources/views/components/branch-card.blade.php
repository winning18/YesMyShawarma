@props(['branch', 'selected' => false])

{{--
    Shared by branches/index.blade.php and contact.blade.php — a change
    here (design, badge logic, buttons) applies to both at once rather
    than two copies drifting apart. $branch is expected to carry the
    transient is_open_now/next_opening_label/todays_hours_label attributes
    set by BranchesController/ContactController (see WorkingHoursService).
--}}
<div
    x-data="{ showMap: false }"
    {{ $attributes->merge(['class' => 'border rounded-xl overflow-hidden flex flex-col '.($selected ? 'border-2 border-brand-yellow' : 'border-brand-gray-100')]) }}
>
    <x-branch-image :branch="$branch" class="w-full aspect-video" />

    <div class="p-5 flex flex-col flex-1">
        <div class="flex items-center gap-2 mb-1">
            <h2 class="text-lg font-bold">{{ $branch->name }}</h2>
            @if ($selected)
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-black bg-brand-yellow px-2.5 py-1 rounded-full">
                    {{ __('Currently selected') }}
                </span>
            @endif
        </div>
        <p class="text-sm text-brand-gray-500 mb-3">{{ $branch->address }}</p>

        {{--
            Only shown once the branch has at least one approved review —
            same "only show what actually exists" convention as the home
            page hero and the About page staff roster, rather than a
            placeholder/zero-state. $branch->reviews_count/reviews_avg_rating
            are transient attributes set by BranchesController/
            ContactController, same pattern as is_open_now above.
        --}}
        @if (($branch->reviews_count ?? 0) > 0)
            <div class="flex items-center gap-1 text-sm mb-3">
                <span class="text-brand-yellow-dark">★</span>
                <span class="font-semibold">{{ number_format($branch->reviews_avg_rating, 1) }}</span>
                <span class="text-brand-gray-500">({{ __(':count reviews', ['count' => $branch->reviews_count]) }})</span>
            </div>
        @endif

        {{--
            Sourced from the branch's own admin-set weekly schedule
            (WorkingHoursService::todayLabel — the "Working Hours"
            dashboard page), never the flat Branch::opens_at/closes_at
            columns this used to read straight from — that pair is a
            same-every-day seed/fallback value, not what an admin actually
            configures per branch. Omitted entirely (rather than showing a
            guess) when the branch's schedule hasn't been set up yet.
        --}}
        @if ($branch->todays_hours_label)
            <div class="text-sm mb-3">
                <span class="font-medium">{{ __('Hours today') }}:</span>
                {{ $branch->todays_hours_label }}
            </div>
        @endif

        <div class="mb-5">
            @if (! $branch->is_accepting_orders)
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-red-dark bg-brand-red-light px-2.5 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-red"></span>
                    {{ __('Not accepting orders right now') }}
                </span>
            @elseif ($branch->is_open_now)
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-white bg-brand-red px-2.5 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-white"></span>
                    {{ __('Accepting orders') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-black bg-brand-gray-100 px-2.5 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-gray-500"></span>
                    {{ $branch->next_opening_label ? __('Closed, opens :time', ['time' => $branch->next_opening_label]) : __('Currently closed') }}
                </span>
            @endif
        </div>

        <div class="mt-auto space-y-2">
            <a
                href="{{ route('branches.pick', $branch) }}"
                class="block text-center px-4 py-2.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark"
            >
                {{ __('Order from here') }}
            </a>
            <div class="grid grid-cols-2 gap-2">
                <a
                    href="tel:{{ $branch->phone }}"
                    class="text-center px-4 py-2 border border-brand-black text-brand-black text-sm font-semibold rounded-md hover:bg-brand-black hover:text-brand-white transition"
                >
                    {{ __('Call branch') }}
                </a>
                <button
                    type="button" @click="showMap = !showMap"
                    class="text-center px-4 py-2 border border-brand-gray-300 text-brand-black text-sm font-semibold rounded-md hover:bg-brand-gray-100 transition"
                >
                    <span x-text="showMap ? '{{ __('Hide map') }}' : '{{ __('View map') }}'"></span>
                </button>
            </div>
        </div>

        <div x-show="showMap" x-cloak class="mt-4 -mx-5 -mb-5">
            <a
                href="https://www.google.com/maps/dir/?api=1&destination={{ $branch->lat }},{{ $branch->lng }}"
                target="_blank"
                rel="noopener"
                class="relative block h-48 group"
            >
                <iframe
                    src="https://www.google.com/maps?q={{ $branch->lat }},{{ $branch->lng }}&z=16&output=embed"
                    class="absolute inset-0 w-full h-full pointer-events-none"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
                <div class="absolute inset-0 bg-brand-black/0 group-hover:bg-brand-black/10 transition flex items-end justify-end p-3">
                    <span class="opacity-0 group-hover:opacity-100 transition bg-brand-white text-brand-black text-xs font-semibold px-3 py-1.5 rounded-md shadow">
                        {{ __('Get directions ↗') }}
                    </span>
                </div>
            </a>
        </div>
    </div>
</div>

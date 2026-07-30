<x-customer-layout title="Branches · {{ config('app.name') }}">
    <h1 class="text-2xl font-bold mb-8">{{ __('Our branches') }}</h1>

    <div class="space-y-10">
        @foreach ($branches as $branch)
            <section class="border border-brand-gray-100 rounded-xl overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-2">
                    {{-- Left: image space, hours, status, actions --}}
                    <div class="p-6 flex flex-col">
                        <x-branch-image :branch="$branch" class="w-full aspect-video rounded-lg mb-5" />

                        <h2 class="text-xl font-bold mb-1">{{ $branch->name }}</h2>
                        <p class="text-sm text-brand-gray-500 mb-4">{{ $branch->address }}</p>

                        <div class="text-sm mb-3">
                            <span class="font-medium">{{ __('Hours') }}:</span>
                            {{ \Illuminate\Support\Carbon::parse($branch->opens_at)->format('g:ia') }}
                            – {{ \Illuminate\Support\Carbon::parse($branch->closes_at)->format('g:ia') }}
                        </div>

                        <div class="mb-6">
                            @if ($branch->is_accepting_orders)
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-black bg-brand-yellow-light px-2.5 py-1 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-brand-black"></span>
                                    {{ __('Accepting orders') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-red-dark bg-brand-red-light px-2.5 py-1 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-brand-red"></span>
                                    {{ __('Not accepting orders right now') }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-auto flex flex-wrap gap-3">
                            <a
                                href="{{ route('branches.pick', $branch) }}"
                                class="px-5 py-2.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark"
                            >
                                {{ __('Order from here') }}
                            </a>
                            <a
                                href="tel:{{ $branch->phone }}"
                                class="px-5 py-2.5 border border-brand-black text-brand-black text-sm font-semibold rounded-md hover:bg-brand-black hover:text-brand-white transition"
                            >
                                {{ __('Call branch') }}
                            </a>
                        </div>
                    </div>

                    {{--
                        Right: embedded map (view only, output=embed needs no
                        API key), wrapped in a link to Google Maps directions
                        so the whole preview is one click-through. The
                        iframe itself is pointer-events-none so clicks pass
                        through to the wrapping <a> instead of just panning
                        the embedded map.
                    --}}
                    <a
                        href="https://www.google.com/maps/dir/?api=1&destination={{ $branch->lat }},{{ $branch->lng }}"
                        target="_blank"
                        rel="noopener"
                        class="relative block h-64 md:h-full group"
                    >
                        <iframe
                            src="https://www.google.com/maps?q={{ $branch->lat }},{{ $branch->lng }}&z=16&output=embed"
                            class="absolute inset-0 w-full h-full pointer-events-none"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                        ></iframe>
                        <div class="absolute inset-0 bg-brand-black/0 group-hover:bg-brand-black/10 transition flex items-end justify-end p-4">
                            <span class="opacity-0 group-hover:opacity-100 transition bg-brand-white text-brand-black text-xs font-semibold px-3 py-1.5 rounded-md shadow">
                                {{ __('Get directions ↗') }}
                            </span>
                        </div>
                    </a>
                </div>
            </section>
        @endforeach
    </div>
</x-customer-layout>

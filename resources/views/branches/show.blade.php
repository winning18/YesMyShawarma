@php
    // "GIMPA Branch" reads badly as "Shawarma in GIMPA Branch" — the area
    // name alone is what people actually search for.
    $area = trim(\Illuminate\Support\Str::replaceLast(' Branch', '', $branch->name));
    $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $today = now('Africa/Accra')->dayOfWeekIso;
    $formatTime = fn (string $time) => \Illuminate\Support\Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('g:ia');
@endphp
<x-customer-layout
    :title="__('Shawarma & Burgers in :area, Accra · :name', ['area' => $area, 'name' => config('app.name')])"
    :og-image="$branch->imageUrl()"
    :og-description="__(':name :branch, :address. Order shawarma, burgers and fresh juices online for delivery or pickup. Call :phone.', ['name' => config('app.name'), 'branch' => $branch->name, 'address' => $branch->address, 'phone' => $branch->phone])"
    main-class="max-w-5xl"
>
    {{-- JSON_HEX_TAG/JSON_HEX_AMP: admin-editable branch name/address are
         embedded verbatim — see menu/show.blade.php for why. --}}
    <script type="application/ld+json">{!! json_encode($restaurantSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <x-slot name="pageHeader">{{ __(':name :branch', ['name' => config('app.name'), 'branch' => $branch->name]) }}</x-slot>

    <a href="{{ route('branches.index') }}" class="text-sm underline text-brand-gray-500">&larr; {{ __('All branches') }}</a>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
        <x-branch-card :branch="$branch" :selected="$selected" :link-to-page="false" />

        <div class="space-y-8">
            <section>
                <h2 class="text-lg font-bold mb-2">{{ __('Shawarma in :area', ['area' => $area]) }}</h2>
                <p class="text-brand-gray-500">
                    {{ __('Fresh chicken and beef shawarma, burgers, loaded fries and fresh juices at our :branch branch, :address. Order online for delivery to your door or pickup from the branch, paying by mobile money, card or cash.', ['branch' => $branch->name, 'address' => $branch->address]) }}
                </p>
            </section>

            @if ($deliveryAreas)
                <section>
                    <h2 class="text-lg font-bold mb-2">{{ __('Delivery areas') }}</h2>
                    <p class="text-brand-gray-500">
                        {{ __('We deliver from :branch to :areas and nearby areas.', ['branch' => $branch->name, 'areas' => implode(', ', $deliveryAreas)]) }}
                    </p>
                </section>
            @endif

            <section>
                <h2 class="text-lg font-bold mb-2">{{ __('Opening hours') }}</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-brand-gray-100">
                        @foreach ($weeklyHours as $day)
                            <tr class="{{ $day['day_of_week'] === $today ? 'font-semibold' : '' }}">
                                <td class="py-2">{{ __($dayNames[$day['day_of_week']]) }}</td>
                                <td class="py-2 text-right">
                                    @if ($day['opens_at'] && $day['closes_at'])
                                        {{ $formatTime($day['opens_at']) }} – {{ $formatTime($day['closes_at']) }}
                                    @else
                                        <span class="text-brand-gray-400">{{ __('Closed') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            @if ($otherBranches->isNotEmpty())
                <section>
                    <h2 class="text-lg font-bold mb-2">{{ __('Our other branches') }}</h2>
                    <ul class="space-y-1">
                        @foreach ($otherBranches as $other)
                            <li>
                                <a href="{{ route('branches.show', $other) }}" class="underline hover:text-brand-gray-500">{{ $other->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-customer-layout>

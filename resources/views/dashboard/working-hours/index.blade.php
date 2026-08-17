<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Working Hours') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">{{ __('Set the opening and closing time for each day of the week. Leave a day blank to mark it closed.') }}</p>

        @if ($isOwner)
            <form method="GET" action="{{ route('dashboard.working-hours.index') }}">
                <label for="branch" class="sr-only">{{ __('Branch') }}</label>
                <select id="branch" name="branch" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        @if ($branchId)
            <form
                method="POST" action="{{ route('dashboard.working-hours.update') }}" class="bg-white shadow rounded-lg p-6 space-y-4"
                x-data="{ error: null }"
                @submit="error = validateWorkingHours($event.target); if (error) $event.preventDefault();"
            >
                @csrf
                @method('PUT')

                <div x-show="error" x-cloak class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2" x-text="error"></div>
                @if ($isOwner)
                    <input type="hidden" name="branch" value="{{ $branchId }}">
                @endif

                @php
                    $dayLabels = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')];
                @endphp

                <div class="divide-y divide-gray-100">
                    @foreach ($days as $day)
                        @php $d = $day['day_of_week']; @endphp
                        <div class="py-3 grid grid-cols-3 sm:grid-cols-4 gap-3 items-center" data-day-row data-day-label="{{ $dayLabels[$d] }}">
                            <span class="text-sm font-medium text-gray-800">{{ $dayLabels[$d] }}</span>

                            <div>
                                <x-input-label :for="'opens_at_'.$d" :value="__('Start')" class="sr-only" />
                                <x-text-input
                                    :id="'opens_at_'.$d" type="time" class="block w-full text-sm"
                                    name="days[{{ $d }}][opens_at]"
                                    :value="old('days.'.$d.'.opens_at', $day['opens_at'] ? \Illuminate\Support\Carbon::parse($day['opens_at'])->format('H:i') : null)"
                                />
                                <x-input-error class="mt-1" :messages="$errors->get('days.'.$d.'.opens_at')" />
                            </div>

                            <div class="col-span-1 sm:col-span-2 flex items-center gap-3">
                                <span class="text-gray-400 text-sm hidden sm:inline">{{ __('to') }}</span>
                                <div class="flex-1">
                                    <x-input-label :for="'closes_at_'.$d" :value="__('Finish')" class="sr-only" />
                                    <x-text-input
                                        :id="'closes_at_'.$d" type="time" class="block w-full text-sm"
                                        name="days[{{ $d }}][closes_at]"
                                        :value="old('days.'.$d.'.closes_at', $day['closes_at'] ? \Illuminate\Support\Carbon::parse($day['closes_at'])->format('H:i') : null)"
                                    />
                                    <x-input-error class="mt-1" :messages="$errors->get('days.'.$d.'.closes_at')" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-2">
                    <x-primary-button>{{ __('Save working hours') }}</x-primary-button>
                </div>
            </form>
        @else
            <p class="text-sm text-gray-500">{{ __('No branches yet.') }}</p>
        @endif
    </div>

    <script>
        // Mirrors UpdateWorkingHoursRequest's required_with (each day's pair
        // is either both filled or both blank) and after:opens_at rules —
        // a faster front door only, server-side stays the actual authority.
        function validateWorkingHours(form) {
            for (const row of form.querySelectorAll('[data-day-row]')) {
                const [opens, closes] = row.querySelectorAll('input[type=time]');
                if (!opens.value && !closes.value) continue;

                if (!opens.value || !closes.value) {
                    return `${row.dataset.dayLabel}: set both a start and finish time, or leave both blank.`;
                }
                if (closes.value <= opens.value) {
                    return `${row.dataset.dayLabel}: finish time must be after the start time.`;
                }
            }

            return null;
        }
    </script>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Time table') }}</h2>
            <p class="text-sm text-gray-500">{{ $branch->name }}</p>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-8">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            {{ __("Only items you give a schedule are affected. Everything else keeps following the manual Available/Sold out toggle. A scheduled item's availability is set automatically every few minutes based on today's day and time; toggling it by hand still works, but the next sync reasserts the schedule.") }}
        </p>

        @forelse ($categories as $group)
            <section class="space-y-4">
                <h3 class="font-semibold text-gray-800">{{ $group['category']->name }}</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($group['items'] as $row)
                        @php $item = $row['item']; $schedules = $row['schedules']; @endphp
                        <div class="bg-white shadow rounded-lg p-4 flex flex-col gap-3">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-gray-800 text-sm">{{ $item->name }}</p>

                                @if ($schedules->isNotEmpty())
                                    <form
                                        method="POST" action="{{ route('dashboard.menu-items.schedule.destroy', $item) }}"
                                        onsubmit="return confirm('{{ __('Clear the timetable for :name?', ['name' => $item->name]) }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="shrink-0 text-xs text-red-600 hover:underline">{{ __('Clear') }}</button>
                                    </form>
                                @endif
                            </div>

                            @if ($schedules->isNotEmpty())
                                <p class="text-xs text-gray-500">
                                    {{ $schedules->pluck('day_of_week')->map(fn ($day) => $dayNames[$day])->implode(', ') }}
                                    · {{ \Illuminate\Support\Carbon::parse($schedules->first()->starts_at)->format('H:i') }} {{ __('to') }} {{ \Illuminate\Support\Carbon::parse($schedules->first()->ends_at)->format('H:i') }}
                                </p>
                            @else
                                <p class="text-xs text-gray-500">{{ __('No schedule: follows manual toggle.') }}</p>
                            @endif

                            <form method="POST" action="{{ route('dashboard.menu-items.schedule.update', $item) }}" class="space-y-2">
                                @csrf
                                <div class="flex flex-wrap gap-x-2 gap-y-1 text-xs">
                                    @foreach ($dayNames as $value => $label)
                                        <label class="flex items-center gap-1">
                                            <input
                                                type="checkbox" name="days[]" value="{{ $value }}"
                                                @checked($schedules->pluck('day_of_week')->contains($value))
                                                class="rounded border-gray-300"
                                            >
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input
                                        type="time" name="starts_at" required class="w-24 rounded-md border-gray-300 text-xs"
                                        value="{{ $schedules->first() ? \Illuminate\Support\Carbon::parse($schedules->first()->starts_at)->format('H:i') : '' }}"
                                    >
                                    <span class="text-gray-400 text-xs">{{ __('to') }}</span>
                                    <input
                                        type="time" name="ends_at" required class="w-24 rounded-md border-gray-300 text-xs"
                                        value="{{ $schedules->first() ? \Illuminate\Support\Carbon::parse($schedules->first()->ends_at)->format('H:i') : '' }}"
                                    >
                                    <button type="submit" class="px-2 py-1 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900">
                                        {{ __('Save') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-sm text-gray-500">{{ __('No menu items yet.') }}</p>
        @endforelse
    </div>
</x-app-layout>

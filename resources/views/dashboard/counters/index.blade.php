<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Counters') }}: {{ $branch->name }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-gray-500">
            {{ __('With no counters, the branch has one shared shift, as it always has. Add counters and each one gets a till of its own: its own float, its own sales and its own cash-up. POS sales are then only taken on a device set up as a counter.') }}
        </p>

        <div class="bg-white shadow rounded-lg p-4 text-sm flex flex-wrap items-center justify-between gap-3">
            <span>
                {{ __('This device:') }}
                <strong>{{ $thisDevice ? $thisDevice->name.' ('.$thisDevice->branch->name.')' : __('not a counter') }}</strong>
            </span>
            @if ($thisDevice)
                <form method="POST" action="{{ route('dashboard.counters.forget') }}" onsubmit="return confirm(@js(__('Stop using this device as a counter?')))">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 underline">{{ __('Stop using this device as a counter') }}</button>
                </form>
            @endif
        </div>

        @forelse ($counters as ['counter' => $counter, 'till' => $till])
            <div class="bg-white shadow rounded-lg p-5 space-y-3 {{ $counter->is_active ? '' : 'opacity-70' }}">
                <form method="POST" action="{{ route('dashboard.counters.update', $counter) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PUT')
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-gray-500" for="counter-{{ $counter->id }}">{{ __('Name') }}</label>
                        <input id="counter-{{ $counter->id }}" name="name" value="{{ $counter->name }}" maxlength="40" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    @if ($receiptPrinters->isNotEmpty())
                        <div>
                            <label class="block text-xs font-medium text-gray-500" for="counter-printer-{{ $counter->id }}">{{ __('Receipt printer') }}</label>
                            <select id="counter-printer-{{ $counter->id }}" name="receipt_printer_id" class="mt-1 block rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('Branch\'s first receipt printer') }}</option>
                                @foreach ($receiptPrinters as $receiptPrinter)
                                    <option value="{{ $receiptPrinter->id }}" @selected($counter->receipt_printer_id === $receiptPrinter->id)>{{ $receiptPrinter->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <label class="flex items-center gap-2 text-sm pb-2">
                        <input type="checkbox" name="is_active" value="1" @checked($counter->is_active) class="rounded border-gray-300">
                        {{ __('In use') }}
                    </label>
                    <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-xs font-semibold rounded-md">{{ __('Save') }}</button>
                </form>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600">
                    <span>{{ $till ? __('Till open since :time', ['time' => $till->started_at->timezone('Africa/Accra')->format('g:ia')]) : __('Till closed') }}</span>
                    <span>{{ $counter->isPaired() ? __('Device set up :date', ['date' => $counter->paired_at->timezone('Africa/Accra')->format('j M Y')]) : __('No device yet') }}</span>
                    @if ($counter->is_active && $thisDevice?->id !== $counter->id)
                        <form method="POST" action="{{ route('dashboard.counters.pair', $counter) }}" @if ($counter->isPaired()) onsubmit="return confirm(@js(__(':counter is set up on another device. Move it to this one?', ['counter' => $counter->name])))" @endif>
                            @csrf
                            <button type="submit" class="font-semibold text-indigo-600 hover:text-indigo-800">{{ __('Use this device as :counter', ['counter' => $counter->name]) }}</button>
                        </form>
                    @elseif ($thisDevice?->id === $counter->id)
                        <span class="font-semibold text-green-700">{{ __('This device') }}</span>
                    @endif
                    @if ($counter->isPaired() && $thisDevice?->id !== $counter->id)
                        <form method="POST" action="{{ route('dashboard.counters.unpair', $counter) }}" onsubmit="return confirm(@js(__('Remove :counter\'s device? That device stops being a counter.', ['counter' => $counter->name])))">
                            @csrf
                            <button type="submit" class="text-red-600 underline">{{ __('Remove its device') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white shadow rounded-lg p-5 text-sm text-gray-500">{{ __('No counters yet: this branch uses one shared shift.') }}</div>
        @endforelse

        <form method="POST" action="{{ route('dashboard.counters.store') }}" class="bg-white shadow rounded-lg p-5 flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700" for="new-counter">{{ __('Add a counter') }}</label>
                <input id="new-counter" name="name" maxlength="40" required placeholder="{{ __('e.g. Counter :n', ['n' => $counters->count() + 1]) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </div>
            <x-primary-button>{{ __('Add counter') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

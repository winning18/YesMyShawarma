<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Printers') }}: {{ $branch->name }}</h2>
    </x-slot>

    @php
        $fields = function ($printer = null) {
            return [
                'role' => old('role', $printer?->role ?? 'receipt'),
                'paper_width' => (int) old('paper_width', $printer?->paper_width ?? 80),
                'connection' => old('connection', $printer?->connection ?? 'network'),
            ];
        };
    @endphp

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-gray-500">
            {{ __('Receipts print when a POS sale is placed; kitchen tickets when an order is accepted. Every order page and the order board also have Print buttons for reprints. For now printing goes through the device\'s own printer; the Express POS Print Agent, coming next, will print straight to these printers and open the cash drawer.') }}
        </p>

        @forelse ($printers as $printer)
            @php $f = $fields($printer); @endphp
            <form method="POST" action="{{ route('dashboard.printers.update', $printer) }}" class="bg-white shadow rounded-lg p-5 space-y-3 {{ $printer->is_active ? '' : 'opacity-70' }}" x-data="{ role: @js($f['role']), connection: @js($f['connection']) }">
                @csrf
                @method('PUT')
                @include('dashboard.printers.partials.fields', ['printer' => $printer, 'f' => $f])
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" @checked($printer->is_active) class="rounded border-gray-300">
                        {{ __('In use') }}
                    </label>
                    <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-xs font-semibold rounded-md">{{ __('Save') }}</button>
                </div>
            </form>
        @empty
            <div class="bg-white shadow rounded-lg p-5 text-sm text-gray-500">{{ __('No printers yet: nothing prints, and no Print buttons show.') }}</div>
        @endforelse

        @php $f = $fields(); @endphp
        <form method="POST" action="{{ route('dashboard.printers.store') }}" class="bg-white shadow rounded-lg p-5 space-y-3" x-data="{ role: @js($f['role']), connection: @js($f['connection']) }">
            @csrf
            <h3 class="font-semibold text-gray-800">{{ __('Add a printer') }}</h3>
            @include('dashboard.printers.partials.fields', ['printer' => null, 'f' => $f])
            <x-primary-button>{{ __('Add printer') }}</x-primary-button>
        </form>

        @if ($counters->isNotEmpty())
            <p class="text-sm text-gray-500">{{ __('Choose each counter\'s receipt printer on the') }} <a href="{{ route('dashboard.counters.index') }}" class="underline">{{ __('Counters') }}</a> {{ __('page. A counter without one uses the branch\'s first receipt printer.') }}</p>
        @endif
    </div>
</x-app-layout>

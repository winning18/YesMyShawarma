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
            {{ __('Receipts print when a POS sale is placed; kitchen tickets when an order is accepted. Every order page and the order board also have Print buttons for reprints. With the Express POS Print Agent running on the branch\'s PC, everything prints straight to these printers with no print dialog, and the cash drawer opens on cash sales. Without it, printing goes through the device\'s own print dialog.') }}
        </p>

        {{-- Step 8b: the print agent on the branch PC. --}}
        <div class="bg-white shadow rounded-lg p-5 space-y-3">
            <h3 class="font-semibold text-gray-800">{{ __('Print agent') }}</h3>

            @if ($code = session('agentCode'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                    <p>{{ __('Connection code for :name:', ['name' => $code['agent']]) }}</p>
                    <p class="mt-1 text-3xl font-mono font-bold tracking-widest">{{ $code['code'] }}</p>
                    <p class="mt-1">{{ __('Type it into the Express POS Print Agent on the branch PC within :minutes minutes. It only works once.', ['minutes' => $code['minutes']]) }}</p>
                </div>
            @endif

            @forelse ($agents as $agent)
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm border-t border-gray-100 pt-3 first:border-0 first:pt-0">
                    <span>
                        <strong>{{ $agent->name }}</strong> ·
                        @if (! $agent->isConnected())
                            <span class="text-amber-700">{{ __('waiting to be connected') }}</span>
                        @elseif ($agent->isOnline())
                            <span class="text-green-700 font-semibold">{{ __('online') }}</span>
                        @else
                            <span class="text-red-600">{{ __('offline') }}</span>{{ $agent->last_seen_at ? ' · '.__('last seen :time', ['time' => $agent->last_seen_at->timezone('Africa/Accra')->diffForHumans()]) : '' }}
                        @endif
                    </span>
                    <span class="flex items-center gap-4">
                        <form method="POST" action="{{ route('dashboard.printers.agents.connect') }}">
                            @csrf
                            <input type="hidden" name="name" value="{{ $agent->name }}">
                            <input type="hidden" name="agent_id" value="{{ $agent->id }}">
                            <button type="submit" class="text-indigo-600 hover:underline">{{ __('New connection code') }}</button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.printers.agents.disconnect', $agent) }}" onsubmit="return confirm(@js(__('Disconnect :name? It stops printing straight away.', ['name' => $agent->name])))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 underline">{{ __('Disconnect') }}</button>
                        </form>
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('No print agent yet.') }}</p>
            @endforelse

            <form method="POST" action="{{ route('dashboard.printers.agents.connect') }}" class="flex flex-wrap items-end gap-3 border-t border-gray-100 pt-3">
                @csrf
                <div class="flex-1 min-w-48">
                    <label for="agent-name" class="block text-xs font-medium text-gray-500">{{ __('Connect a print agent') }}</label>
                    <input id="agent-name" name="name" maxlength="60" required value="{{ __('Counter PC') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <x-primary-button>{{ __('Get a connection code') }}</x-primary-button>
            </form>
            <p class="text-xs text-gray-500">
                {{ __('On the branch\'s Windows PC:') }}
                <a href="{{ asset('downloads/ExpressPOSPrintAgent.exe') }}" class="underline font-semibold">{{ __('download the Express POS Print Agent') }}</a>,
                {{ __('run it, and type this site\'s address and the connection code when it asks. It starts with Windows from then on.') }}
            </p>
        </div>

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

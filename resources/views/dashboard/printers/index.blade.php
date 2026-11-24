<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Printers') }}: {{ $branch->name }}</h2>
    </x-slot>

    @php
        $connectedAgents = $agents->filter->isConnected();
        $siteAddress = rtrim(url('/'), '/');
        $describe = fn ($printer) => collect([
            $printer->role === 'receipt' ? __('Customer receipts') : __('Kitchen tickets'),
            $printer->paper_width.' mm',
            $printer->connection === 'network' ? __('Network').($printer->address ? ' '.$printer->address : '') : __('USB').($printer->address ? ' "'.$printer->address.'"' : ''),
            $printer->opens_drawer ? __('cash drawer') : null,
        ])->filter()->join(' · ');
    @endphp

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif
        <x-error-summary />

        <p class="text-sm text-gray-600">
            {{ __('Receipts print when a POS sale is placed, kitchen tickets when an order is accepted, and every order has Print buttons for reprints.') }}
            @if ($agentOnline)
                <strong class="text-green-700">{{ __('The print agent is online: everything prints straight to these printers, with no print dialog.') }}</strong>
            @else
                {{ __('Until the print agent (part 2) is running, printing goes through the device\'s own print dialog.') }}
            @endif
        </p>

        {{-- 1. The branch's printers --}}
        <section class="space-y-3">
            <h3 class="font-semibold text-gray-800">{{ __('1. Your printers') }}</h3>

            @forelse ($printers as $printer)
                @php $test = $latestTests->get($printer->id); @endphp
                <div class="bg-white shadow rounded-lg p-5 space-y-3 {{ $printer->is_active ? '' : 'opacity-70' }}" x-data="printerTest({{ $printer->id }}, @js($test?->status), @js($test?->error))">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-800">{{ $printer->name }} @unless ($printer->is_active)<span class="text-xs font-normal text-gray-500">({{ __('not in use') }})</span>@endunless</p>
                            <p class="text-sm text-gray-600">{{ $describe($printer) }}</p>
                            <p class="text-xs mt-1" :class="statusClass()" x-text="statusText()"></p>
                        </div>
                        <button type="button" @click="run()" :disabled="busy" class="shrink-0 px-3 py-2 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900 disabled:opacity-50">{{ __('Print a test page') }}</button>
                    </div>

                    @if ($agentOnline && $printer->connection === 'network' && ! $printer->address)
                        <p class="text-xs text-amber-700">{{ __('Type this printer\'s IP address below so the print agent can reach it.') }}</p>
                    @elseif ($agentOnline && $printer->connection === 'usb' && ! $printer->address)
                        <p class="text-xs text-amber-700">{{ __('Type this printer\'s name as Windows shows it below so the print agent can reach it.') }}</p>
                    @endif

                    <details class="text-sm" @if ($errors->any() && (int) old('printer_id') === $printer->id) open @endif>
                        <summary class="cursor-pointer text-indigo-600 font-semibold">{{ __('Edit') }}</summary>
                        <form method="POST" action="{{ route('dashboard.printers.update', $printer) }}" class="mt-3 space-y-3" x-data="{ role: @js($printer->role), connection: @js($printer->connection) }">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="printer_id" value="{{ $printer->id }}">
                            @include('dashboard.printers.partials.fields', ['printer' => $printer, 'f' => ['paper_width' => $printer->paper_width]])
                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="is_active" value="1" @checked($printer->is_active) class="rounded border-gray-300">
                                    {{ __('In use') }}
                                </label>
                                <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-xs font-semibold rounded-md">{{ __('Save') }}</button>
                            </div>
                        </form>
                    </details>
                </div>
            @empty
                <div class="bg-white shadow rounded-lg p-5 text-sm text-gray-500">{{ __('No printers yet: nothing prints, and no Print buttons show. Add the branch\'s receipt and kitchen printers below.') }}</div>
            @endforelse

            <details class="bg-white shadow rounded-lg p-5" @if ($printers->isEmpty() || ($errors->any() && ! old('printer_id'))) open @endif>
                <summary class="cursor-pointer font-semibold text-gray-800">{{ __('Add a printer') }}</summary>
                <form method="POST" action="{{ route('dashboard.printers.store') }}" class="mt-4 space-y-3" x-data="{ role: @js(old('role', 'receipt')), connection: @js(old('connection', 'network')) }">
                    @csrf
                    @include('dashboard.printers.partials.fields', ['printer' => null, 'f' => ['paper_width' => (int) old('paper_width', 80)]])
                    <x-primary-button>{{ __('Add printer') }}</x-primary-button>
                </form>
            </details>

            @if ($counters->isNotEmpty())
                <p class="text-sm text-gray-500">{{ __('Each counter can use its own receipt printer: choose it on the') }} <a href="{{ route('dashboard.counters.index') }}" class="underline">{{ __('Counters') }}</a> {{ __('page. A counter without one uses the branch\'s first receipt printer.') }}</p>
            @endif
        </section>

        {{-- 2. The print agent on the branch's Windows PC --}}
        <section class="space-y-3">
            <h3 class="font-semibold text-gray-800">{{ __('2. Print agent (the branch\'s Windows PC)') }}</h3>

            <div class="bg-white shadow rounded-lg p-5 space-y-4">
                <p class="text-sm text-gray-600">{{ __('A small program that prints straight to your printers with no print dialog, and opens the cash drawer on cash sales. It works with any receipt printer, network or USB.') }}</p>

                <ol class="space-y-4 text-sm">
                    <li class="flex gap-3">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-gray-800 text-white text-xs font-bold flex items-center justify-center">1</span>
                        <div class="space-y-2">
                            <p>{{ __('On the Windows PC, download the print agent.') }}</p>
                            <a href="{{ asset('downloads/ExpressPOSPrintAgent.exe') }}" download class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.69L6.28 8.47a.75.75 0 0 0-1.06 1.06l4.25 4.25a.75.75 0 0 0 1.06 0l4.25-4.25a.75.75 0 0 0-1.06-1.06l-2.97 2.97V2.75Z"/><path d="M3.5 13.75a.75.75 0 0 0-1.5 0v1.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-1.5a.75.75 0 0 0-1.5 0v1.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-1.5Z"/></svg>
                                {{ __('Download for Windows') }}
                            </a>
                            <p class="text-xs text-gray-500">{{ __('ExpressPOSPrintAgent.exe, about 5 MB. Or open this page on that PC and click the button there.') }}</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-gray-800 text-white text-xs font-bold flex items-center justify-center">2</span>
                        <div class="space-y-2 flex-1">
                            <p>{{ __('Get a connection code for it.') }}</p>
                            <form method="POST" action="{{ route('dashboard.printers.agents.connect') }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                <div class="flex-1 min-w-40">
                                    <label for="agent-name" class="block text-xs font-medium text-gray-500">{{ __('Name for this PC') }}</label>
                                    <input id="agent-name" name="name" maxlength="60" required value="{{ __('Counter PC') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Get a connection code') }}</button>
                            </form>
                        @if ($code = session('agentCode'))
                            <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                                <p>{{ __('Connection code for :name:', ['name' => $code['agent']]) }}</p>
                                <p class="mt-1 text-3xl font-mono font-bold tracking-widest">{{ $code['code'] }}</p>
                                <p class="mt-1">{{ __('Type it into the print agent within :minutes minutes. It only works once.', ['minutes' => $code['minutes']]) }}</p>
                            </div>
                        @endif
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-gray-800 text-white text-xs font-bold flex items-center justify-center">3</span>
                        <div class="space-y-1">
                            <p>{{ __('Right-click the downloaded file, choose "Run as administrator", and type:') }}</p>
                            <p>{{ __('Site address:') }} <code class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $siteAddress }}</code></p>
                            <p>{{ __('Connection code: the code from step 2.') }}</p>
                            <p class="text-xs text-gray-500">{{ __('If Windows says "unknown publisher", choose More info, then Run anyway. The agent then runs in the background and starts with Windows; it shows as online below.') }}</p>
                        </div>
                    </li>
                </ol>

                <div class="border-t border-gray-100 pt-4 space-y-2">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('This branch\'s print agents') }}</p>
                    @forelse ($agents as $agent)
                        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span>
                                <strong>{{ $agent->name }}</strong> ·
                                @if (! $agent->isConnected())
                                    <span class="text-amber-700">{{ __('waiting for its connection code') }}</span>
                                @elseif ($agent->isOnline())
                                    <span class="text-green-700 font-semibold">{{ __('online') }}</span>
                                @else
                                    <span class="text-red-600">{{ __('offline') }}</span>{{ $agent->last_seen_at ? ' · '.__('last seen :time', ['time' => $agent->last_seen_at->diffForHumans()]) : '' }}
                                @endif
                            </span>
                            <span class="flex items-center gap-4">
                                <form method="POST" action="{{ route('dashboard.printers.agents.connect') }}">
                                    @csrf
                                    <input type="hidden" name="name" value="{{ $agent->name }}">
                                    <input type="hidden" name="agent_id" value="{{ $agent->id }}">
                                    <button type="submit" class="text-indigo-600 hover:underline">{{ __('New connection code') }}</button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.printers.agents.disconnect', $agent) }}" onsubmit="return confirm(@js(__('Remove :name? It stops printing straight away.', ['name' => $agent->name])))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 underline">{{ $agent->isConnected() ? __('Disconnect') : __('Remove') }}</button>
                                </form>
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('None yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    @include('partials.print-script', ['printBranchId' => $branch->id])
    <script>
        // "Print a test page": the agent prints it (and this checks it came
        // out), or, with no agent on, this device's print dialog does.
        function printerTest(printerId, status, error) {
            return {
                status, error, busy: false,
                statusText() {
                    return {
                        pending: @js(__('Test page waiting for the print agent…')),
                        sent: @js(__('Test page sent to the printer…')),
                        printed: @js(__('Last test page printed.')),
                        failed: @js(__('Last test page didn\'t print:')) + ' ' + (this.error || ''),
                        browser: @js(__('Test page sent to this device\'s print dialog.')),
                    }[this.status] || @js(__('Not tested yet.'));
                },
                statusClass() {
                    return { printed: 'text-green-700', failed: 'text-red-600', browser: 'text-gray-600' }[this.status] || 'text-gray-500';
                },
                async run() {
                    this.busy = true;
                    try {
                        const response = await fetch(@js(url('/dashboard/printers')) + '/' + printerId + '/test', {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        });
                        const payload = await response.json();
                        if (! payload.queued) {
                            this.status = 'browser';
                            window.expressPrint(@js(url('/dashboard/printers')) + '/' + printerId + '/test');
                            return;
                        }
                        this.status = 'pending';
                        for (let i = 0; i < 15 && ['pending', 'sent'].includes(this.status); i++) {
                            await new Promise(resolve => setTimeout(resolve, 2000));
                            const check = await (await fetch(@js(url('/dashboard/printers/jobs')) + '/' + payload.job, { headers: { Accept: 'application/json' } })).json();
                            this.status = check.status;
                            this.error = check.error;
                        }
                    } catch (e) {
                        this.status = 'failed';
                        this.error = window.explainError(e);
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>

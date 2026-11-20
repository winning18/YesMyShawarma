<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Tax') }}</h2>
    </x-slot>

    @php
        $rate = fn (int $basisPoints) => rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.');
        $initialLines = old('lines', collect($config['lines'])->map(fn ($line) => ['name' => $line['name'], 'rate' => $rate($line['rate_basis_points'])])->all());
        $presetLines = collect($preset)->map(fn ($line) => ['name' => $line['name'], 'rate' => $rate($line['rate_basis_points'])])->all();
    @endphp

    <div class="max-w-2xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">{{ __('The taxes you charge on sales, such as VAT and levies. They show on receipts and the Tax report. With no taxes listed, nothing is charged. A change applies to new orders only; orders already placed keep the tax they were sold with.') }}</p>

        <form
            method="POST" action="{{ route('dashboard.tax.update') }}"
            class="bg-white shadow rounded-lg p-6 space-y-8"
            x-data="{
                lines: @js(array_values($initialLines)),
                preset: @js($presetLines),
                max: {{ $maxLines }},
                inclusive: @js((bool) old('inclusive', $config['inclusive'])),
                combined() { return this.lines.reduce((sum, line) => sum + (parseFloat(line.rate) || 0), 0); },
                example() {
                    const price = 100, rate = this.combined();
                    if (!rate) return '';
                    const tax = this.inclusive ? price * rate / (100 + rate) : price * rate / 100;
                    return this.inclusive
                        ? @js(__('A GH₵100.00 item: the customer pays GH₵100.00, of which')) + ' GH₵' + tax.toFixed(2) + ' ' + @js(__('is tax.'))
                        : @js(__('A GH₵100.00 item: the customer pays')) + ' GH₵' + (price + tax).toFixed(2) + ' (GH₵' + tax.toFixed(2) + ' ' + @js(__('tax added).'));
                },
            }"
        >
            @csrf
            @method('PUT')

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-gray-800">{{ __('Taxes you charge') }}</h3>
                    <button type="button" class="text-sm font-semibold text-indigo-700 hover:underline" @click="lines = JSON.parse(JSON.stringify(preset))">{{ __('Use Ghana\'s standard rates') }}</button>
                </div>
                <p class="text-xs text-gray-500">{{ __('Ghana\'s standard rates from 2026: VAT 15%, NHIL 2.5% and GETFund 2.5%, all on the value of the sale (20% together). Check the rates that apply to your business with GRA or your accountant.') }}</p>

                <template x-if="lines.length === 0">
                    <p class="text-sm text-gray-500 bg-gray-50 rounded-md px-3 py-2">{{ __('No taxes: nothing is charged.') }}</p>
                </template>

                <template x-for="(line, index) in lines" :key="index">
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-500 mb-1" :for="'tax-name-' + index">{{ __('Name') }}</label>
                            <input type="text" :id="'tax-name-' + index" :name="'lines[' + index + '][name]'" x-model="line.name" maxlength="40" class="block w-full rounded-md border-gray-300 text-sm" placeholder="{{ __('e.g. VAT') }}">
                        </div>
                        <div class="w-28">
                            <label class="block text-xs font-medium text-gray-500 mb-1" :for="'tax-rate-' + index">{{ __('Rate (%)') }}</label>
                            <input type="number" :id="'tax-rate-' + index" :name="'lines[' + index + '][rate]'" x-model="line.rate" min="0.01" max="100" step="0.01" inputmode="decimal" class="block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <button type="button" class="px-3 py-2 text-sm text-red-600 hover:underline" @click="lines.splice(index, 1)">{{ __('Remove') }}</button>
                    </div>
                </template>

                @foreach ($errors->get('lines*') as $messages)
                    <x-input-error :messages="$messages" />
                @endforeach

                <button type="button" x-show="lines.length < max" class="px-3 py-2 text-sm font-semibold rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50" @click="lines.push({ name: '', rate: '' })">{{ __('Add a tax') }}</button>
            </section>

            <section class="space-y-3">
                <h3 class="font-semibold text-gray-800">{{ __('Your prices') }}</h3>
                <label class="flex items-start gap-3">
                    <input type="radio" name="inclusive" value="1" @change="inclusive = true" @checked(old('inclusive', $config['inclusive'] ? '1' : '0') === '1') class="mt-1">
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">{{ __('Already include tax') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('Menu prices stay as they are; the receipt shows how much of the total is tax.') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-3">
                    <input type="radio" name="inclusive" value="0" @change="inclusive = false" @checked(old('inclusive', $config['inclusive'] ? '1' : '0') === '0') class="mt-1">
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">{{ __('Tax is added on top') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('The tax is added to the total at the till and at checkout.') }}</span>
                    </span>
                </label>
                <p class="text-xs text-gray-600 bg-gray-50 rounded-md px-3 py-2" x-show="example()" x-text="example()" x-cloak></p>
                <x-input-error :messages="$errors->get('inclusive')" />
            </section>


            <section class="space-y-1">
                <h3 class="font-semibold text-gray-800">{{ __('On receipts') }}</h3>
                <p class="text-sm text-gray-600">
                    @if ($tin)
                        {{ __('Receipts show your TIN, :tin.', ['tin' => $tin]) }}
                    @else
                        {{ __('No TIN yet.') }}
                    @endif
                    <a href="{{ route('dashboard.business-profile.edit') }}" class="text-indigo-700 font-semibold hover:underline">{{ $tin ? __('Change it in Business profile') : __('Add it in Business profile') }}</a>
                </p>
                <p class="text-xs text-gray-500">{{ __('Only food is taxed: delivery fees, Bolt Food orders and freebies carry no tax.') }}</p>
            </section>

            <div class="flex justify-end">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>

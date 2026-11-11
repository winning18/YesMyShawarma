<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Market list kinds') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('dashboard.market-lists.index') }}" class="text-sm text-gray-500 hover:underline">&larr; {{ __('Market list') }}</a>

            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $errors->first() }}</div>
            @endif

            <p class="text-sm text-gray-500">{{ __('Kinds group purchases in reports (all the chicken, all the transport…). Keywords make a kind get suggested as staff type a description. Hiding a kind stops it being offered; past purchases keep it.') }}</p>

            @if ($matchingKind && $matches->isNotEmpty())
                <form method="POST" action="{{ route('dashboard.market-lists.kinds.move-items', $matchingKind) }}" class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-3" x-data="{ all: true }">
                    @csrf
                    <p class="text-sm text-amber-900 font-semibold">
                        {{ trans_choice(':count past item under Others matches :kind. Move it?|:count past items under Others match :kind. Move them?', $matches->count(), ['count' => $matches->count(), 'kind' => $matchingKind->name]) }}
                    </p>
                    <p class="text-xs text-amber-800">{{ __('Totals don\'t change, only which kind they count under in reports. Approved lists included; each one notes the move in its history.') }}</p>
                    <ul class="text-sm space-y-1 max-h-64 overflow-y-auto">
                        @foreach ($matches as $item)
                            <li>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="items[]" value="{{ $item->id }}" checked class="rounded border-gray-300">
                                    <span>{{ $item->description }}</span>
                                    <span class="text-xs text-gray-500">· {{ $item->marketList?->purchased_on?->format('d M Y') }} · {{ $item->marketList?->branch?->name }} · GH₵{{ number_format($item->amount / 100, 2) }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white text-sm font-semibold rounded-md hover:bg-amber-700">{{ __('Move ticked items to :kind', ['kind' => $matchingKind->name]) }}</button>
                </form>
            @endif

            <div class="bg-white shadow rounded-lg divide-y divide-gray-100">
                @foreach ($kinds as $kind)
                    @php $isOthers = $kind->name === \App\Models\ExpenseCategory::OTHERS; @endphp
                    <div class="p-4 {{ $kind->is_active ? '' : 'bg-gray-50' }}" x-data="{ editing: false }">
                        <div class="flex flex-wrap items-start justify-between gap-3" x-show="! editing">
                            <div class="min-w-0">
                                <p class="font-semibold {{ $kind->is_active ? 'text-gray-800' : 'text-gray-400' }}">
                                    {{ $kind->name }}
                                    @unless ($kind->is_active) <span class="text-xs font-normal">({{ __('hidden') }})</span> @endunless
                                </p>
                                <p class="text-xs text-gray-500 break-words">
                                    {{ $isOthers ? __('Anything that matches no other kind.') : (empty($kind->keywords) ? __('No keywords: only chosen by hand.') : implode(', ', $kind->keywords)) }}
                                </p>
                            </div>
                            @unless ($isOthers)
                                <div class="flex items-center gap-3 text-sm shrink-0">
                                    @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                                        <form method="POST" action="{{ route('dashboard.market-lists.kinds.move', $kind) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="{{ $direction }}">
                                            <button type="submit" class="text-gray-500 hover:text-gray-800" aria-label="{{ $direction === 'up' ? __('Move up') : __('Move down') }}">{{ $arrow }}</button>
                                        </form>
                                    @endforeach
                                    <button type="button" @click="editing = true" class="text-indigo-600 hover:underline">{{ __('Edit') }}</button>
                                    <form method="POST" action="{{ route('dashboard.market-lists.kinds.toggle', $kind) }}">
                                        @csrf
                                        <button type="submit" class="{{ $kind->is_active ? 'text-gray-500' : 'text-green-700' }} hover:underline">{{ $kind->is_active ? __('Hide') : __('Show') }}</button>
                                    </form>
                                </div>
                            @endunless
                        </div>
                        @unless ($isOthers)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('dashboard.market-lists.kinds.update', $kind) }}" class="space-y-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" required maxlength="60" value="{{ $kind->name }}" class="w-full sm:w-64 rounded-md border-gray-300 text-sm" aria-label="{{ __('Name') }}">
                                <input type="text" name="keywords" maxlength="1000" value="{{ implode(', ', $kind->keywords ?? []) }}" placeholder="{{ __('Keywords, separated by commas') }}" class="w-full rounded-md border-gray-300 text-sm" aria-label="{{ __('Keywords') }}">
                                <div class="flex gap-3">
                                    <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Save') }}</button>
                                    <button type="button" @click="editing = false" class="text-sm text-gray-500 hover:underline">{{ __('Cancel') }}</button>
                                </div>
                            </form>
                        @endunless
                    </div>
                @endforeach
            </div>

            <div class="bg-white shadow rounded-lg p-4">
                <h3 class="font-semibold text-gray-800 mb-3">{{ __('Add a kind') }}</h3>
                <form method="POST" action="{{ route('dashboard.market-lists.kinds.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="name" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Name') }} <span class="text-red-600">*</span></label>
                        <input id="name" type="text" name="name" required maxlength="60" value="{{ old('name') }}" placeholder="{{ __('e.g. Cheese') }}" class="w-full sm:w-64 rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label for="keywords" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Keywords (optional)') }}</label>
                        <input id="keywords" type="text" name="keywords" maxlength="1000" value="{{ old('keywords') }}" placeholder="{{ __('e.g. cheese, mozzarella, cheddar') }}" class="w-full rounded-md border-gray-300 text-sm">
                        <p class="text-xs text-gray-400 mt-1">{{ __('Separate words with commas. A description containing one of these words gets this kind suggested.') }}</p>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Add kind') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

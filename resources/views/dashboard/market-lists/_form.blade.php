{{--
    Shared by the record form (index) and the edit page. $rows is the
    starting set of item rows; $list is null when recording a new one.
    The kind suggestion mirrors ExpenseKindMatcher exactly: the longest
    keyword found at the start of a word wins, else Others — but once
    someone picks a kind by hand, typing never overrides it.
--}}
@php
    $kindData = $kinds->map(fn ($kind) => ['id' => $kind->id, 'name' => $kind->name, 'keywords' => $kind->keywords ?? []])->values();
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4"
    x-data="marketListForm(@js($rows), @js($kindData))">
    @csrf
    @isset($list)
        @method('PUT')
    @endisset

    <div class="flex flex-wrap gap-4">
        @if (! isset($list) && $branchOptions->isNotEmpty())
            <div>
                <label for="branch_id" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Branch') }} <span class="text-red-600">*</span></label>
                <select id="branch_id" name="branch_id" required class="rounded-md border-gray-300 text-sm">
                    @foreach ($branchOptions as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div>
                <span class="block text-xs font-medium text-gray-500 mb-1">{{ __('Branch') }}</span>
                <span class="block text-sm py-2">{{ isset($list) ? $list->branch->name : $currentBranch?->name }}</span>
            </div>
        @endif

        <div>
            <label for="purchased_on" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Date bought') }} <span class="text-red-600">*</span></label>
            <input id="purchased_on" type="date" name="purchased_on" required max="{{ $today }}"
                value="{{ old('purchased_on', isset($list) ? $list->purchased_on->toDateString() : $today) }}"
                class="rounded-md border-gray-300 text-sm">
        </div>
    </div>

    {{-- Stacked per item on a phone (staff record this at the market), one line per item from sm up. --}}
    <div class="space-y-3 sm:space-y-1">
        <div class="hidden sm:grid sm:grid-cols-[2fr_1.4fr_0.7fr_0.8fr_1fr_1.5rem] gap-2 text-xs font-medium text-gray-500">
            <span>{{ __('Item or expense') }} <span class="text-red-600">*</span></span>
            <span>{{ __('Kind') }}</span>
            <span>{{ __('Quantity') }}</span>
            <span>{{ __('Unit') }}</span>
            <span>{{ __('Amount (GH₵)') }} <span class="text-red-600">*</span></span>
            <span></span>
        </div>
        <template x-for="(row, index) in rows" :key="row.key">
            <div class="grid grid-cols-2 sm:grid-cols-[2fr_1.4fr_0.7fr_0.8fr_1fr_1.5rem] gap-2 items-center border border-gray-100 sm:border-0 rounded-md p-2 sm:p-0">
                <input type="text" :name="`items[${index}][description]`" x-model="row.description" @input="suggest(row)"
                    maxlength="255" placeholder="{{ __('e.g. Chicken, Trotro to market') }}" aria-label="{{ __('Item or expense') }}"
                    class="col-span-2 sm:col-span-1 w-full rounded-md border-gray-300 text-sm">
                <select :name="`items[${index}][expense_category_id]`" x-model.number="row.kind" @change="row.kindTouched = true" aria-label="{{ __('Kind') }}"
                    class="w-full rounded-md border-gray-300 text-sm">
                    <template x-for="kind in kinds" :key="kind.id">
                        <option :value="kind.id" x-text="kind.name" :selected="kind.id === row.kind"></option>
                    </template>
                </select>
                <input type="number" :name="`items[${index}][amount]`" x-model="row.amount" min="0" step="0.01" inputmode="decimal"
                    placeholder="{{ __('GH₵') }}" aria-label="{{ __('Amount (GH₵)') }}"
                    class="sm:order-last w-full rounded-md border-gray-300 text-sm">
                <input type="number" :name="`items[${index}][quantity]`" x-model="row.quantity" min="0" step="0.01" inputmode="decimal"
                    placeholder="{{ __('Qty') }}" aria-label="{{ __('Quantity') }}"
                    class="w-full rounded-md border-gray-300 text-sm">
                <input type="text" :name="`items[${index}][unit]`" x-model="row.unit" maxlength="30" placeholder="{{ __('kg, crate…') }}" aria-label="{{ __('Unit') }}"
                    class="w-full rounded-md border-gray-300 text-sm">
                <button type="button" @click="remove(index)" x-show="rows.length > 1"
                    class="col-span-2 sm:col-span-1 sm:order-last text-xs sm:text-base text-gray-400 hover:text-red-600 text-right"
                    aria-label="{{ __('Remove row') }}"><span class="sm:hidden">{{ __('Remove') }}</span><span class="hidden sm:inline">&times;</span></button>
            </div>
        </template>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <button type="button" @click="add()" class="text-sm font-semibold text-indigo-600 hover:underline">+ {{ __('Add another item') }}</button>
        <p class="text-sm text-gray-700">{{ __('Total') }}: <strong x-text="'GH₵' + total()"></strong></p>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="receipt" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Receipt or list photo (optional)') }}</label>
            <input id="receipt" type="file" name="receipt" accept="image/*" class="text-sm">
            @if (isset($list) && $list->receipt_path)
                <label class="flex items-center gap-2 text-xs text-gray-500 mt-2">
                    <input type="checkbox" name="remove_receipt" value="1" class="rounded border-gray-300">
                    {{ __('Remove the current photo') }}
                    (<a href="{{ route('dashboard.market-lists.receipt', $list) }}" target="_blank" class="text-indigo-600 hover:underline">{{ __('view') }}</a>)
                </label>
            @endif
        </div>
        <div>
            <label for="note" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Note (optional)') }}</label>
            <textarea id="note" name="note" rows="2" maxlength="1000" class="w-full rounded-md border-gray-300 text-sm">{{ old('note', $list->note ?? '') }}</textarea>
        </div>
    </div>

    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ $submitLabel }}</button>
</form>

@once
    <script>
        function marketListForm(rows, kinds) {
            let nextKey = 0;
            const others = kinds.find((kind) => kind.name === 'Others') ?? kinds[kinds.length - 1];
            const escape = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

            const blank = () => ({ key: nextKey++, description: '', kind: others?.id, kindTouched: false, quantity: '', unit: '', amount: '' });

            return {
                kinds,
                rows: rows.length ? rows.map((row) => ({ ...blank(), ...row, key: nextKey++, kindTouched: !!row.kind })) : [blank()],

                match(description) {
                    const text = description.toLowerCase();
                    let best = others;
                    let bestLength = 0;

                    for (const kind of kinds) {
                        for (const keyword of kind.keywords) {
                            if (keyword.length > bestLength && new RegExp('(^|[^\\p{L}\\p{N}])' + escape(keyword), 'u').test(text)) {
                                best = kind;
                                bestLength = keyword.length;
                            }
                        }
                    }

                    return best;
                },
                suggest(row) {
                    if (!row.kindTouched) row.kind = this.match(row.description)?.id;
                },
                add() {
                    this.rows.push(blank());
                },
                remove(index) {
                    this.rows.splice(index, 1);
                },
                total() {
                    return this.rows.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0).toFixed(2);
                },
            };
        }
    </script>
@endonce

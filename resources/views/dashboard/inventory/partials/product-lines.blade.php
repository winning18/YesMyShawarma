{{--
    Products chosen for a purchase order or a transfer (R4): search, pick the
    product or one of its packs, a quantity (and a cost for buying). Posts
    lines[i][menu_item_id], [pack_id], [quantity] / [units], [unit_cost].
    $mode: 'buy' or 'send'.
--}}
<div x-data="productLines(@js($mode), @js(route('dashboard.inventory.products')))" class="space-y-3">
    <div class="relative">
        <input type="search" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="pickFirst()" autocomplete="off"
            placeholder="{{ __('Search or scan a product to add') }}" class="w-full rounded-md border-gray-300 text-sm">
        <ul x-show="results.length" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow divide-y divide-gray-100 max-h-72 overflow-y-auto">
            <template x-for="p in results" :key="p.id">
                <li class="p-2 text-sm flex flex-wrap items-center gap-2">
                    <span class="flex-1 font-medium" x-text="p.name"></span>
                    <button type="button" @click="add(p, null)" class="px-2 py-1 border border-gray-300 rounded text-xs" x-text="p.unit"></button>
                    <template x-if="mode === 'buy'">
                        <template x-for="k in p.packs" :key="k.id">
                            <button type="button" @click="add(p, k)" class="px-2 py-1 border border-gray-300 rounded text-xs" x-text="k.name"></button>
                        </template>
                    </template>
                </li>
            </template>
        </ul>
    </div>

    <table class="w-full text-sm" x-show="lines.length" x-cloak>
        <thead class="text-left text-gray-500"><tr>
            <th class="py-1 font-medium">{{ __('Product') }}</th>
            <th class="py-1 font-medium" x-text="mode === 'buy' ? @js(__('Quantity')) : @js(__('Units to send'))"></th>
            <th class="py-1 font-medium" x-show="mode === 'buy'">{{ __('Cost each (GH₵)') }}</th>
            <th></th>
        </tr></thead>
        <tbody>
            <template x-for="(line, i) in lines" :key="line.key">
                <tr class="border-t border-gray-100">
                    <td class="py-2 pr-2">
                        <span x-text="line.label"></span>
                        <input type="hidden" :name="`lines[${i}][menu_item_id]`" :value="line.menu_item_id">
                        <input type="hidden" :name="`lines[${i}][pack_id]`" :value="line.pack_id ?? ''">
                    </td>
                    <td class="py-2 pr-2"><input type="number" min="1" required x-model.number="line.quantity" :name="mode === 'buy' ? `lines[${i}][quantity]` : `lines[${i}][units]`" class="w-24 rounded-md border-gray-300 text-sm"></td>
                    <td class="py-2 pr-2" x-show="mode === 'buy'"><input type="number" min="0" step="0.01" :required="mode === 'buy'" x-model="line.unit_cost" :name="`lines[${i}][unit_cost]`" class="w-28 rounded-md border-gray-300 text-sm"></td>
                    <td class="py-2 text-right"><button type="button" @click="lines.splice(i, 1)" class="text-xs text-red-600 hover:underline">{{ __('Remove') }}</button></td>
                </tr>
            </template>
        </tbody>
        <tfoot x-show="mode === 'buy'"><tr class="border-t border-gray-200 font-semibold"><td class="py-2">{{ __('Total') }}</td><td></td><td class="py-2" x-text="'GH₵' + total().toFixed(2)"></td><td></td></tr></tfoot>
    </table>
    <p x-show="!lines.length" class="text-sm text-gray-500">{{ __('No products added yet.') }}</p>
</div>

@once
<script>
    function productLines(mode, url) {
        return {
            mode, query: '', results: [], lines: [], next: 1,
            async search() {
                const q = this.query.trim();
                if (!q) { this.results = []; return; }
                const r = await fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                this.results = r.ok ? (await r.json()).products : [];
            },
            pickFirst() { if (this.results.length === 1) this.add(this.results[0], null); },
            add(p, pack) {
                const cost = pack ? pack.cost : p.cost;
                this.lines.push({ key: this.next++, menu_item_id: p.id, pack_id: pack ? pack.id : null, label: p.name + (pack ? ' (' + pack.name + ')' : ' (' + p.unit + ')'), quantity: 1, unit_cost: cost === null ? '' : (cost / 100).toFixed(2) });
                this.query = ''; this.results = [];
            },
            total() { return this.lines.reduce((s, l) => s + (parseFloat(l.unit_cost) || 0) * (l.quantity || 0), 0); },
        };
    }
</script>
@endonce

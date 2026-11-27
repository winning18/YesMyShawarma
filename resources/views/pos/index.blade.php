<x-app-layout>
    <x-slot name="header">
        @include('dashboard._channel-header', ['title' => __('POS'), 'active' => 'pos', 'isStaff' => $isStaff, 'forceShiftStart' => $forceShiftStart, 'ordersUrl' => $ordersUrl, 'branchId' => $branch->id])
    </x-slot>

    <div
        class="py-8"
        x-data="posTerminal()"
        x-init="init()"
    >
        {{--
            lg+ only: each column scrolls independently within its own fixed
            viewport-relative height, rather than the whole page scrolling —
            a POS terminal is a fixed-height, landscape/desktop tool, so the
            menu grid and the cart/checkout panel each keep their own
            scrollbar. Below lg, columns stack and the page scrolls normally
            (cramming two independent scroll regions into a phone-width
            screen is worse, not better).
        --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-8 lg:h-[calc(100vh-6rem)]">
            {{-- Menu picker --}}
            <div class="lg:col-span-2 lg:h-full lg:overflow-y-auto lg:pr-2 space-y-8">
                {{-- One line: branch, the Tables / Quick sale switch, and Add table (owner's "How the POS works" setting). --}}
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">{{ $branch->name }}</p>
                    @module('tables')
                    <div class="flex flex-wrap items-center gap-2">
                        <form x-show="view === 'tables'" @submit.prevent="addTable()" class="flex gap-2">
                            <input type="text" x-model="newTableName" maxlength="40" placeholder="{{ __('New table, e.g. 7') }}" class="w-36 rounded-md border-gray-300 text-sm py-1.5">
                            <button type="submit" class="px-3 py-1.5 text-sm font-semibold rounded-md bg-gray-800 text-white hover:bg-gray-900">{{ __('Add table') }}</button>
                        </form>
                        <div x-show="posMode !== 'quick'" class="inline-flex rounded-md border border-gray-200 bg-white p-0.5 text-sm">
                            <button type="button" @click="showTables()" class="px-3 py-1 rounded" :class="view === 'tables' ? 'bg-gray-800 text-white' : 'text-gray-600'">{{ __('Tables') }}</button>
                            <button type="button" x-show="posMode === 'both'" @click="quickSale()" class="px-3 py-1 rounded" :class="view === 'menu' && !table ? 'bg-gray-800 text-white' : 'text-gray-600'">{{ __('Quick sale') }}</button>
                            <button type="button" x-show="table" x-cloak class="px-3 py-1 rounded bg-amber-500 text-white" x-text="@js(__('Table')) + ' ' + (table?.name ?? '')"></button>
                        </div>
                    </div>
                    @endmodule
                </div>

                @module('tables')
                {{-- Tables (2026-11-20): square cards, free or in use with what's not yet paid. --}}
                <section x-show="view === 'tables'" x-cloak class="space-y-4">
                    <div x-show="tableError" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3" x-text="tableError"></div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        <template x-for="t in tables" :key="t.id">
                            <div class="aspect-square rounded-xl border-2 p-3 flex flex-col" :class="inUse(t) ? 'bg-amber-50 border-amber-400' : 'bg-white border-gray-200'">
                                <div class="flex items-start justify-between gap-1">
                                    <p class="text-2xl font-bold text-gray-900 leading-tight break-words" x-text="t.name"></p>
                                    <div class="flex gap-1 shrink-0">
                                        <button type="button" @click="renameTable(t)" class="p-1 text-gray-400 hover:text-gray-700" title="{{ __('Rename') }}" aria-label="{{ __('Rename') }}">
                                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                                        </button>
                                        <button type="button" x-show="!inUse(t)" @click="deleteTable(t)" class="p-1 text-gray-400 hover:text-red-600" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <span class="mt-1 inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-semibold" :class="inUse(t) ? 'bg-amber-200 text-amber-900' : 'bg-green-100 text-green-800'" x-text="inUse(t) ? @js(__('In use')) : @js(__('Free'))"></span>
                                <p x-show="t.unsent > 0" class="mt-1 text-xs font-semibold text-amber-800" x-text="@js(__('Not sent yet:')) + ' ' + t.unsent + ' ' + (t.unsent === 1 ? @js(__('item')) : @js(__('items')))"></p>
                                <template x-if="t.tab">
                                    <div class="mt-2 text-sm space-y-0.5">
                                        <p class="font-semibold text-red-700" x-text="@js(__('Not paid')) + ' ' + formatMoney(t.tab.total)"></p>
                                        <p class="text-xs text-gray-600 truncate" x-show="t.tab.customer_name" x-text="t.tab.customer_name"></p>
                                        <p class="text-xs text-gray-500" x-text="t.tab.rounds + ' ' + (t.tab.rounds === 1 ? @js(__('round')) : @js(__('rounds'))) + ' · ' + @js(__('since')) + ' ' + t.tab.opened_at"></p>
                                    </div>
                                </template>
                                <div class="flex gap-2 mt-auto pt-2">
                                    <button type="button" @click="chooseTable(t)" class="flex-1 px-2 py-2 text-xs font-semibold rounded-md bg-gray-800 text-white hover:bg-gray-900" x-text="t.unsent > 0 ? @js(__('Continue')) : (t.tab ? @js(__('Add a round')) : @js(__('Order')))"></button>
                                    <button type="button" x-show="t.tab" @click="openBill(t)" class="flex-1 px-2 py-2 text-xs font-semibold rounded-md bg-green-600 text-white hover:bg-green-700">{{ __('Bill') }}</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p class="text-sm text-gray-500" x-show="tables.length === 0">{{ __('No tables yet. Add one at the top: a number or a name.') }}</p>
                </section>
                @endmodule

                {{-- A pharmacy or shop scans and searches instead (R3). --}}
                @unlessrestaurant
                    @include('pos.partials.retail-picker')
                @endrestaurant

                <div x-show="view === 'menu'" class="space-y-8">
                @restaurant
                @forelse ($categories as $group)
                    <section>
                        <h3 class="font-semibold text-gray-800 mb-3">{{ $group['category']->name }}</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach ($group['items'] as $item)
                                <button
                                    type="button"
                                    @click="openItem(@js([
                                        'id' => $item->id,
                                        'name' => $item->name,
                                        'price' => $item->base_price,
                                        'optionGroups' => $item->optionGroups->map(fn ($g) => [
                                            'id' => $g->id,
                                            'name' => $g->name,
                                            'min_select' => $g->min_select,
                                            'max_select' => $g->max_select,
                                            'is_required' => $g->is_required,
                                            'options' => $g->options->map(fn ($o) => [
                                                'id' => $o->id, 'name' => $o->name, 'price_delta' => $o->price_delta,
                                            ])->values(),
                                        ])->values(),
                                    ]))"
                                    class="bg-white border border-gray-200 rounded-lg p-3 text-left hover:border-gray-400 flex flex-col gap-2"
                                >
                                    <x-product-image :item="$item" class="w-full" />
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $item->name }}</p>
                                        <p class="text-sm text-gray-500">GH₵{{ number_format($item->base_price / 100, 2) }}</p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <p class="text-sm text-gray-500">{{ __('Nothing available to sell at this branch right now.') }}</p>
                @endforelse
                @endrestaurant
                </div>
            </div>

            {{-- Cart + checkout --}}
            <div class="bg-white shadow rounded-lg p-4 lg:h-full lg:overflow-y-auto">
                <h3 class="font-semibold text-gray-800 mb-3">{{ __('Order') }}</h3>
                @unlessrestaurant
                    @include('pos.partials.retail-actions')
                @endrestaurant

                {{-- On the Tables screen with no table chosen: point to the tables, not a quick-sale form. --}}
                <p x-show="!table && view === 'tables'" x-cloak class="text-sm text-gray-500"
                    x-text="posMode === 'both' ? @js(__('Choose a table to take its order, or Quick sale for a takeaway.')) : @js(__('Choose a table to take its order.'))"></p>

                <div x-show="table || view === 'menu'">

                @module('tables')
                <div x-show="table" x-cloak class="mb-4 rounded-md bg-amber-50 border border-amber-300 px-3 py-2 text-sm flex items-center justify-between gap-2">
                    <span><span class="font-semibold" x-text="@js(__('Table')) + ' ' + (table?.name ?? '')"></span> · {{ __('a round, paid on the bill') }}</span>
                    <button type="button" @click="clearTable()" class="text-xs text-gray-600 underline">{{ __('Back to tables') }}</button>
                </div>
                @endmodule

                {{--
                    Payment first, above the items: a Bolt Food order is chosen
                    before anything is added, so the buy-2-get-1 FREE line
                    (never given on Bolt Food, see visibleLines()) doesn't show
                    while staff build one (owner, 2026-11-12).
                --}}
                <div class="mb-4 pb-3 border-b border-gray-100" x-show="!table">
                    <label class="block text-xs font-medium text-gray-500 mb-2">{{ __('Payment') }} <span class="text-red-600">*</span></label>
                    <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                        <label class="flex items-center gap-1.5">
                            <input type="radio" x-model="paymentMethod" value="cash"> {{ __('Cash') }}
                        </label>
                        <label class="flex items-center gap-1.5">
                            <input type="radio" x-model="paymentMethod" value="momo"> {{ __('Momo') }}
                        </label>
                        @module('bolt_food')
                        <label class="flex items-center gap-1.5">
                            <input type="radio" x-model="paymentMethod" value="bolt_food" @change="fulfilmentType = 'pickup'; whatsapp = false"> <span class="whitespace-nowrap">{{ __('Bolt Food') }}</span>
                        </label>
                        @endmodule
                        @module('freebies')
                        <label class="flex items-center gap-1.5">
                            <input type="radio" x-model="paymentMethod" value="complimentary" @change="whatsapp = false"> {{ __('Freebie') }}
                        </label>
                        @endmodule
                    </div>
                    @module('bolt_food')
                    <p class="text-xs text-gray-500 mt-1" x-show="paymentMethod === 'bolt_food'">
                        {{ __('For an order placed through Bolt Food and brought here to prepare — not counted as this till\'s sales.') }}
                    </p>
                    @endmodule

                    {{-- WhatsApp group orders: GH₵10 off the food; deliverable since 2026-11-20. --}}
                    @module('whatsapp_reward')
                    <label class="flex items-center gap-2 text-sm mt-3" x-show="paymentMethod === 'cash' || paymentMethod === 'momo'">
                        <input type="checkbox" x-model="whatsapp" class="rounded border-gray-300">
                        <span>{{ __('WhatsApp group order') }} <span class="text-gray-500" x-text="'(' + formatMoney(whatsappReward) + ' ' + @js(__('off the food')) + ')'"></span></span>
                    </label>
                    @endmodule

                    @module('freebies')
                    {{-- Freebies: free to the customer, never sales, approved by a manager afterwards. --}}
                    <div x-show="paymentMethod === 'complimentary'" x-cloak class="mt-3 space-y-2 rounded-md bg-amber-50 border border-amber-200 p-3">
                        <p class="text-xs text-amber-900">{{ __('The customer pays nothing. It\'s not counted as sales, and a manager approves it afterwards.') }}</p>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-1.5"><input type="radio" x-model="freebieRecipientType" value="customer"> {{ __('For a customer') }}</label>
                            <label class="flex items-center gap-1.5"><input type="radio" x-model="freebieRecipientType" value="staff"> {{ __('For staff') }}</label>
                        </div>
                        <p class="text-xs text-gray-600" x-show="freebieRecipientType === 'customer'">{{ __('Enter their name or phone below.') }}</p>
                        <select x-show="freebieRecipientType === 'staff'" x-model="freebieStaffId" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('Choose the staff member') }}</option>
                            @foreach ($freebieStaff as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" x-model="freebieReason" maxlength="255" placeholder="{{ __('Why? e.g. Birthday, Staff meal, Complaint make-good') }}" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    @endmodule
                </div>

                <p class="text-sm text-gray-500" x-show="lines.length === 0">{{ __('No items yet.') }}</p>

                <ul class="space-y-3 mb-4 text-sm" x-show="lines.length > 0">
                    <template x-for="line in visibleLines()" :key="line.line_id">
                        <li class="flex justify-between items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-gray-800 truncate">
                                    <span x-text="line.name_snapshot"></span>
                                    <span x-show="line.is_free" class="ml-1 text-xs font-semibold text-green-700 bg-green-50 rounded px-1.5 py-0.5">{{ __('FREE') }}</span>
                                </p>
                                <p class="text-xs text-gray-500" x-show="line.options.length" x-text="line.options.map(o => o.name_snapshot + (o.quantity > 1 ? ' x' + o.quantity : '')).join(', ')"></p>
                                @unlessrestaurant
                                    @include('pos.partials.retail-line')
                                @else
                                <div class="flex items-center gap-2 mt-1" x-show="!line.is_free">
                                    <button type="button" @click="changeQuantity(line.line_id, line.quantity - 1)" class="w-5 h-5 flex items-center justify-center border border-gray-300 rounded text-xs text-gray-600">-</button>
                                    <span class="text-xs w-4 text-center" x-text="line.quantity"></span>
                                    <button type="button" @click="changeQuantity(line.line_id, line.quantity + 1)" class="w-5 h-5 flex items-center justify-center border border-gray-300 rounded text-xs text-gray-600">+</button>
                                </div>
                                @endrestaurant
                                {{-- A free line's quantity is entirely
                                     derived from its paid sibling lines —
                                     never independently editable or
                                     removable, it just disappears on its
                                     own the next time the cart no longer
                                     earns one. --}}
                                <p class="text-xs text-gray-500 mt-1" x-show="line.is_free" x-text="'{{ __('Qty') }} ' + line.quantity"></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-gray-800" x-text="line.is_free ? '{{ __('FREE') }}' : formatMoney(line.line_total)"></p>
                                <button type="button" x-show="!line.is_free" @click="removeLine(line.line_id)" class="text-xs text-red-600 hover:underline mt-1">{{ __('Remove') }}</button>
                            </div>
                        </li>
                    </template>
                </ul>

                <div x-show="promotionBanner && paymentMethod !== 'bolt_food' && paymentMethod !== 'complimentary'" x-cloak class="text-sm text-green-700 bg-green-50 rounded-md px-3 py-2 mb-3" x-text="promotionBanner"></div>

                <div class="flex justify-between border-t border-gray-100 pt-3">
                    <span>{{ __('Subtotal') }}</span>
                    <span x-text="formatMoney(subtotal)"></span>
                </div>

                <div class="flex justify-between text-sm mt-2" x-show="displayedDiscount() > 0" x-cloak>
                    <span x-text="paymentMethod === 'complimentary' ? @js(__('Freebie')) : (whatsappDiscount() > 0 ? @js(__('WhatsApp reward')) : @js(__('Discount')))"></span>
                    <span x-text="'-' + formatMoney(displayedDiscount())"></span>
                </div>

                <template x-for="line in tax().lines.filter((line) => !line.inclusive)" :key="line.label">
                    <div class="flex justify-between text-sm mt-2">
                        <span x-text="line.label"></span>
                        <span x-text="formatMoney(line.amount)"></span>
                    </div>
                </template>

                <div class="flex justify-between font-semibold border-t border-gray-100 pt-3 mt-2" :class="tax().lines.some((line) => line.inclusive) ? 'mb-1' : 'mb-4'">
                    <span>{{ __('Total') }}</span>
                    <span x-text="formatMoney(subtotal - displayedDiscount() + tax().added)"></span>
                </div>
                <template x-if="tax().lines.some((line) => line.inclusive)">
                    <div class="mb-4">
                        <template x-for="line in tax().lines" :key="line.label">
                            <div class="flex justify-between text-xs text-gray-500">
                                <span x-text="@js(__('Includes')) + ' ' + line.label"></span>
                                <span x-text="formatMoney(line.amount)"></span>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="space-y-3 border-t border-gray-100 pt-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Customer phone (optional)') }}</label>
                        <input
                            type="tel" inputmode="numeric" autocomplete="off"
                            :value="phone.formatted" @input="phone.onInput($event)" @blur="phone.onBlur()"
                            placeholder="024-123-4567" maxlength="12"
                            class="w-full rounded-md text-sm"
                            :class="phone.raw.length > 0 && phone.invalid ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'"
                        >
                        {{--
                            Optional here, unlike every other phoneField()
                            use — empty is valid (a walk-in who skips it), a
                            half-typed number still isn't.
                        --}}
                        <p class="text-xs text-red-600 mt-1" x-show="phone.raw.length > 0 && phone.invalid">{{ __('Enter a 10-digit phone number, or leave it blank.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Customer name (optional)') }}</label>
                        <input type="text" x-model="name" class="w-full rounded-md border-gray-300 text-sm">
                    </div>

                    <div x-show="!table">
                        <label class="block text-xs font-medium text-gray-500 mb-2">{{ __('Fulfilment') }} <span class="text-red-600">*</span></label>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-1.5">
                                <input type="radio" x-model="fulfilmentType" value="pickup"> {{ __('Pickup') }}
                            </label>
                            {{-- Dine in (owner, 2026-11-20); never for Bolt Food, whose courier collects it. A restaurant's only (R1). --}}
                            @restaurant
                            <label class="flex items-center gap-1.5" x-show="paymentMethod !== 'bolt_food'">
                                <input type="radio" x-model="fulfilmentType" value="dine_in"> {{ __('Dine in') }}
                            </label>
                            @endrestaurant
                            @if ($deliveryAvailable)
                                <label class="flex items-center gap-1.5" x-show="!pickupOnly()">
                                    <input type="radio" x-model="fulfilmentType" value="delivery"> {{ __('Delivery') }}
                                </label>
                            @endif
                        </div>
                        @module('bolt_food')
                        <p class="text-xs text-gray-500 mt-1" x-show="paymentMethod === 'bolt_food'">
                            {{ __('Bolt Food orders are always pickup — Bolt handles delivery themselves.') }}
                        </p>
                        @endmodule
                    </div>

                    @if ($deliveryAvailable)
                        <div x-show="fulfilmentType === 'delivery'" x-cloak class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Delivery area') }} <span class="text-red-600">*</span></label>
                                <x-area-picker :areas="$deliveryAreas" model="areaId" :placeholder="__('Search areas…')" />
                            </div>
                            <div x-show="areaId === 'other'" x-cloak>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Area name') }}</label>
                                <input type="text" x-model="areaOther" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Landmark') }} <span class="text-red-600">*</span></label>
                                <input type="text" x-model="landmark" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('GhanaPost GPS code (optional)') }}</label>
                                <input type="text" x-model="ghanapostCode" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <p class="text-xs text-gray-500">{{ __('No live location capture at POS. The delivery fee is priced when the rider marks it delivered.') }}</p>
                        </div>
                    @endif

                    <div x-show="paymentMethod === 'momo'" x-cloak>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ $checksMomo ? __('Momo transaction ID') : __('Momo transaction ID (optional)') }}</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="paymentReference" class="w-full rounded-md border-gray-300 text-sm" placeholder="{{ __('e.g. from the Momo confirmation SMS') }}">
                            <button
                                type="button" @click="paymentReference = ''"
                                x-show="paymentReference && @js($checksMomo)"
                                class="shrink-0 px-3 py-1.5 text-xs text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
                            >{{ __('Skip') }}</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $checksMomo ? __("Busy? Leave this blank and enter it later from the order. It won't be marked paid until you do.") : __('MoMo is marked paid at once; the ID is just for your records.') }}</p>
                    </div>

                    <div x-show="asksFeePayer()" x-cloak>
                        <label class="block text-xs font-medium text-gray-500 mb-2">{{ __('Delivery fee paid') }} <span class="text-red-600">*</span></label>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-1.5">
                                <input type="radio" x-model="deliveryFeePaidBy" value="momo"> <span x-text="paymentMethod === 'complimentary' ? @js(__('By MoMo, now')) : @js(__('By MoMo, with the food'))"></span>
                            </label>
                            <label class="flex items-center gap-1.5">
                                <input type="radio" x-model="deliveryFeePaidBy" value="rider_cash"> {{ __('Cash to the rider') }}
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ __('If paid by MoMo, the rider collects nothing at the door and gets the delivery fee from staff.') }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Instructions (optional)') }}</label>
                        <textarea x-model="instructions" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    </div>

                    <div x-show="error" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3" x-text="error"></div>

                    <button
                        type="button" @click="placeOrder()"
                        :disabled="lines.length === 0 || submitting"
                        class="w-full px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 disabled:opacity-50"
                    ><span x-text="submitting ? '{{ __('Placing…') }}' : (table ? @js(__('Send round to the kitchen')) : '{{ __('Place order') }}')"></span></button>
                </div>
                </div>
            </div>
        </div>

        @module('tables')
        {{-- A table's bill: its rounds, settle it (cash or MoMo), move it, print it. --}}
        <div x-show="bill" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="bill = null">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto p-5" @click.outside="bill = null">
                <template x-if="bill">
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 class="font-semibold text-gray-800" x-text="@js(__('Table')) + ' ' + bill.table"></h3>
                            <span class="text-xs text-gray-500" x-text="@js(__('since')) + ' ' + bill.opened_at"></span>
                        </div>
                        <p class="text-sm font-semibold text-amber-800" x-show="bill.reference" x-text="@js(__('Bill')) + ' ' + bill.reference"></p>
                        <p class="text-sm text-gray-600 mt-1" x-show="bill.customer_name || bill.customer_phone" x-text="[bill.customer_name, bill.customer_phone].filter(Boolean).join(' · ')"></p>

                        <ul class="mt-3 divide-y divide-gray-100 text-sm">
                            <template x-for="round in bill.orders" :key="round.id">
                                <li class="py-2">
                                    <div class="flex justify-between gap-2">
                                        <span class="text-gray-500" x-text="round.placed_at + ' · ' + round.reference"></span>
                                        <span class="font-semibold" x-text="formatMoney(round.total)"></span>
                                    </div>
                                    <p class="text-xs text-gray-600" x-text="round.items.join(', ')"></p>
                                </li>
                            </template>
                        </ul>
                        <p class="text-sm text-gray-500" x-show="bill.orders.length === 0">{{ __('Nothing on this bill (every round was cancelled). Settling just frees the table.') }}</p>

                        <div class="flex justify-between font-semibold border-t border-gray-100 pt-3 mt-2">
                            <span>{{ __('Total') }}</span>
                            <span x-text="formatMoney(bill.total)"></span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div class="flex gap-4 text-sm" x-show="bill.total > 0">
                                <label class="flex items-center gap-1.5"><input type="radio" x-model="settleMethod" value="cash"> {{ __('Cash') }}</label>
                                <label class="flex items-center gap-1.5"><input type="radio" x-model="settleMethod" value="momo"> {{ __('Momo') }}</label>
                            </div>
                            <input type="text" x-show="settleMethod === 'momo' && bill.total > 0" x-model="settleReference" placeholder="{{ $checksMomo ? __('Momo transaction ID') : __('Momo transaction ID (optional)') }}" class="w-full rounded-md border-gray-300 text-sm">
                            <div x-show="billError" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3" x-text="billError"></div>
                            <button type="button" @click="settleBill()" :disabled="billBusy" class="w-full px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 disabled:opacity-50"
                                x-text="bill.total > 0 ? @js(__('Paid: settle the bill')) : @js(__('Free the table'))"></button>
                            <div class="flex gap-2">
                                <button type="button" @click="printBill()" x-show="bill.total > 0" class="flex-1 px-3 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Print bill') }}</button>
                                <button type="button" @click="bill = null" class="flex-1 px-3 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Close') }}</button>
                            </div>
                            <div class="flex gap-2 items-center border-t border-gray-100 pt-3" x-show="freeTables().length > 0">
                                <select x-model="moveTo" class="flex-1 rounded-md border-gray-300 text-sm">
                                    <option value="">{{ __('Move to another table…') }}</option>
                                    <template x-for="t in freeTables()" :key="t.id">
                                        <option :value="t.id" x-text="t.name"></option>
                                    </template>
                                </select>
                                <button type="button" @click="moveBill()" :disabled="!moveTo || billBusy" class="px-3 py-2 text-sm rounded-md bg-gray-800 text-white disabled:opacity-50">{{ __('Move') }}</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        @endmodule

        @unlessrestaurant
            @include('pos.partials.retail-modals')
        @endrestaurant

        {{-- Item options modal --}}
        <div x-show="activeItem" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="activeItem = null"></div>

            <template x-if="activeItem">
                <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full p-5">
                    <h3 class="font-semibold text-gray-800 mb-1" x-text="activeItem.name"></h3>
                    <p class="text-sm text-gray-500 mb-4" x-text="formatMoney(activeItem.price)"></p>

                    <template x-for="group in activeItem.optionGroups" :key="group.id">
                        <fieldset
                            class="mb-4 rounded-md transition-colors"
                            :class="group.id in invalidGroups ? 'ring-2 ring-red-400 bg-red-50 p-2 -m-2' : ''"
                        >
                            <legend class="text-sm text-brand-yellow-dark font-semibold mb-1.5">
                                <span x-text="group.name"></span><span x-show="group.is_required" class="text-red-600"> *</span>
                            </legend>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="option in group.options" :key="option.id">
                                    <div class="flex items-center gap-1.5 text-sm">
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0">
                                            <input
                                                type="checkbox" :checked="option.id in selectedOptionQuantities"
                                                @change="toggleOption(option.id, group.id)"
                                                class="rounded border-gray-300 shrink-0"
                                            >
                                            <span class="truncate" x-text="option.name + (option.price_delta ? ' (+' + formatMoney(option.price_delta) + ')' : '')"></span>
                                        </label>
                                        {{--
                                            Only a multi-select group's option gets a quantity
                                            control — see menu/show.blade.php's equivalent for
                                            why. Fixed for the whole line, not multiplied by
                                            Qty below (MenuPricingService).
                                        --}}
                                        <div class="flex items-center gap-1 shrink-0" x-show="group.max_select > 1 && option.id in selectedOptionQuantities" x-cloak>
                                            <button type="button" @click="adjustOptionQuantity(option.id, -1)" class="w-5 h-5 flex items-center justify-center border border-gray-300 rounded text-xs">&minus;</button>
                                            <span class="w-4 text-center text-xs" x-text="selectedOptionQuantities[option.id]"></span>
                                            <button type="button" @click="adjustOptionQuantity(option.id, 1)" class="w-5 h-5 flex items-center justify-center border border-gray-300 rounded text-xs">+</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="group.id in invalidGroups" x-cloak x-text="invalidGroups[group.id]" class="text-xs text-red-600 mt-1.5"></p>
                        </fieldset>
                    </template>

                    <div x-show="error" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-md p-2 mb-4" x-text="error"></div>

                    <div class="flex items-center gap-3 mb-4">
                        <label class="text-sm font-medium text-brand-yellow-dark">{{ __('Qty') }}</label>
                        <input type="number" x-model.number="itemQuantity" min="1" max="{{ \App\Services\Cart\PosCartService::maxLineQuantity() }}" class="w-20 rounded-md border-gray-300 text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="activeItem = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm">{{ __('Cancel') }}</button>
                        <button type="button" @click="confirmAddItem()" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm hover:bg-gray-900">{{ __('Add') }}</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Confirmation --}}
        <div x-show="confirmation" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50"></div>

            <template x-if="confirmation">
                <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full p-6 text-center">
                    <h3 class="font-semibold text-lg text-gray-800 mb-1" x-text="confirmation.table ? @js(__('Round sent to the kitchen')) : @js(__('Order placed'))"></h3>
                    <p class="text-sm font-semibold text-amber-800 mb-1" x-show="confirmation.table" x-text="@js(__('Table')) + ' ' + confirmation.table + ' · ' + @js(__('added to the bill'))"></p>
                    <p class="text-sm text-gray-500 mb-1" x-text="confirmation.reference"></p>
                    <p class="text-xl font-bold text-gray-800 mb-4" x-text="formatMoney(confirmation.total)"></p>

                    <button type="button" @click="confirmation = null" class="w-full px-4 py-2 bg-gray-800 text-white rounded-md text-sm hover:bg-gray-900">{{ __('New order') }}</button>
                </div>
            </template>
        </div>
    </div>

    @include('partials.tax-script')
    @unlessrestaurant
        @include('pos.partials.retail-script')
    @endrestaurant
    <script>
        function posTerminal() {
            return {
                @unlessrestaurant ...retailTillState(), @endrestaurant
                lines: @json($cart['lines']),
                subtotal: {{ $cart['subtotal'] }},
                discount: {{ $cart['discount'] }},
                promotionBanner: @json($cart['promotionBanner']),
                foodSubtotal: {{ $cart['foodSubtotal'] }},
                whatsappReward: {{ $whatsappReward }},
                whatsapp: false,
                freebieRecipientType: 'customer',
                freebieStaffId: '',
                freebieReason: '',
                phone: phoneField(''),
                name: '',
                fulfilmentType: 'pickup',
                areaId: '',
                areaOther: '',
                landmark: '',
                ghanapostCode: '',
                paymentMethod: 'cash',
                paymentReference: '',
                deliveryFeePaidBy: '',
                instructions: '',
                error: null,
                submitting: false,
                activeItem: null,
                selectedOptionQuantities: {},
                itemQuantity: 1,
                invalidGroups: {},
                confirmation: null,
                // Tables (2026-11-20). The owner's "How the POS works": tables, quick or both (opens on Tables).
                posMode: @js($posMode),
                view: @js($posMode === 'quick' ? 'menu' : 'tables'),
                tables: [],
                table: null,
                newTableName: '',
                tableError: null,
                bill: null,
                billError: null,
                billBusy: false,
                settleMethod: 'cash',
                settleReference: '',
                moveTo: '',

                init() {
                    if (this.view === 'tables') this.loadTables();
                },

                // Tables and Quick sale never mix (owner, 2026-11-20): going to either lets go of the table.
                async showTables() {
                    if (this.table) await this.leaveTable();
                    this.view = 'tables';
                    this.loadTables();
                },

                // Quick sale: the counter's own order, never a table's.
                async quickSale() {
                    if (this.table) await this.leaveTable();
                    this.view = 'menu';
                },

                // The table's order stays on the table (its own cart); the panel goes back to Quick sale's own.
                async leaveTable() {
                    this.table = null;
                    this.phone = phoneField('');
                    this.name = '';
                    this.instructions = '';
                    this.error = null;
                    await this.loadCart();
                },

                // Each table keeps its own unsent items (server, per table) and customer (on its bill).
                async loadCart() {
                    try {
                        const url = @js(route('dashboard.pos.cart.show')) + (this.table ? '?table_id=' + this.table.id : '');
                        const payload = await this.tablesRequest(url);
                        this.lines = payload.lines;
                        this.subtotal = payload.subtotal;
                        this.foodSubtotal = payload.foodSubtotal;
                        this.discount = payload.discount;
                        this.promotionBanner = payload.promotionBanner;
                    } catch (e) {
                        this.error = window.explainError(e);
                    }
                },

                async renameTable(t) {
                    const name = prompt(@js(__('New name for this table:')), t.name);
                    if (!name || name.trim() === t.name) return;
                    try {
                        this.tables = (await this.tablesRequest(@js(url('/dashboard/tables')) + '/' + t.id, { method: 'PUT', body: JSON.stringify({ name }) })).tables;
                        if (this.table?.id === t.id) this.table.name = name.trim();
                        this.tableError = null;
                    } catch (e) {
                        this.tableError = window.explainError(e);
                    }
                },

                async deleteTable(t) {
                    if (!confirm(@js(__('Delete table')) + ' ' + t.name + '?\n\n' + @js(__('It disappears from the POS. Its past bills stay in reports and order history. This can\'t be undone here (you can add a table with the same name again).')))) return;
                    try {
                        this.tables = (await this.tablesRequest(@js(url('/dashboard/tables')) + '/' + t.id, { method: 'DELETE' })).tables;
                        if (this.table?.id === t.id) this.clearTable();
                        this.tableError = null;
                    } catch (e) {
                        this.tableError = window.explainError(e);
                    }
                },

                async tablesRequest(url, options = {}) {
                    const response = await fetch(url, { headers: this.headers(), ...options });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(window.failureMessage(response, payload));
                    return payload;
                },

                async loadTables() {
                    try {
                        this.tables = (await this.tablesRequest(@js(route('dashboard.tables.index')))).tables;
                        this.tableError = null;
                    } catch (e) {
                        this.tableError = window.explainError(e);
                    }
                },

                async addTable() {
                    if (!this.newTableName.trim()) return;
                    try {
                        this.tables = (await this.tablesRequest(@js(route('dashboard.tables.store')), { method: 'POST', body: JSON.stringify({ name: this.newTableName }) })).tables;
                        this.newTableName = '';
                        this.tableError = null;
                    } catch (e) {
                        this.tableError = window.explainError(e);
                    }
                },

                async chooseTable(t) {
                    this.table = { id: t.id, name: t.name };
                    this.paymentMethod = 'cash';
                    this.fulfilmentType = 'dine_in';
                    this.whatsapp = false;
                    // This table's own customer and unsent items, never another table's.
                    this.phone = phoneField(t.tab?.customer_phone ? '0' + t.tab.customer_phone.replace(/^\+233/, '') : '');
                    this.name = t.tab?.customer_name ?? '';
                    this.error = null;
                    await this.loadCart();
                    this.view = 'menu';
                },

                async clearTable() {
                    await this.showTables();
                },

                // In use: a bill not yet paid, or an order in progress on this till.
                inUse(t) {
                    return !!t.tab || t.unsent > 0;
                },

                freeTables() {
                    return this.tables.filter((t) => !this.inUse(t));
                },

                async openBill(t) {
                    this.billError = null;
                    this.settleMethod = 'cash';
                    this.settleReference = '';
                    this.moveTo = '';
                    try {
                        this.bill = await this.tablesRequest(@js(url('/dashboard/tables/tabs')) + '/' + t.tab.id);
                    } catch (e) {
                        this.tableError = window.explainError(e);
                    }
                },

                async settleBill() {
                    if (!this.bill || this.billBusy) return;
                    if (this.bill.total > 0 && this.settleMethod === 'cash' && !confirm(@js(__('Confirm you have received the cash for this bill?')))) return;
                    this.billBusy = true;
                    this.billError = null;
                    try {
                        const payload = await this.tablesRequest(@js(url('/dashboard/tables/tabs')) + '/' + this.bill.id + '/settle', {
                            method: 'POST',
                            body: JSON.stringify({ payment_method: this.settleMethod, payment_reference: this.settleMethod === 'momo' ? this.settleReference : null }),
                        });
                        this.tables = payload.tables;
                        if (payload.total > 0 && !payload.printed_by_agent && window.expressPrinting?.receipt) window.expressPrint(payload.print_url);
                        this.bill = null;
                    } catch (e) {
                        this.billError = window.explainError(e);
                    } finally {
                        this.billBusy = false;
                    }
                },

                async moveBill() {
                    if (!this.bill || !this.moveTo) return;
                    this.billBusy = true;
                    try {
                        this.tables = (await this.tablesRequest(@js(url('/dashboard/tables/tabs')) + '/' + this.bill.id + '/move', { method: 'POST', body: JSON.stringify({ table_id: Number(this.moveTo) }) })).tables;
                        this.bill = null;
                    } catch (e) {
                        this.billError = window.explainError(e);
                    } finally {
                        this.billBusy = false;
                    }
                },

                async printBill() {
                    if (!this.bill) return;
                    try {
                        const payload = await this.tablesRequest(@js(url('/dashboard/tables/tabs')) + '/' + this.bill.id + '/print', { method: 'POST', body: '{}' });
                        if (!payload.queued) window.expressPrint(payload.print_url);
                    } catch (e) {
                        this.billError = window.explainError(e);
                    }
                },

                // The server's own discount assumes a non-Bolt-Food order
                // (it's priced before payment method is necessarily final)
                // — zeroed here the moment Bolt Food is selected, matching
                // OrderCreationService/PromotionService's own exclusion so
                // the displayed total never promises a discount that
                // won't actually be charged.
                displayedDiscount() {
                    if (this.paymentMethod === 'complimentary') return this.subtotal;
                    if (this.paymentMethod === 'bolt_food') return 0;

                    return this.discount + this.whatsappDiscount();
                },

                // Mirrors OrderCreationService: GH₵10 off the food (never
                // drinks, never more than the food), only when the order
                // isn't already earning the day's promotion.
                whatsappDiscount() {
                    if (!this.whatsapp || !['cash', 'momo'].includes(this.paymentMethod)) return 0;
                    if (this.discount > 0 || this.lines.some((line) => line.is_free)) return 0;

                    return Math.min(this.whatsappReward, this.foodSubtotal);
                },

                // Tax on the food (step 9); the delivery fee is never taxed.
                tax() {
                    return window.expressTax(this.subtotal - this.displayedDiscount(), this.paymentMethod);
                },

                // Only Bolt Food stays pickup: freebies and WhatsApp orders can be delivered (owner, 2026-11-20).
                pickupOnly() {
                    return this.paymentMethod === 'bolt_food';
                },

                // Who pays the delivery fee is asked for MoMo orders and freebies.
                asksFeePayer() {
                    return ['momo', 'complimentary'].includes(this.paymentMethod) && this.fulfilmentType === 'delivery';
                },

                // Same reasoning as displayedDiscount() above, applied to
                // a buy_x_get_y_free promotion's free line instead of a
                // discount number — the server includes it in `lines`
                // assuming a non-Bolt-Food order, since payment_method
                // isn't necessarily final while the cart's still being
                // built.
                visibleLines() {
                    return ['bolt_food', 'complimentary'].includes(this.paymentMethod)
                        ? this.lines.filter((line) => !line.is_free)
                        : this.lines;
                },

                openItem(item) {
                    this.activeItem = item;
                    this.selectedOptionQuantities = {};
                    this.itemQuantity = 1;
                    this.invalidGroups = {};
                    this.error = null;
                },

                toggleOption(id, groupId) {
                    const alreadySelected = id in this.selectedOptionQuantities;
                    const group = this.activeItem.optionGroups.find((g) => g.id === groupId);

                    if (group && group.max_select === 1 && !alreadySelected) {
                        group.options.forEach((o) => delete this.selectedOptionQuantities[o.id]);
                        this.selectedOptionQuantities[id] = 1;
                    } else if (alreadySelected) {
                        delete this.selectedOptionQuantities[id];
                    } else {
                        this.selectedOptionQuantities[id] = 1;
                    }

                    this.refreshInvalidGroups();
                },

                adjustOptionQuantity(id, delta) {
                    const max = {{ \App\Services\Menu\MenuPricingService::MAX_OPTION_QUANTITY }};
                    const current = this.selectedOptionQuantities[id] ?? 1;
                    this.selectedOptionQuantities[id] = Math.min(max, Math.max(1, current + delta));
                },

                // Mirrors MenuPricingService::assertGroupCountsSatisfied()'s
                // two checks exactly (see menu-item-form-script.blade.php's
                // equivalent for the customer-facing menu) — a fast front
                // door so staff see which group is missing instantly instead
                // of the "Add" button silently doing nothing while the real
                // 422 rejection sits unnoticed behind the still-open modal.
                validateOptionGroups() {
                    const invalidGroups = {};

                    for (const group of this.activeItem.optionGroups) {
                        const count = group.options.filter((o) => o.id in this.selectedOptionQuantities).length;

                        if (count < group.min_select || count > group.max_select) {
                            invalidGroups[group.id] = `Requires between ${group.min_select} and ${group.max_select} selection(s).`;
                        } else if (group.is_required && count < 1) {
                            invalidGroups[group.id] = 'This selection is required.';
                        }
                    }

                    return invalidGroups;
                },

                // Live-clears a group's highlight as soon as it's fixed,
                // without nagging the user with validation before they've
                // even tried to submit once.
                refreshInvalidGroups() {
                    if (Object.keys(this.invalidGroups).length === 0) return;
                    this.invalidGroups = this.validateOptionGroups();
                },

                async confirmAddItem() {
                    const invalidGroups = this.validateOptionGroups();

                    if (Object.keys(invalidGroups).length > 0) {
                        this.invalidGroups = invalidGroups;
                        return;
                    }

                    const added = await this.mutateCart('{{ route('dashboard.pos.cart.add') }}', 'POST', {
                        menu_item_id: this.activeItem.id,
                        quantity: this.itemQuantity,
                        option_ids: Object.keys(this.selectedOptionQuantities).map(Number),
                        option_qty: this.selectedOptionQuantities,
                    });

                    if (added) {
                        this.activeItem = null;
                        this.invalidGroups = {};
                    }
                },

                async changeQuantity(lineId, quantity) {
                    if (quantity < 1) {
                        return this.removeLine(lineId);
                    }
                    await this.mutateCart(`/dashboard/pos/cart/${lineId}`, 'POST', { quantity });
                },

                async removeLine(lineId) {
                    await this.mutateCart(`/dashboard/pos/cart/${lineId}`, 'DELETE');
                },

                async mutateCart(url, method, body = null) {
                    // A table's items go to that table's own cart.
                    if (this.table) url += (url.includes('?') ? '&' : '?') + 'table_id=' + this.table.id;
                    try {
                        const response = await fetch(url, {
                            method,
                            headers: this.headers(),
                            body: body ? JSON.stringify(body) : undefined,
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            this.error = window.failureMessage(response, payload);
                            return false;
                        }

                        this.lines = payload.lines;
                        this.subtotal = payload.subtotal;
                        this.foodSubtotal = payload.foodSubtotal;
                        this.discount = payload.discount;
                        this.promotionBanner = payload.promotionBanner;
                        this.error = null;
                        return true;
                    } catch (e) {
                        this.error = window.explainError(e);
                        return false;
                    }
                },

                async placeOrder() {
                    if (this.lines.length === 0 || this.submitting) return;

                    this.error = this.validationError();
                    if (this.error) return;

                    if (!this.table && this.paymentMethod === 'cash' && !confirm(@js(__('Confirm you have received cash payment for this order?')))) {
                        return;
                    }

                    this.submitting = true;
                    this.error = null;

                    try {
                        const response = await fetch('{{ route('dashboard.pos.orders.store') }}', {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({
                                phone: this.phone.raw,
                                name: this.name,
                                fulfilment_type: this.fulfilmentType,
                                area_id: this.areaId,
                                area_other: this.areaOther,
                                landmark: this.landmark,
                                ghanapost_code: this.ghanapostCode,
                                payment_method: this.paymentMethod,
                                payment_reference: this.paymentMethod === 'momo' ? this.paymentReference : null,
                                delivery_fee_paid_by: this.asksFeePayer() ? this.deliveryFeePaidBy : null,
                                instructions: this.instructions,
                                source: this.whatsapp && ['cash', 'momo'].includes(this.paymentMethod) ? 'whatsapp' : null,
                                freebie_recipient_type: this.paymentMethod === 'complimentary' ? this.freebieRecipientType : null,
                                freebie_recipient_user_id: this.paymentMethod === 'complimentary' && this.freebieRecipientType === 'staff' ? this.freebieStaffId : null,
                                freebie_reason: this.paymentMethod === 'complimentary' ? this.freebieReason : null,
                                table_id: this.table ? this.table.id : null,
                            }),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            this.error = window.failureMessage(response, payload, @js(__('Could not place the order.')));
                            return;
                        }

                        this.confirmation = {
                            reference: payload.order.reference,
                            total: payload.order.total,
                        };

                        // Step 8a: the receipt and the kitchen ticket, when the branch has those printers.
                        // The print agent has them when it's on (step 8b).
                        // A table round prints only its kitchen ticket; the bill comes when it's settled.
                        if (! payload.printed_by_agent) {
                            if (window.expressPrinting?.receipt && !payload.order.table) window.expressPrint(window.expressPrintUrl(payload.order.id, 'receipt'));
                            if (window.expressPrinting?.kitchen) window.expressPrint(window.expressPrintUrl(payload.order.id, 'kitchen'));
                        }
                        if (payload.order.table) {
                            this.confirmation.table = payload.order.table;
                            this.table = null;
                            this.resetForm();
                            await this.loadCart();
                            this.showTables();
                        } else {
                            this.resetForm();
                        }
                    } catch (e) {
                        // The order may still have gone through if the connection dropped on the way back.
                        this.error = window.explainError(e) + ' ' + @js(__('Check Order history before placing it again.'));
                    } finally {
                        this.submitting = false;
                    }
                },

                // Mirrors the server-side validation rules — a faster
                // front door only, the server-side check stays the actual
                // authority.
                validationError() {
                    // Optional at the POS (unlike every other use of
                    // phoneField() — checkout, registration, etc.) — empty
                    // is fine for a walk-in customer who doesn't want to
                    // give it, but a half-typed number still isn't.
                    if (this.phone.raw.length > 0 && !this.phone.valid) return '{{ __('Enter a valid 10-digit customer phone number, or leave it blank.') }}';

                    if (this.paymentMethod === 'complimentary') {
                        if (this.freebieRecipientType === 'staff' && !this.freebieStaffId) return @js(__('Choose which staff member the freebie is for.'));
                        if (this.freebieRecipientType === 'customer' && !this.name.trim() && !this.phone.raw) return @js(__('Enter the customer\'s name or phone for the freebie.'));
                        if (!this.freebieReason.trim()) return @js(__('Say why the freebie is being given.'));
                    }

                    if (this.fulfilmentType === 'delivery') {
                        if (!this.areaId) return '{{ __('Delivery area is required.') }}';
                        if (this.areaId === 'other' && !this.areaOther.trim()) return '{{ __('Area name is required.') }}';
                        if (!this.landmark.trim()) return '{{ __('Landmark is required.') }}';
                        if (this.asksFeePayer() && !this.deliveryFeePaidBy) return '{{ __('Choose how the customer pays the delivery fee.') }}';
                    }

                    return null;
                },

                resetForm() {
                    this.lines = [];
                    this.subtotal = 0;
                    this.phone = phoneField('');
                    this.name = '';
                    this.fulfilmentType = 'pickup';
                    this.areaId = '';
                    this.areaOther = '';
                    this.landmark = '';
                    this.ghanapostCode = '';
                    this.paymentMethod = 'cash';
                    this.whatsapp = false;
                    this.freebieRecipientType = 'customer';
                    this.freebieStaffId = '';
                    this.freebieReason = '';
                    this.paymentReference = '';
                    this.deliveryFeePaidBy = '';
                    this.instructions = '';
                },

                headers() {
                    return {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    };
                },

                formatMoney(pesewas) {
                    return 'GH₵' + ((pesewas ?? 0) / 100).toFixed(2);
                },
            };
        }
    </script>

    @include('partials.phone-input-script')
    @include('partials.print-script')
</x-app-layout>

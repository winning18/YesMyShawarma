<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Market list') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $errors->first() }}</div>
            @endif

            @can('create', \App\Models\MarketList::class)
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800">{{ __('Record purchases and expenses') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('Everything bought or paid for: market items, transport, gas… The kind is suggested as you type; change it if it\'s wrong. It counts in reports once approved.') }}</p>
                    @include('dashboard.market-lists._form', [
                        'action' => route('dashboard.market-lists.store'),
                        'rows' => collect(old('items', []))->map(fn ($row) => [
                            'description' => $row['description'] ?? '',
                            'kind' => isset($row['expense_category_id']) ? (int) $row['expense_category_id'] : null,
                            'quantity' => $row['quantity'] ?? '',
                            'unit' => $row['unit'] ?? '',
                            'amount' => $row['amount'] ?? '',
                        ])->values()->all(),
                        'submitLabel' => __('Submit market list'),
                    ])
                </div>
            @endcan

            <div class="bg-white shadow rounded-lg p-4">
                <form method="GET" action="{{ route('dashboard.market-lists.index') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                        <select id="status" name="status" class="rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('Any status') }}</option>
                            @foreach ($statuses as $value)
                                <option value="{{ $value }}" @selected($status === $value)>{{ __(\Illuminate\Support\Str::headline($value)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Apply filters') }}</button>
                </form>
            </div>

            @forelse ($lists as $list)
                @php
                    $badgeClass = match ($list->status) {
                        'approved' => 'bg-green-50 text-green-700',
                        'needs_changes' => 'bg-amber-50 text-amber-700',
                        default => 'bg-gray-100 text-gray-600',
                    };
                    $ownerOnly = in_array($list->recorder_role, \App\Services\MarketLists\MarketListService::OWNER_APPROVES_ROLES, true);
                    $canApprove = $list->status === 'pending' && auth()->user()->can('approve', $list);
                @endphp
                <div class="bg-white shadow rounded-lg p-4 space-y-3" x-data="{ sendingBack: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-800">
                                {{ $list->purchased_on->format('D d M Y') }} · {{ $list->branch->name }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ __('Recorded by') }} {{ $list->recordedBy?->name ?? __('Deleted account') }}
                                <span class="text-gray-400">({{ \Illuminate\Support\Str::headline($list->recorder_role) }})</span>
                                @if ($list->shift_id) · {{ __('from shift close') }} @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-medium px-2 py-1 rounded-md {{ $badgeClass }}">{{ __(\Illuminate\Support\Str::headline($list->status)) }}</span>
                            @if ($list->status === 'pending' && $ownerOnly)
                                <span class="block text-xs text-gray-400 mt-1">{{ __('Owner approves') }}</span>
                            @endif
                            <p class="font-semibold text-gray-800 mt-1">GH₵{{ number_format($list->total / 100, 2) }}</p>
                        </div>
                    </div>

                    @if ($canApprove)
                        <form id="approve-{{ $list->id }}" method="POST" action="{{ route('dashboard.market-lists.approve', $list) }}">@csrf</form>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs text-gray-500 border-b border-gray-100">
                                <tr>
                                    <th class="py-1 pr-2 font-medium">{{ __('Item') }}</th>
                                    <th class="py-1 pr-2 font-medium">{{ __('Kind') }}</th>
                                    <th class="py-1 pr-2 font-medium">{{ __('Quantity') }}</th>
                                    <th class="py-1 font-medium text-right">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($list->items as $item)
                                    <tr>
                                        <td class="py-1 pr-2">{{ $item->description }}</td>
                                        <td class="py-1 pr-2 text-gray-500">
                                            @if ($canApprove)
                                                <select name="kinds[{{ $item->id }}]" form="approve-{{ $list->id }}" class="rounded-md border-gray-300 text-xs py-1">
                                                    @foreach ($kinds as $kind)
                                                        <option value="{{ $kind->id }}" @selected($kind->id === $item->expense_category_id)>{{ $kind->name }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                {{ $item->category->name }}
                                            @endif
                                        </td>
                                        <td class="py-1 pr-2 text-gray-500">
                                            @if ($item->quantity !== null)
                                                {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="py-1 text-right">GH₵{{ number_format($item->amount / 100, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($list->note || $list->receipt_path)
                        <p class="text-xs text-gray-500">
                            @if ($list->note) {{ $list->note }} @endif
                            @can('viewReceipt', $list)
                                <a href="{{ route('dashboard.market-lists.receipt', $list) }}" target="_blank" class="text-indigo-600 hover:underline ms-1">{{ __('View receipt photo') }}</a>
                            @endcan
                        </p>
                    @endif

                    @if ($list->review_note && $list->status !== 'pending')
                        <p class="text-xs {{ $list->status === 'needs_changes' ? 'text-amber-700' : 'text-gray-500' }}">
                            {{ $list->status === 'needs_changes' ? __('Sent back by :name:', ['name' => $list->reviewedBy?->name]) : __('Approval note:') }}
                            {{ $list->review_note }}
                        </p>
                    @elseif ($list->status === 'approved' && $list->reviewedBy)
                        <p class="text-xs text-gray-400">{{ __('Approved by :name, :when', ['name' => $list->reviewedBy->name, 'when' => $list->reviewed_at->timezone('Africa/Accra')->format('d M, H:i')]) }}</p>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3 text-sm">
                        @if ($list->isEditable())
                            @can('update', $list)
                                <a href="{{ route('dashboard.market-lists.edit', $list) }}" class="text-indigo-600 hover:underline">
                                    {{ $list->status === 'needs_changes' && $list->recorded_by === auth()->id() ? __('Edit and resubmit') : __('Edit') }}
                                </a>
                            @endcan
                        @endif
                        @if ($canApprove)
                            <button type="button" @click="sendingBack = ! sendingBack" class="text-amber-700 hover:underline">{{ __('Send back') }}</button>
                            <input type="text" name="note" form="approve-{{ $list->id }}" maxlength="1000" placeholder="{{ __('Note (optional)') }}" class="rounded-md border-gray-300 text-sm">
                            <button type="submit" form="approve-{{ $list->id }}" class="px-3 py-1.5 bg-green-700 text-white font-semibold rounded-md hover:bg-green-800">{{ __('Approve') }}</button>
                        @endif
                    </div>

                    @if ($canApprove)
                        <form x-show="sendingBack" x-cloak method="POST" action="{{ route('dashboard.market-lists.send-back', $list) }}" class="flex flex-wrap items-end gap-2 border-t border-gray-100 pt-3">
                            @csrf
                            <div class="flex-1 min-w-[16rem]">
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('What needs changing?') }} <span class="text-red-600">*</span></label>
                                <input type="text" name="reason" required maxlength="1000" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <button type="submit" class="px-3 py-2 bg-amber-600 text-white text-sm font-semibold rounded-md hover:bg-amber-700">{{ __('Send back') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="bg-white shadow rounded-lg p-6 text-center text-gray-500 text-sm">{{ __('No market lists match these filters.') }}</div>
            @endforelse

            {{ $lists->links() }}
        </div>
    </div>
</x-app-layout>

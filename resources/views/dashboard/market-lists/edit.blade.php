<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit market list') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('dashboard.market-lists.index') }}" class="text-sm text-gray-500 hover:underline">&larr; {{ __('Market list') }}</a>

            @if ($errors->any())
                <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $errors->first() }}</div>
            @endif

            @if ($list->status === 'needs_changes' && $list->review_note)
                <div class="rounded-md bg-amber-50 text-amber-800 text-sm px-4 py-2">{{ __('Sent back:') }} {{ $list->review_note }}</div>
            @endif

            <div class="bg-white shadow rounded-lg p-4">
                @include('dashboard.market-lists._form', [
                    'action' => route('dashboard.market-lists.update', $list),
                    'rows' => old('items')
                        ? collect(old('items'))->map(fn ($row) => [
                            'description' => $row['description'] ?? '',
                            'kind' => isset($row['expense_category_id']) ? (int) $row['expense_category_id'] : null,
                            'quantity' => $row['quantity'] ?? '',
                            'unit' => $row['unit'] ?? '',
                            'amount' => $row['amount'] ?? '',
                        ])->values()->all()
                        : $list->items->map(fn ($item) => [
                            'description' => $item->description,
                            'kind' => $item->expense_category_id,
                            'quantity' => $item->quantity !== null ? (float) $item->quantity : '',
                            'unit' => $item->unit ?? '',
                            'amount' => number_format($item->amount / 100, 2, '.', ''),
                        ])->values()->all(),
                    'submitLabel' => $list->status === 'needs_changes' && $list->recorded_by === auth()->id() ? __('Save and resubmit') : __('Save changes'),
                ])
            </div>

            <div class="bg-white shadow rounded-lg p-4">
                <h3 class="font-semibold text-gray-800 mb-2 text-sm">{{ __('History') }}</h3>
                <ul class="text-xs text-gray-500 space-y-1">
                    @foreach ($list->events->sortBy('id') as $event)
                        <li>
                            {{ $event->created_at->timezone('Africa/Accra')->format('d M Y, H:i') }} —
                            {{ __(\Illuminate\Support\Str::headline($event->action)) }}
                            @if ($event->actor) {{ __('by :name', ['name' => $event->actor->name]) }} @endif
                            @if ($event->note) · {{ $event->note }} @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __(':name history', ['name' => $item->name]) }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('dashboard.stock.index') }}" class="text-sm text-gray-600 hover:underline">
                &larr; {{ __('Back to stock') }}
            </a>

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Type') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Quantity') }}</th>
                            <th class="px-4 py-2">{{ __('By') }}</th>
                            <th class="px-4 py-2">{{ __('Note') }}</th>
                            <th class="px-4 py-2">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="px-4 py-2">
                                    <span class="text-xs font-medium px-2 py-1 rounded-md capitalize {{ $movement->type === 'restock' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $movement->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-gray-800">
                                    {{ $movement->type === 'restock' ? '+' : '-' }}{{ $movement->quantity }} {{ $item->unit }}
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->actor->name }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->note }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->created_at->timezone('Africa/Accra')->format('d M Y, H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No stock movements yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $movements->links() }}
        </div>
    </div>
</x-app-layout>

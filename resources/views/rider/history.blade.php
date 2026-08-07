<x-rider-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Past deliveries') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @forelse ($orders as $order)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $order->reference }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $order->delivery_address_snapshot['area_name'] ?? __('No area on file') }}
                            </p>
                        </div>
                        <span
                            class="text-sm font-medium px-2 py-1 rounded-md {{ $order->status === 'delivered' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}"
                        >{{ $order->status === 'delivered' ? __('Delivered') : __('Failed') }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">{{ $order->updated_at->timezone('Africa/Accra')->format('d M Y, H:i') }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __("You haven't completed any deliveries yet.") }}</p>
            @endforelse

            {{ $orders->links() }}
        </div>
    </div>
</x-rider-layout>

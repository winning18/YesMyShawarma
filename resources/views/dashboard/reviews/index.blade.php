<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Reviews') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif
            @error('review')
                <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>
            @enderror

            <div class="bg-white shadow rounded-lg p-4">
                <form method="GET" action="{{ route('dashboard.reviews.index') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                        <select id="status" name="status" class="rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                            <option value="pending" @selected($status === 'pending')>{{ __('Pending') }}</option>
                            <option value="approved" @selected($status === 'approved')>{{ __('Approved') }}</option>
                            <option value="rejected" @selected($status === 'rejected')>{{ __('Rejected') }}</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Order') }}</th>
                            <th class="px-4 py-2">{{ __('Branch') }}</th>
                            <th class="px-4 py-2">{{ __('Rating') }}</th>
                            <th class="px-4 py-2">{{ __('Comment') }}</th>
                            <th class="px-4 py-2">{{ __('Submitted') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($reviews as $review)
                            <tr>
                                <td class="px-4 py-2 font-medium">
                                    <a href="{{ route('dashboard.orders.show', $review->order) }}" class="text-indigo-600 hover:underline">
                                        {{ $review->order->reference }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $review->branch->name }}</td>
                                <td class="px-4 py-2 text-amber-500">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</td>
                                <td class="px-4 py-2 text-gray-500 max-w-xs truncate" title="{{ $review->comment }}">{{ $review->comment ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $review->created_at->timezone('Africa/Accra')->format('d M Y, H:i') }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if ($review->status === 'pending')
                                        <div class="flex items-center justify-end gap-2">
                                            @can('approve', $review)
                                                <form method="POST" action="{{ route('dashboard.reviews.approve', $review) }}">
                                                    @csrf
                                                    <button type="submit" class="text-green-700 hover:underline">{{ __('Approve') }}</button>
                                                </form>
                                            @endcan
                                            @can('reject', $review)
                                                <form method="POST" action="{{ route('dashboard.reviews.reject', $review) }}"
                                                    onsubmit="return confirm({{ Js::from(__('Reject this review?')) }})">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Reject') }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No reviews match this filter.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $reviews->links() }}
        </div>
    </div>
</x-app-layout>

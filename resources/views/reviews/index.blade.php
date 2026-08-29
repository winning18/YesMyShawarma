<x-customer-layout title="Reviews · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Reviews') }}</x-slot>

    @if ($reviews->isEmpty())
        <p class="text-center text-brand-gray-500 max-w-md mx-auto">
            {{ __('No reviews yet — be the first to leave one after your order.') }}
        </p>
    @else
        <div class="max-w-2xl mx-auto space-y-6 mb-8">
            @foreach ($reviews as $review)
                <div class="border border-brand-gray-100 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1 text-brand-yellow-dark" aria-label="{{ __(':rating out of 5 stars', ['rating' => $review->rating]) }}">
                            @for ($star = 1; $star <= 5; $star++)
                                <span>{{ $star <= $review->rating ? '★' : '☆' }}</span>
                            @endfor
                        </div>
                        <p class="text-xs text-brand-gray-500">{{ $review->moderated_at?->format('j M Y') }}</p>
                    </div>

                    @if ($review->comment)
                        <p class="text-brand-black mb-2">{{ $review->comment }}</p>
                    @endif

                    <p class="text-xs text-brand-gray-500">{{ $review->branch->name }}</p>
                </div>
            @endforeach
        </div>

        <div class="max-w-2xl mx-auto">
            {{ $reviews->links() }}
        </div>
    @endif
</x-customer-layout>

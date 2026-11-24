{{-- The latest approved reviews (Reviews module), for a theme's home page. --}}
@if ($reviews->isNotEmpty())
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        @foreach ($reviews as $review)
            <figure class="rounded-2xl bg-brand-white border border-brand-gray-100 p-6 flex flex-col">
                <div class="text-brand-accent-text mb-3" aria-label="{{ __(':rating out of 5 stars', ['rating' => $review->rating]) }}">
                    @for ($star = 1; $star <= 5; $star++)<span>{{ $star <= $review->rating ? '★' : '☆' }}</span>@endfor
                </div>
                <blockquote class="text-brand-black leading-relaxed flex-1">“{{ $review->comment }}”</blockquote>
                <figcaption class="mt-4 text-xs text-brand-gray-500">{{ $review->branch?->name }} · {{ $review->moderated_at?->format('j M Y') }}</figcaption>
            </figure>
        @endforeach
    </div>
    <p class="mt-6 text-center">
        <a href="{{ route('reviews.index') }}" class="text-sm font-semibold underline underline-offset-4">{{ __('Read all reviews') }}</a>
    </p>
@endif

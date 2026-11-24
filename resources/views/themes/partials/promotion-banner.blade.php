{{-- A live automatic promotion's banner (PromotionService::findActiveAutomatic), ahead of a theme's hero. --}}
@if ($promotionBanner)
    <section class="bg-brand-red text-brand-on-accent text-center px-4 py-5">
        @if ($promotionBanner['imageUrl'])
            <img src="{{ $promotionBanner['imageUrl'] }}" alt="" class="mx-auto mb-3 max-h-40 rounded-lg" loading="eager">
        @endif
        <p class="font-heading text-lg">{{ $promotionBanner['headline'] ?: __('A promotion is live today.') }}</p>
    </section>
@endif

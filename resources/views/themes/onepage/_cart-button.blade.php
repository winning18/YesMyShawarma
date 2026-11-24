{{-- One-page's floating "View cart", once something is in it. --}}
@if ($cartItemCount > 0)
    <a href="{{ route('cart.show') }}" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 inline-flex items-center gap-3 px-6 py-3 rounded-full bg-brand-black text-brand-white font-semibold shadow-lg hover:bg-brand-gray-700 transition">
        {{ __('View cart') }}
        <span class="bg-brand-yellow text-brand-on-primary text-xs font-bold rounded-full min-w-6 h-6 px-1.5 flex items-center justify-center">{{ $cartItemCount }}</span>
    </a>
@endif

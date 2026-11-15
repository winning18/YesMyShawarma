{{-- My account section tabs. $active: details | orders | addresses --}}
<div class="flex gap-6 border-b border-brand-gray-100 mb-6 overflow-x-auto whitespace-nowrap">
    {{-- Saved addresses are for delivery, so they go when Delivery and riders is switched off. --}}
    @foreach (array_filter(['details' => [__('Details'), 'customer.account.show'], 'orders' => [__('Orders'), 'customer.account.orders'], 'addresses' => app(\App\Services\Modules\ModuleService::class)->enabled('delivery') ? [__('Addresses'), 'customer.account.addresses'] : null]) as $key => [$label, $route])
        <a href="{{ route($route) }}"
            class="pb-3 -mb-px border-b-2 text-sm font-semibold {{ $active === $key ? 'border-brand-yellow text-brand-black' : 'border-transparent text-brand-gray-500 hover:text-brand-black' }}">{{ $label }}</a>
    @endforeach
</div>

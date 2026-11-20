@php $money = fn (int $pesewas) => number_format($pesewas / 100, 2); @endphp
<x-print-page :title="$data['reference']" :width="$width">
    <div class="center">
        @if ($data['logo'])
            <img class="logo" src="{{ $data['logo'] }}" alt="">
        @endif
        <p class="big">{{ $data['business'] }}</p>
        <p>{{ $data['branch'] }}</p>
        @if ($data['address'])<p class="muted">{{ $data['address'] }}</p>@endif
        @if ($data['phone'])<p class="muted">{{ __('Tel') }} {{ $data['phone'] }}</p>@endif
        @if ($data['tin'])<p class="muted">{{ __('TIN') }} {{ $data['tin'] }}</p>@endif
    </div>
    <div class="rule"></div>
    <div class="row"><span class="bold">{{ $data['reference'] }}</span><span>{{ $data['placed_at']?->format('d/m/Y H:i') }}</span></div>
    @if ($data['served_by'] || $data['counter'])
        <p class="muted">{{ collect([$data['served_by'] ? __('Served by :name', ['name' => $data['served_by']]) : null, $data['counter']])->filter()->join(' · ') }}</p>
    @endif
    <p class="muted">{{ $data['fulfilment'] === 'delivery' ? __('Delivery') : __('Pickup') }}@if ($data['customer']) · {{ $data['customer'] }}@endif</p>
    <div class="rule"></div>
    @foreach ($data['lines'] as $line)
        <div class="row"><span>{{ $line['quantity'] }}× {{ $line['name'] }}@if ($line['free']) ({{ __('free') }})@endif</span><span>{{ $money($line['total']) }}</span></div>
        @foreach ($line['options'] as $option)
            <p class="opt muted">+ {{ $option }}</p>
        @endforeach
    @endforeach
    <div class="rule"></div>
    @php $taxesAdded = $data['taxes'] && ! $data['tax_inclusive']; @endphp
    @if ($data['discount'] || $data['delivery_fee'] || $taxesAdded)
        <div class="row"><span>{{ __('Subtotal') }}</span><span>{{ $money($data['subtotal']) }}</span></div>
        @if ($data['discount'])<div class="row"><span>{{ __('Discount') }}</span><span>−{{ $money($data['discount']) }}</span></div>@endif
        @if ($data['delivery_fee'])<div class="row"><span>{{ __('Delivery fee') }}</span><span>{{ $money($data['delivery_fee']) }}</span></div>@endif
        @if ($taxesAdded)
            @foreach ($data['taxes'] as $tax)
                <div class="row"><span>{{ $tax['label'] }}</span><span>{{ $money($tax['amount']) }}</span></div>
            @endforeach
        @endif
    @endif
    <div class="row big"><span>{{ __('Total') }}</span><span>GH₵{{ $money($data['total']) }}</span></div>
    @if ($data['taxes'] && $data['tax_inclusive'])
        @foreach ($data['taxes'] as $tax)
            <div class="row muted"><span>{{ __('Includes :tax', ['tax' => $tax['label']]) }}</span><span>{{ $money($tax['amount']) }}</span></div>
        @endforeach
    @endif
    <p>{{ __('Paid by :method', ['method' => $data['payment']]) }}</p>
    @if ($data['message'])
        <div class="rule"></div>
        <p class="center">{{ $data['message'] }}</p>
    @endif
</x-print-page>

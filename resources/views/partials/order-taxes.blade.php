{{--
    An order's tax lines as they were at the sale (Express POS step 9).
    Include twice around the Total row: $position 'before' lists tax
    added on top (part of the total), 'after' lists tax included in the
    prices ("Includes VAT 15%"). Nothing for an order without tax.
--}}
@if ($order->tax_total > 0)
    @foreach ($order->taxes->sortBy('id') as $tax)
        @if (($position === 'before') === ! $tax->inclusive)
            <div class="flex justify-between {{ $rowClass ?? '' }} {{ $tax->inclusive ? 'text-xs text-gray-500' : '' }}">
                <span class="{{ $tax->inclusive ? '' : ($labelClass ?? '') }}">{{ $tax->inclusive ? __('Includes :tax', ['tax' => \App\Services\Tax\TaxService::label($tax->name, $tax->rate_basis_points)]) : \App\Services\Tax\TaxService::label($tax->name, $tax->rate_basis_points) }}</span>
                <span>GH₵{{ number_format(($tax->food_amount + $tax->delivery_amount) / 100, 2) }}</span>
            </div>
        @endif
    @endforeach
@endif

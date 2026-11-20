<x-print-page :title="__('Test page')" :width="$width">
    <p class="center big">Express POS</p>
    <p class="center bold">{{ __('Test page') }}</p>
    <div class="rule"></div>
    <p class="center">{{ $business }} · {{ $printer->branch?->name }}</p>
    <p class="center">{{ $printer->name }} ({{ $printer->paper_width }} mm)</p>
    <p class="center">{{ now()->timezone('Africa/Accra')->format('d/m/Y H:i') }}</p>
    <div class="rule"></div>
    <p class="center">{{ __('If you can read this, this device can print to the printer. For printing with no dialog and the cash drawer, set up the Express POS Print Agent.') }}</p>
</x-print-page>

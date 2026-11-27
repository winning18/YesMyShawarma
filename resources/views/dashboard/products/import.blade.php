{{-- Products → Import (R2, 2026-11-27; ProductImportController). --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Import products') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-3 text-sm text-gray-700">
            <p>{{ __('Add or update many products at once from a spreadsheet: CSV or Excel (.xlsx), one product per row, up to :max rows.', ['max' => number_format($maxRows)]) }}</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>{{ __('Needed for a new product: Name, Category and Price (in cedis). Optional: SKU, Barcode, Unit, Cost price, Description.') }}</li>
                <li>{{ __('A row with a product code (SKU) you already have updates that product, so you can import the same file again after changes. A blank SKU is made for you.') }}</li>
                <li>{{ __('Pack sizes: fill in Pack name, Pack units, Pack price (and Pack cost price, Pack barcode). For more packs of the same product, add rows with the same SKU and only the pack columns.') }}</li>
                <li>{{ __('Every row is checked first. If any row has a mistake, nothing is imported and each mistake is listed below.') }}</li>
            </ul>
            <p><a href="{{ route('dashboard.products.import.template') }}" class="font-semibold text-indigo-600 hover:underline">{{ __('Download the template (CSV)') }}</a></p>
        </div>

        <form method="POST" action="{{ route('dashboard.products.import.store') }}" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 space-y-4">
            @csrf
            <div>
                <x-input-label for="file" :value="__('Product list')" required />
                <input id="file" name="file" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                    class="mt-1 block w-full text-sm {{ $errors->has('file') ? 'rounded-md ring-2 ring-red-600' : '' }}">
                <p class="text-xs text-gray-500 mt-1">{{ __('CSV or Excel (.xlsx), up to 10 MB.') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('file')" />
                @if (session('import_errors'))
                    <ul class="mt-2 space-y-1 text-sm text-red-600 max-h-96 overflow-y-auto">
                        @foreach (session('import_errors') as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Import') }}</x-primary-button>
                <a href="{{ route('dashboard.menu-items.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Back to menu') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>

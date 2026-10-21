<x-app-layout>
    <x-slot name="header">
        @include('dashboard.customers._tabs', ['active' => 'import'])
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
        <a href="{{ route('dashboard.customers.index') }}" class="text-sm underline text-gray-500">&larr; {{ __('Customer list') }}</a>

        @if ($result = session('importResult'))
            <div class="bg-white shadow rounded-lg p-6 space-y-3">
                <h3 class="font-semibold text-gray-800">{{ __('Import finished') }}</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li><span class="font-semibold text-green-700">{{ $result['added'] }}</span> {{ __('new contacts added') }}</li>
                    <li><span class="font-semibold">{{ $result['existing'] }}</span> {{ __('already in the list, left as they were') }}@if ($result['names_filled']) {{ __('(:count got a name they were missing)', ['count' => $result['names_filled']]) }}@endif</li>
                    <li><span class="font-semibold">{{ $result['duplicates'] }}</span> {{ __('duplicates in the file, skipped') }}</li>
                    <li><span class="font-semibold {{ $result['invalid'] ? 'text-red-600' : '' }}">{{ $result['invalid'] }}</span> {{ __('invalid numbers, skipped') }}</li>
                </ul>
                @if ($result['invalid_rows'])
                    <div class="text-xs text-gray-500">
                        <p class="mb-1">{{ __('Invalid numbers (line in file: value):') }}</p>
                        <ul class="font-mono space-y-0.5">
                            @foreach ($result['invalid_rows'] as $row)
                                <li>{{ $row['line'] }}: {{ $row['value'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($result['truncated'])
                    <p class="text-sm text-amber-700">{{ __('Only the first :max rows were read. Split the file and upload the rest.', ['max' => number_format($maxRows)]) }}</p>
                @endif
                @can('customers.broadcast')
                    <a href="{{ route('dashboard.customers.broadcasts.index') }}" class="inline-block text-sm font-semibold underline text-gray-800">{{ __('Send an SMS broadcast') }} &rarr;</a>
                @endcan
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.customers.import.store') }}" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 space-y-4">
            @csrf
            <div>
                <h3 class="font-semibold text-gray-800">{{ __('Import contacts') }}</h3>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Upload a CSV file with a phone number column and, optionally, a name column. Save from Excel or Google Sheets as "CSV". Up to :max rows.', ['max' => number_format($maxRows)]) }}
                </p>
            </div>

            <pre class="text-xs bg-gray-50 rounded-md p-3 text-gray-600">Name,Phone
Ama Mensah,0241234567
Kofi,+233 20 123 4567</pre>

            <ul class="text-xs text-gray-500 list-disc list-inside space-y-0.5">
                <li>{{ __('Numbers are checked and saved as +233…. Numbers missing their leading 0 are fixed.') }}</li>
                <li>{{ __('Numbers already in the list are kept as they are. Duplicates and invalid numbers are skipped.') }}</li>
                <li>{{ __('New contacts are included in "All customers" SMS broadcasts.') }}</li>
            </ul>

            <div>
                <input type="file" name="file" accept=".csv,text/csv" required class="block w-full text-sm">
                <x-input-error class="mt-2" :messages="$errors->get('file')" />
            </div>

            <x-primary-button>{{ __('Upload and import') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

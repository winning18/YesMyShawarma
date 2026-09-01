<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add stock item') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('dashboard.stock.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            @isset($branches)
                <div>
                    <x-input-label for="branch_id" :value="__('Branch')" required />
                    <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('branch_id')" />
                </div>
            @endisset

            @include('dashboard.stock.partials.fields', ['item' => null])

            <x-primary-button>{{ __('Add item') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

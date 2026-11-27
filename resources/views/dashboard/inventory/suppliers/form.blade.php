{{-- Add or change a supplier (R4). --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $supplier ? $supplier->name : __('Add supplier') }}</h2></x-slot>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <form method="POST" action="{{ $supplier ? route('dashboard.inventory.suppliers.update', $supplier) : route('dashboard.inventory.suppliers.store') }}" class="bg-white shadow rounded-lg p-6 space-y-4">
            @csrf
            @if ($supplier) @method('PUT') @endif
            @foreach (['name' => [__('Name'), true], 'phone' => [__('Phone (optional)'), false], 'email' => [__('Email (optional)'), false], 'address' => [__('Address (optional)'), false]] as $field => [$label, $required])
                <div>
                    <x-input-label :for="$field" :value="$label" :required="$required" />
                    <x-text-input :id="$field" :name="$field" class="mt-1 block w-full" :value="old($field, $supplier?->{$field})" :required="$required" />
                    <x-input-error class="mt-2" :messages="$errors->get($field)" />
                </div>
            @endforeach
            <div>
                <x-input-label for="notes" :value="__('Notes (optional)')" />
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes', $supplier?->notes) }}</textarea>
            </div>
            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
                <a href="{{ route('dashboard.inventory.suppliers.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Back to suppliers') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>

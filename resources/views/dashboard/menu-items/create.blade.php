<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add menu item') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('dashboard.menu-items.store') }}" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            @include('dashboard.menu-items.partials.fields', ['menuItem' => null])

            <div>
                <x-input-label for="image" :value="__('Photo (optional)')" />
                <input type="file" id="image" name="image" accept="image/*" class="mt-1 block w-full text-sm">
                <x-input-error class="mt-2" :messages="$errors->get('image')" />
            </div>

            <x-primary-button>{{ __('Create item') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

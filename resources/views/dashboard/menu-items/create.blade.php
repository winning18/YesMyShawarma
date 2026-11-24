<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add menu item') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('dashboard.menu-items.store') }}" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            @include('dashboard.menu-items.partials.fields', ['menuItem' => null])

            <x-image-upload rule="menu_item" name="image" :label="__('Photo (optional)')" />

            <x-primary-button>{{ __('Create item') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add category') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('dashboard.categories.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            @include('dashboard.categories.partials.fields', ['category' => null])

            <x-primary-button>{{ __('Create category') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

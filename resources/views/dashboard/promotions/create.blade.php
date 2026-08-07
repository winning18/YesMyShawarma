<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add promotion') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('dashboard.promotions.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            @include('dashboard.promotions.partials.fields', ['promotion' => null])

            <x-primary-button>{{ __('Create promotion') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

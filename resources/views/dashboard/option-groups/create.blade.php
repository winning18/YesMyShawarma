<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add option group') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('dashboard.option-groups.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            @include('dashboard.option-groups.partials.fields', ['optionGroup' => null])

            <x-primary-button>{{ __('Create option group') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

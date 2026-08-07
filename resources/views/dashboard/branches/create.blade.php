<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add branch') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('dashboard.branches.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            @include('dashboard.branches.partials.fields', ['branch' => null])

            <x-primary-button>{{ __('Create branch') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

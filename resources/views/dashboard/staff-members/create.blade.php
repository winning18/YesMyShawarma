<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add staff member') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('dashboard.staff-members.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            @include('dashboard.staff-members.partials.fields', ['staffMember' => null])

            <p class="text-sm text-gray-500">{{ __('Add their photo after creating them.') }}</p>

            <x-primary-button>{{ __('Add staff member') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

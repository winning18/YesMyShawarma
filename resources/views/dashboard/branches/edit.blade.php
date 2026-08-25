<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $branch->name }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.branches.update', $branch) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            @include('dashboard.branches.partials.fields', ['branch' => $branch])

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
                <a href="{{ route('dashboard.branches.index') }}" class="text-sm text-gray-600 hover:underline">
                    {{ __('Back to branches') }}
                </a>
            </div>
        </form>
    </div>
</x-app-layout>

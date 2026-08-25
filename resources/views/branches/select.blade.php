<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Choose your branch') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    <p class="mb-4 text-sm text-gray-600">
                        {{ __('Your account holds roles at more than one branch. Pick which one you\'re working from.') }}
                    </p>

                    <form method="POST" action="{{ route('branches.select.store') }}" class="space-y-4">
                        @csrf

                        @foreach ($branches as $branch)
                            <label class="flex items-center gap-3 border rounded-lg p-3 cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="branch_id" value="{{ $branch->id }}" required>
                                <span>{{ $branch->name }}</span>
                            </label>
                        @endforeach

                        <x-primary-button>{{ __('Continue') }}</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

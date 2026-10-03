<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Start your shift') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    <p class="mb-4 text-sm text-gray-600">
                        {{ __("You're assigned to more than one branch. Pick which one you're working from today to start your shift.") }}
                    </p>

                    <form method="POST" action="{{ route('branches.select.store') }}" class="space-y-4">
                        @csrf

                        @foreach ($branches as $branch)
                            <label class="flex items-center gap-3 border rounded-lg p-3 cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="branch_id" value="{{ $branch->id }}" @checked($branch->id === $currentBranchId) required>
                                <span>{{ $branch->name }}</span>
                                @if ($branch->id === $currentBranchId)
                                    <span class="ml-auto text-xs font-medium text-green-700">{{ __('Currently serving') }}</span>
                                @endif
                            </label>
                        @endforeach

                        <div class="border-t border-gray-100 pt-4">
                            <label for="starting_cash" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Starting cash (GHS), optional') }}</label>
                            <input id="starting_cash" type="number" step="0.01" min="0" name="starting_cash" value="{{ old('starting_cash') }}" class="w-full rounded-md border-gray-300 text-sm" placeholder="0.00">
                            <p class="text-xs text-gray-400 mt-1">{{ __('For making change. This is not counted as part of today\'s sales.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('starting_cash')" />
                        </div>

                        <x-primary-button>{{ __('Start shift') }}</x-primary-button>
                    </form>

                    {{--
                        A plain page, not a modal — staff reaching this
                        before the dashboard exists can't just click
                        through a backdrop to escape it the way the
                        dashboard's own forced start-shift modal allows.
                        Same escape hatch as that modal provides: log out
                        instead of starting a shift right now.
                    --}}
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline">
                            {{ __('Not ready? Log out instead.') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $targetUser->name }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif
        @error('role')
            <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>
        @enderror

        <div class="bg-white shadow rounded-lg p-6">
            <p class="text-sm text-gray-500">{{ $targetUser->email }}</p>
            <p class="text-sm text-gray-500">{{ $targetUser->phone ?? __('No phone on file') }}</p>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('Roles') }}</h3>

            <div class="space-y-2 mb-6">
                @forelse ($roles as $assignment)
                    <div class="flex items-center justify-between text-sm">
                        <span>{{ ucfirst($assignment['role']) }}{{ $assignment['branch'] ? ' · '.$assignment['branch']->name : '' }}</span>

                        <form method="POST" action="{{ route('dashboard.users.roles.remove', $targetUser) }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="role" value="{{ $assignment['role'] }}">
                            <input type="hidden" name="branch_id" value="{{ $assignment['branch']?->id }}">
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">{{ __('No roles assigned.') }}</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('dashboard.users.roles.add', $targetUser) }}" class="flex items-end gap-3">
                @csrf

                <div class="flex-1">
                    <x-input-label for="role" :value="__('Add role')" />
                    <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300" required>
                        @foreach ($availableRoles as $role)
                            <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1">
                    <x-input-label for="branch_id" :value="__('At branch')" />
                    <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <x-primary-button>{{ __('Add') }}</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>

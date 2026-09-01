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
        @error('user')
            <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>
        @enderror
        @error('to_branch_id')
            <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>
        @enderror

        @if (session('temporary_password'))
            <div class="rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-3 space-y-1">
                <p class="font-semibold">{{ __('One-time password') }}</p>
                <p class="font-mono text-base tracking-wide">{{ session('temporary_password') }}</p>
                <p>{{ __('Shown once. Pass it to :name yourself. They\'ll be forced to set their own on first login.', ['name' => $targetUser->name]) }}</p>
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-6">
            <p class="text-sm text-gray-500">{{ $targetUser->email }}</p>
            <p class="text-sm text-gray-500">{{ $targetUser->phone ?? __('No phone on file') }}</p>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ __('Roles') }}</h3>

            <div class="space-y-3 mb-6">
                @forelse ($roles as $assignment)
                    <div class="text-sm">
                        <div class="flex items-center justify-between">
                            <span>{{ str($assignment['role'])->headline() }}{{ $assignment['branch'] ? ' · '.$assignment['branch']->name : '' }}</span>

                            @if ($canManage)
                                <form method="POST" action="{{ route('dashboard.users.roles.remove', $targetUser) }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="role" value="{{ $assignment['role'] }}">
                                    <input type="hidden" name="branch_id" value="{{ $assignment['branch']?->id }}">
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                                </form>
                            @endif
                        </div>

                        @if ($assignment['branch'] && $assignment['role'] !== 'owner')
                        @can('changeBranch', [$targetUser, $assignment['role'], $assignment['branch']->id])
                            <form
                                method="POST" action="{{ route('dashboard.users.change_branch', $targetUser) }}"
                                class="flex items-end gap-2 mt-1.5"
                            >
                                @csrf
                                <input type="hidden" name="role" value="{{ $assignment['role'] }}">
                                <input type="hidden" name="from_branch_id" value="{{ $assignment['branch']->id }}">

                                <div class="flex-1">
                                    <select name="to_branch_id" class="block w-full rounded-md border-gray-300 text-xs" required>
                                        <option value="">{{ __('Move to branch…') }}</option>
                                        @foreach ($branches as $branch)
                                            @if ($branch->id !== $assignment['branch']->id)
                                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>

                                <button type="submit" class="shrink-0 px-3 py-1.5 text-xs font-semibold text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                                    {{ __('Move') }}
                                </button>
                            </form>
                        @endcan
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400">{{ __('No roles assigned.') }}</p>
                @endforelse
            </div>

            @if ($canManage)
                <form method="POST" action="{{ route('dashboard.users.roles.add', $targetUser) }}" class="flex items-end gap-3">
                    @csrf

                    <div class="flex-1">
                        <x-input-label for="role" :value="__('Add role')" required />
                        <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300" required>
                            @foreach ($availableRoles as $role)
                                <option value="{{ $role }}">{{ str($role)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex-1">
                        <x-input-label for="branch_id" :value="__('At branch')" required />
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-primary-button>{{ __('Add') }}</x-primary-button>
                </form>
            @endif
        </div>

        @if ($canManage && $targetUser->id !== auth()->id())
            <div class="bg-white shadow rounded-lg p-6 border border-red-100">
                <h3 class="font-semibold text-red-700 mb-1">{{ __('Delete account') }}</h3>
                <p class="text-sm text-gray-500 mb-4">
                    {{ __('Removes their access and every role immediately, and clears their name, email and phone. The email and phone become free to use on a new account right away. Past orders and shifts they were attributed to are unaffected.') }}
                </p>

                <form
                    method="POST" action="{{ route('dashboard.users.destroy', $targetUser) }}"
                    onsubmit="return confirm({{ Js::from(__('Delete :name\'s account? This cannot be undone from here.', ['name' => $targetUser->name])) }})"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700">
                        {{ __('Delete account') }}
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>

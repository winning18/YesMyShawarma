<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Users') }}</h2>
            @if ($canCreate)
                <a href="{{ route('dashboard.users.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                    {{ __('Add user') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow rounded-lg divide-y divide-gray-100">
            @foreach ($users as $row)
                <div class="p-4 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-800">{{ $row['user']->name }}</p>
                        <p class="text-sm text-gray-500">{{ $row['user']->email }}</p>

                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($row['roles'] as $assignment)
                                <span class="text-xs px-2 py-1 rounded-md bg-gray-100 text-gray-700">
                                    {{ str($assignment['role'])->headline() }}{{ $assignment['branch'] ? ' · '.$assignment['branch']->name : '' }}
                                </span>
                            @empty
                                <span class="text-xs text-gray-400">{{ __('No roles assigned') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <a href="{{ route('dashboard.users.edit', $row['user']) }}" class="shrink-0 text-sm text-gray-600 hover:underline">
                        {{ __('Edit') }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>

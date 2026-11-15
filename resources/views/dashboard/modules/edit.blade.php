<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Modules') }}</h2>
    </x-slot>

    <div class="max-w-2xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">{{ __('The counter POS, kitchen board, menu, branches, staff, shifts, sales reports and order history are always included. Switch on the extras this business uses. A module that\'s off disappears for everyone; its records are kept and come back when you switch it on again.') }}</p>

        <form method="POST" action="{{ route('dashboard.modules.update') }}" class="bg-white shadow rounded-lg divide-y divide-gray-100">
            @csrf
            @method('PUT')

            @foreach ($modules as $module)
                <label class="flex items-start gap-3 px-5 py-4 {{ $module['ready'] ? 'cursor-pointer' : 'opacity-60' }}">
                    @if ($module['ready'])
                        <input type="hidden" name="modules[{{ $module['key'] }}]" value="0">
                        <input type="checkbox" name="modules[{{ $module['key'] }}]" value="1" @checked($module['on']) class="mt-1 rounded border-gray-300">
                    @else
                        <input type="checkbox" checked disabled class="mt-1 rounded border-gray-300">
                    @endif
                    <span>
                        <span class="block text-sm font-medium text-gray-800">{{ __($module['label']) }}</span>
                        <span class="block text-xs text-gray-500">{{ __($module['description']) }}</span>
                        @if (! $module['ready'])
                            <span class="block text-xs text-gray-500 mt-1">{{ __('Always on for now: its switch comes in a later update.') }}</span>
                        @elseif ($module['blockedBy'])
                            <span class="block text-xs text-amber-700 mt-1">{{ __('Needs :modules switched on to work.', ['modules' => implode(', ', array_map('__', $module['blockedBy']))]) }}</span>
                        @elseif ($module['requiresLabels'])
                            <span class="block text-xs text-gray-400 mt-1">{{ __('Works with :modules.', ['modules' => implode(', ', array_map('__', $module['requiresLabels']))]) }}</span>
                        @endif
                    </span>
                </label>
            @endforeach

            <div class="px-5 py-4">
                <x-primary-button>{{ __('Save modules') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Staff members') }}</h2>
            <a href="{{ route('dashboard.staff-members.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                {{ __('Add staff member') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            {{ __('Shown on the About page\'s "Meet our staff" section, in this order. Only active members are shown there.') }}
        </p>

        @if ($staffMembers->isEmpty())
            <p class="text-sm text-gray-500">{{ __('No staff members yet. Add one to show them on the About page.') }}</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach ($staffMembers as $staffMember)
                    <div class="bg-white shadow rounded-lg p-4 flex flex-col items-center text-center gap-1">
                        <img
                            src="{{ $staffMember->photoUrl() ?? asset('images/favicon.png') }}"
                            alt="{{ $staffMember->name }}"
                            class="w-20 h-20 rounded-full object-cover bg-gray-100 mb-2"
                        >

                        <p class="font-semibold text-gray-800">{{ $staffMember->name }}</p>
                        <p class="text-sm text-gray-500">{{ $staffMember->title ?? __('No title set') }}</p>

                        @unless ($staffMember->is_active)
                            <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-500">{{ __('Inactive') }}</span>
                        @endunless

                        <div class="flex items-center gap-3 mt-3">
                            <a href="{{ route('dashboard.staff-members.edit', $staffMember) }}" class="text-sm text-gray-600 hover:underline">
                                {{ __('Edit') }}
                            </a>

                            <form
                                method="POST" action="{{ route('dashboard.staff-members.destroy', $staffMember) }}"
                                onsubmit="return confirm({{ Js::from(__('Delete :name? This cannot be undone.', ['name' => $staffMember->name])) }})"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>

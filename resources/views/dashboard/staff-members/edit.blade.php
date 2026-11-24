<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $staffMember->name }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow rounded-lg p-6 flex items-center gap-6">
            <img
                src="{{ $staffMember->photoUrl() ?? 'data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 80 80%27%3E%3Crect width=%2780%27 height=%2780%27 fill=%27%23f3f4f6%27/%3E%3Ccircle cx=%2740%27 cy=%2731%27 r=%2713%27 fill=%27%239ca3af%27/%3E%3Cpath d=%27M16 70c3-14 13-21 24-21s21 7 24 21%27 fill=%27%239ca3af%27/%3E%3C/svg%3E' }}"
                alt="{{ $staffMember->name }}"
                class="w-20 h-20 rounded-full object-cover bg-gray-100 shrink-0"
            >

            <div class="flex-1 min-w-0 space-y-2">
                <form
                    method="POST"
                    action="{{ route('dashboard.staff-members.image.update', $staffMember) }}"
                    enctype="multipart/form-data"
                    class="space-y-3"
                >
                    @csrf
                    <x-image-upload rule="staff" name="image" :label="__('Photo')" required />
                    <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Upload') }}
                    </button>
                </form>

                @if ($staffMember->photo_path)
                    <form method="POST" action="{{ route('dashboard.staff-members.image.destroy', $staffMember) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Remove photo') }}</button>
                    </form>
                @endif

                @error('image')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <form method="POST" action="{{ route('dashboard.staff-members.update', $staffMember) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            @include('dashboard.staff-members.partials.fields', ['staffMember' => $staffMember])

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
                <a href="{{ route('dashboard.staff-members.index') }}" class="text-sm text-gray-600 hover:underline">
                    {{ __('Back to staff members') }}
                </a>
            </div>
        </form>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Branches') }}</h2>
            <a href="{{ route('dashboard.branches.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                {{ __('Add branch') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        @foreach ($branches as $branch)
            <div class="bg-white shadow rounded-lg p-6 flex flex-col sm:flex-row items-start gap-6">
                <div class="shrink-0">
                    <x-branch-image :branch="$branch" class="w-32 h-32 rounded-md" />
                </div>

                <div class="flex-1 min-w-0 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800">
                                {{ $branch->name }}
                                @unless ($branch->is_active)
                                    <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 align-middle">{{ __('Inactive') }}</span>
                                @endunless
                            </p>
                            <p class="text-sm text-gray-500">{{ $branch->address }} · {{ $branch->phone }}</p>
                            <p class="text-sm text-gray-500">{{ $branch->opens_at }}–{{ $branch->closes_at }}</p>
                        </div>

                        <div class="shrink-0 flex items-center gap-3">
                            <form method="POST" action="{{ route('dashboard.branches.toggle-accepting-orders', $branch) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="text-xs px-2 py-1 rounded-md {{ $branch->is_accepting_orders ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}"
                                >{{ $branch->is_accepting_orders ? __('Accepting orders') : __('Paused') }}</button>
                            </form>

                            <a href="{{ route('dashboard.branches.edit', $branch) }}" class="text-sm text-gray-600 hover:underline">
                                {{ __('Edit') }}
                            </a>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4 flex items-center gap-3">
                        <form
                            method="POST"
                            action="{{ route('dashboard.branches.image.update', $branch) }}"
                            enctype="multipart/form-data"
                            class="flex items-center gap-3"
                        >
                            @csrf
                            <input type="file" name="image" accept="image/*" required class="text-sm">
                            <button type="submit" class="shrink-0 px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                                {{ __('Upload') }}
                            </button>
                        </form>

                        @if ($branch->image_path)
                            <form method="POST" action="{{ route('dashboard.branches.image.destroy', $branch) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="shrink-0 text-sm text-red-600 hover:underline">{{ __('Remove image') }}</button>
                            </form>
                        @endif
                    </div>
                    @error('image')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>

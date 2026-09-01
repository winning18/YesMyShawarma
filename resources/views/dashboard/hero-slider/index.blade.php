<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Hero slider') }}</h2>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            {{ __('Every category can have a hero photo. Only categories with a photo uploaded here actually appear on the home page slider, in this order. A new category is ready to use the moment it exists, no code changes needed.') }}
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach ($categories as $category)
                <div class="bg-white shadow rounded-lg p-4 flex flex-col gap-3">
                    @if ($category->heroImageUrl())
                        <img src="{{ $category->heroImageUrl() }}" alt="{{ $category->name }}" class="w-full aspect-[2.5/1] object-cover rounded-md">
                    @else
                        <div class="w-full aspect-[2.5/1] rounded-md bg-gray-100 flex items-center justify-center text-xs text-gray-400 text-center px-2">
                            {{ __('No hero image') }}
                        </div>
                    @endif

                    <div>
                        <p class="font-semibold text-gray-800 text-sm">
                            {{ $category->name }}
                            @if ($category->hero_image_path)
                                <span class="text-xs px-2 py-0.5 rounded-md bg-green-50 text-green-700 align-middle">{{ __('On home page') }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $category->tagline ?: __('No tagline set.') }}
                            <a href="{{ route('dashboard.categories.edit', $category) }}" class="text-gray-400 hover:underline">{{ __('Edit') }}</a>
                        </p>
                    </div>

                    <div class="border-t border-gray-100 pt-3 space-y-2">
                        <form
                            method="POST"
                            action="{{ route('dashboard.categories.image.update', $category) }}"
                            enctype="multipart/form-data"
                            class="space-y-2"
                        >
                            @csrf
                            <input type="file" name="image" accept="image/*" required class="block w-full text-xs">
                            <p class="text-xs text-gray-400">{{ __('Recommended: 1280×512px, landscape (2.5:1), up to 4MB.') }}</p>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="submit" class="shrink-0 px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900">
                                    {{ __('Upload') }}
                                </button>

                                @if ($category->hero_image_path)
                                    <button
                                        type="submit" form="remove-hero-{{ $category->id }}"
                                        class="shrink-0 text-xs text-red-600 hover:underline"
                                    >{{ __('Remove image') }}</button>
                                @endif
                            </div>
                        </form>

                        @if ($category->hero_image_path)
                            <form id="remove-hero-{{ $category->id }}" method="POST" action="{{ route('dashboard.categories.image.destroy', $category) }}">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif

                        @error('image')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>

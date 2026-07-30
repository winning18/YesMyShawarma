<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Menu item images') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-10">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        @foreach ($categories as $category)
            @if ($category->menuItems->isNotEmpty())
                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-4">{{ $category->name }}</h3>

                    <div class="space-y-4">
                        @foreach ($category->menuItems as $item)
                            <div class="bg-white shadow rounded-lg p-6 flex items-start gap-6">
                                <x-product-image :item="$item" class="w-24 h-24 shrink-0" />

                                <div class="flex-1">
                                    <p class="font-semibold text-gray-800">{{ $item->name }}</p>

                                    <form
                                        method="POST"
                                        action="{{ route('dashboard.menu-items.image.update', $item) }}"
                                        enctype="multipart/form-data"
                                        class="mt-3 flex items-center gap-3"
                                    >
                                        @csrf
                                        <input type="file" name="image" accept="image/*" required class="text-sm">
                                        <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                                            {{ __('Upload') }}
                                        </button>
                                    </form>
                                    @error('image')
                                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                    @enderror

                                    @if ($item->image_path)
                                        <form method="POST" action="{{ route('dashboard.menu-items.image.destroy', $item) }}" class="mt-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Remove image') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
</x-app-layout>

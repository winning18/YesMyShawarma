<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight font-mono">{{ $promotion->code }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.promotions.update', $promotion) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            @include('dashboard.promotions.partials.fields', ['promotion' => $promotion])

            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </form>

        {{--
            Deliberately its own form, siblings with the one above rather
            than nested inside it — a <form> nested inside another <form>
            is invalid HTML, and browsers silently close the OUTER form the
            moment they hit the inner one's closing tag, stranding every
            field after it (the Save button included) outside any form at
            all, so it does nothing when clicked.
        --}}
        @if ($promotion->is_automatic)
            <div class="bg-white shadow rounded-lg p-6 space-y-3">
                <x-input-label :value="__('Homepage banner image (optional)')" />
                @if ($promotion->bannerImageUrl())
                    <img src="{{ $promotion->bannerImageUrl() }}" alt="" class="max-h-32 rounded-md">
                @endif
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('dashboard.promotions.banner-image.update', $promotion) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="image" accept="image/*" class="text-sm">
                        <x-secondary-button type="submit">{{ __('Upload') }}</x-secondary-button>
                    </form>
                    @if ($promotion->bannerImageUrl())
                        <form method="POST" action="{{ route('dashboard.promotions.banner-image.destroy', $promotion) }}" onsubmit="return confirm('{{ __('Remove the banner image?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.promotions.destroy', $promotion) }}" onsubmit="return confirm('{{ __('Remove this promotion?') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Remove promotion') }}</button>
        </form>
    </div>
</x-app-layout>

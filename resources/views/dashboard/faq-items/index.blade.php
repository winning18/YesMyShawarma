<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Website content') }}: {{ __('FAQ questions') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        <a href="{{ route('dashboard.site-pages.index') }}" class="text-sm text-gray-500 underline">&larr; {{ __('All pages') }}</a>

        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">{{ __('Shown on the FAQ page in this order (lowest number first). With no questions, the FAQ page is taken off the site.') }}</p>

        @foreach ($items as $item)
            <details class="bg-white shadow rounded-lg" @if ($errors->any() && (int) old('faq_item_id') === $item->id) open @endif>
                <summary class="cursor-pointer px-5 py-3 text-sm font-medium text-gray-800 flex items-center justify-between gap-4">
                    <span>{{ $item->sort_order }}. {{ $item->question }}</span>
                    <span class="text-xs font-semibold text-indigo-600 shrink-0">{{ __('Edit') }}</span>
                </summary>
                <div class="px-5 pb-5 space-y-3">
                    <form method="POST" action="{{ route('dashboard.faq-items.update', $item) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="faq_item_id" value="{{ $item->id }}">
                        @include('dashboard.faq-items.partials.fields', ['item' => $item, 'prefix' => 'faq-'.$item->id])
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </form>
                    <form method="POST" action="{{ route('dashboard.faq-items.destroy', $item) }}" onsubmit="return confirm('{{ __('Remove this question?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 underline">{{ __('Remove') }}</button>
                    </form>
                </div>
            </details>
        @endforeach

        <form method="POST" action="{{ route('dashboard.faq-items.store') }}" class="bg-white shadow rounded-lg p-5 space-y-3">
            @csrf
            <h3 class="font-semibold text-gray-800">{{ __('Add a question') }}</h3>
            @include('dashboard.faq-items.partials.fields', ['item' => null, 'prefix' => 'faq-new'])
            <x-primary-button>{{ __('Add question') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>

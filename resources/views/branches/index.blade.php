<x-customer-layout title="Branches · {{ config('app.name') }}">
    <h1 class="text-2xl font-bold mb-6">{{ __('Our branches') }}</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        @foreach ($branches as $branch)
            <div class="border border-brand-gray-100 rounded-lg p-6">
                <p class="font-semibold text-lg">{{ $branch->name }}</p>
                <p class="text-sm text-brand-gray-500 mt-1">{{ $branch->address }}</p>
                <p class="text-sm text-brand-gray-500">
                    {{ __('Open') }} {{ \Illuminate\Support\Carbon::parse($branch->opens_at)->format('g:ia') }}
                    – {{ \Illuminate\Support\Carbon::parse($branch->closes_at)->format('g:ia') }}
                </p>
                <a href="tel:{{ $branch->phone }}" class="text-sm text-brand-gray-500 underline">{{ $branch->phone }}</a>

                <a
                    href="{{ route('branches.pick', $branch) }}"
                    class="mt-4 inline-block px-4 py-2 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark"
                >
                    {{ __('Order from here') }}
                </a>
            </div>
        @endforeach
    </div>
</x-customer-layout>

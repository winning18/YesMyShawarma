<x-customer-layout>
    <div class="space-y-12">
        <section class="text-center py-12">
            <h1 class="text-3xl sm:text-4xl font-bold mb-4">{{ __('Order from :name', ['name' => config('app.name')]) }}</h1>
            <p class="text-brand-gray-500 mb-8">{{ __('Shawarma, burgers, sandwiches, hot dogs, loaded fries and drinks — order for pickup at Ga Odumase or Pokuase Y-Junction.') }}</p>
            <a href="{{ route('branches.index') }}" class="inline-block px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">
                {{ __('Order now') }}
            </a>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-4">{{ __('Our branches') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($branches as $branch)
                    <div class="border border-brand-gray-100 rounded-lg p-5">
                        <p class="font-semibold">{{ $branch->name }}</p>
                        <p class="text-sm text-brand-gray-500">{{ $branch->address }}</p>
                        <p class="text-sm text-brand-gray-500 mb-3">
                            {{ __('Open') }} {{ \Illuminate\Support\Carbon::parse($branch->opens_at)->format('g:ia') }}
                            – {{ \Illuminate\Support\Carbon::parse($branch->closes_at)->format('g:ia') }}
                        </p>
                        <a href="{{ route('branches.pick', $branch) }}" class="text-sm font-semibold text-brand-red hover:text-brand-red-dark">
                            {{ __('Order from here →') }}
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-customer-layout>

<div {{ $attributes->merge(['class' => 'rounded-lg border text-sm px-4 py-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-center '.($automatic ? 'bg-brand-yellow-light border-brand-yellow' : 'border-brand-gray-100')]) }}>
    <span>
        @if ($automatic)
            {{ __('We picked') }} <strong>{{ $branch->name }}</strong> {{ __('for you.') }}
        @else
            {{ __('Ordering from') }} <strong>{{ $branch->name }}</strong>
        @endif
    </span>
    <a href="{{ route('branches.index') }}" class="underline text-brand-gray-500 hover:text-brand-black">{{ __('Change') }}</a>
    <x-nearest-branch-button :label="__('Find nearest')" class="text-brand-red hover:text-brand-red-dark" />
</div>

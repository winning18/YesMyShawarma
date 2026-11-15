{{-- The business's own logo and name over the sign-in form: which install this is. --}}
@php $business = app(\App\Services\Settings\BusinessProfile::class); @endphp
<div class="text-center">
    @if ($business->logoUrl())
        <img src="{{ $business->logoUrl() }}" alt="" class="h-16 w-auto max-w-full mx-auto mb-4">
    @endif
    <h1 class="text-3xl font-extrabold tracking-tight">{{ $business->name() }}</h1>
    <p class="mt-1 text-gray-600">{{ $subheading }}</p>
</div>

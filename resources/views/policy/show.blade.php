{{-- Terms, Refund, Privacy and Cookie policies: text written under Website content (PolicyController). --}}
<x-customer-layout :title="$meta['title']" :og-description="$meta['description']">
    <x-slot name="pageHeader">{{ $heading }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black page-content">
        {{ $body }}
    </div>
</x-customer-layout>

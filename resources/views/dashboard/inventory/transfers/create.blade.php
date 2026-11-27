{{-- Send stock to another branch (R4). --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Send stock from :branch', ['branch' => $branch->name]) }}</h2></x-slot>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <form method="POST" action="{{ route('dashboard.inventory.transfers.store') }}" class="bg-white shadow rounded-lg p-6 space-y-5">
            @csrf
            <div>
                <x-input-label for="to_branch_id" :value="__('Send to')" required />
                <select id="to_branch_id" name="to_branch_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('Choose a branch') }}</option>
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </div>
            @include('dashboard.inventory.partials.product-lines', ['mode' => 'send'])
            <p class="text-xs text-gray-500">{{ __('Counted in each product\'s own unit (tablets, pieces). The stock leaves this branch when you send; the other branch confirms what arrives.') }}</p>
            <div>
                <x-input-label for="note" :value="__('Note (optional)')" />
                <textarea id="note" name="note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
            </div>
            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Send') }}</x-primary-button>
                <a href="{{ route('dashboard.inventory.transfers.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>

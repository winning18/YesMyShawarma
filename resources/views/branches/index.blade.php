<x-customer-layout title="Branches · {{ config('app.name') }}" main-class="max-w-7xl">
    <x-slot name="pageHeader">{{ __('Our branches') }}</x-slot>

    <div x-data="branchSearch(@js($branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'address' => $b->address])->values()))">
        <div class="max-w-xl mx-auto mb-8">
            <input
                type="search" x-model="query"
                placeholder="{{ __('Search by branch name or area…') }}"
                autocomplete="off"
                class="w-full rounded-md border-brand-gray-300 focus:border-brand-yellow focus:ring-brand-yellow"
            >
            <p
                x-show="query.trim() !== '' && !branches.some((b) => matches(b))" x-cloak
                class="text-sm text-brand-gray-400 text-center mt-2"
            >{{ __('No branches match your search.') }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($branches as $branch)
                <x-branch-card
                    :branch="$branch"
                    :selected="$selectedBranchId === $branch->id"
                    x-show="matches({{ Js::from(['id' => $branch->id, 'name' => $branch->name, 'address' => $branch->address]) }})"
                />
            @endforeach
        </div>
    </div>

    <script>
        function branchSearch(branches) {
            return {
                branches,
                query: '',

                matches(branch) {
                    const q = this.query.trim().toLowerCase();
                    if (q === '') return true;

                    return branch.name.toLowerCase().includes(q) || branch.address.toLowerCase().includes(q);
                },
            };
        }
    </script>
</x-customer-layout>

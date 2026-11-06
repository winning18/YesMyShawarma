<form method="POST" action="{{ route('branches.select.store') }}" class="flex items-center gap-2">
    @csrf
    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
    <label for="branch-switcher" class="text-sm text-gray-500">{{ __('Branch') }}</label>
    <select id="branch-switcher" name="branch_id" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm py-1">
        @foreach ($branches as $branch)
            <option value="{{ $branch->id }}" @selected($branch->id === $currentBranchId)>{{ $branch->name }}</option>
        @endforeach
    </select>
    <noscript><button type="submit" class="text-sm underline">{{ __('Switch') }}</button></noscript>
</form>

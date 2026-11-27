@if (session('status'))
    <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="rounded-md bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 space-y-1">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif

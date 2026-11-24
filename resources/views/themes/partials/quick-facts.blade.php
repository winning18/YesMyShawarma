{{-- The strip of quick facts under a theme's hero (ThemeHomeService::quickFacts). --}}
@if ($quickFacts !== [])
    <div class="bg-brand-white border-b border-brand-gray-100">
        <dl class="max-w-6xl mx-auto px-4 grid grid-cols-2 {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][count($quickFacts)] ?? 'md:grid-cols-4' }}">
            @foreach ($quickFacts as $fact)
                <div class="py-5 px-3 flex flex-col-reverse gap-0.5 text-center {{ $loop->first ? '' : 'md:border-l' }} border-brand-gray-100">
                    <dt class="text-xs uppercase tracking-wider text-brand-gray-500">{{ $fact['label'] }}</dt>
                    <dd class="font-heading text-lg sm:text-xl text-brand-black">{{ $fact['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif

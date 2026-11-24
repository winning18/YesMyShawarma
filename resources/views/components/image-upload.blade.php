@props([
    'rule',
    'name',
    'id' => null,
    'required' => false,
    'label' => null,
    'inputClass' => 'block w-full text-sm',
    // Pages with one upload per row (a photo per branch or category) keep
    // each row's errors in its own bag, so a mistake shows on that row only.
    'errorBag' => 'default',
])
{{--
    An image upload with its rules shown and checked (ImageRules, owner
    2026-11-24): the shape, best and smallest size, formats and largest
    file, then — once a photo is chosen — how it will be cropped in each
    place it shows, and a clear red message (before anything is sent) if
    it's too big, too small or the wrong type. The server checks the same
    rules again; its message shows here too.
--}}
@php
    $rules = app(\App\Services\Media\ImageRules::class);
    $config = $rules->slot($rule);
    $id ??= $name;
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $bag = isset($errors) ? $errors->getBag($errorBag) : null;
    $hasError = $bag && $bag->has($errorKey);
    $accept = collect($config['formats'])->map(fn ($f) => 'image/'.($f === 'jpg' ? 'jpeg' : $f))->unique()->join(',');
    $client = [
        'maxBytes' => $config['max_kb'] * 1024,
        'maxLabel' => $rules->size($config['max_kb']),
        'min' => $config['min'],
        'square' => $config['exact_shape'] ?? false,
        'types' => collect($config['formats'])->map(fn ($f) => 'image/'.($f === 'jpg' ? 'jpeg' : $f))->unique()->values(),
        'previews' => collect($config['previews'])->map(fn ($ratio, $label) => ['label' => __($label), 'ratio' => $ratio])->values(),
        'messages' => [
            'type' => __('That file type can\'t be used here. Choose a :formats image.', ['formats' => $rules->formats($rule)]),
            'size' => __('This photo is :size; the most is :max. Make it smaller (sending it to yourself on WhatsApp shrinks it) and try again.'),
            'small' => __('This photo is :size, too small: it would look blurred. Use one at least :min.'),
            'square' => __('The icon must be square. Crop it to a square and try again.'),
            'unreadable' => __('This photo couldn\'t be opened. Try another one, or take a screenshot of it and upload that.'),
        ],
    ];
@endphp
<div x-data="imageUpload(@js($client))" {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700">{{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif</label>
    @endif

    <div class="flex items-start gap-2 rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-xs text-gray-600">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4 mt-px shrink-0 text-gray-400" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 15 5-4 4 3 3-2 6 4" /></svg>
        <p>
            <span class="font-semibold text-gray-700">{{ __($config['label']) }}:</span> {{ $rules->summary($rule) }}.
            <span class="block text-gray-500">{{ __($config['tip']) }}</span>
        </p>
    </div>

    <input
        type="file" name="{{ $name }}" id="{{ $id }}" accept="{{ $accept }}" @if ($required) required @endif
        x-ref="input" @change="check($event)"
        aria-describedby="{{ $id }}-error"
        @if ($hasError) aria-invalid="true" @endif
        class="{{ $inputClass }} rounded-md file:me-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-1.5 file:text-white file:text-xs file:font-semibold {{ $hasError ? 'ring-2 ring-red-500 ring-offset-1' : '' }}"
        :class="error ? 'ring-2 ring-red-500 ring-offset-1' : ''"
    >

    <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-sm text-red-600" x-show="error" x-cloak role="alert">
        <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
        <span x-text="error"></span>
    </p>
    @if ($hasError)
        <x-input-error :messages="$bag->get($errorKey)" x-show="! error" />
    @endif

    <template x-if="preview && ! error && previews.length">
        <div>
            <p class="text-xs text-gray-500 mb-1">{{ __('How it will look (cropped from the centre):') }} <span x-text="dimensions"></span></p>
            <div class="flex flex-wrap items-end gap-3">
                <template x-for="frame in previews" :key="frame.label">
                    <figure class="text-center">
                        <div class="overflow-hidden rounded-md bg-gray-100 border border-gray-200" :style="`width: ${frame.ratio >= 1 ? 120 : Math.round(120 * frame.ratio)}px; aspect-ratio: ${frame.ratio}`">
                            <img :src="preview" alt="" class="w-full h-full object-cover">
                        </div>
                        <figcaption class="mt-1 text-[11px] text-gray-500" x-text="frame.label"></figcaption>
                    </figure>
                </template>
            </div>
        </div>
    </template>
</div>

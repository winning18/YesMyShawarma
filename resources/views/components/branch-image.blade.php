@props(['branch'])

{{--
    branches has no image_path column yet (schema.md doesn't define one) —
    this is deliberately placeholder-only for now, same as menu items
    before their image_path existed. Adding real photo upload needs a
    migration proposed and approved first, per Hard Rule 3.
--}}
<div
    {{ $attributes->merge(['class' => 'bg-brand-gray-100 flex items-center justify-center']) }}
    role="img"
    aria-label="{{ $branch->name }}"
>
    <svg viewBox="0 0 24 24" fill="none" class="w-10 h-10 text-brand-gray-300">
        <path d="M4 21V10.5L12 4l8 6.5V21" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
        <path d="M9.5 21v-6h5v6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
    </svg>
</div>

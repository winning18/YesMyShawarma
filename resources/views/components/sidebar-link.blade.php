@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center px-3 py-2 rounded-md rounded-l-none border-l-4 border-indigo-400 bg-indigo-50 text-sm font-semibold text-indigo-700 transition duration-150 ease-in-out'
            : 'flex items-center px-3 py-2 rounded-md rounded-l-none border-l-4 border-transparent text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

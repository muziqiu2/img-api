@props(['active'])

@php
$classes = "space-x-3 px-4 h-10 w-full flex items-center hover:bg-gray-100 text-gray-700 text-sm rounded-lg transition-colors" . (($active ?? false) ? ' bg-blue-50 text-blue-700 font-medium' : '');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $icon }}
    <span class="tracking-widest">{{ $name }}</span>
</a>

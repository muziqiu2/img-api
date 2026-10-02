@props(['disabled' => false])

<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'mt-1 block w-full rounded-lg bg-white border border-gray-300 focus:border-blue-500 focus:ring-blue-200 focus:ring-2']) !!}>
    {{ $slot }}
</select>

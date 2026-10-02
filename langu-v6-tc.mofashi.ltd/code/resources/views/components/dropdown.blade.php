@props(['classes' => ['left' => 'origin-top-right right-0', 'right' => 'origin-top-left left-0'], 'direction' => 'left', 'labelledby' => 'user-menu-button'])

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false" x-cloak>
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="transform opacity-0 translate-y-9"
         x-transition:enter-end="transform opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 translate-y-0"
         x-transition:leave-end="transform opacity-0 translate-y-9"
         class="absolute z-[9] {{ $classes[$direction] }} mt-2 w-48 rounded-xl shadow-lg border border-gray-200 py-1 bg-white "
         role="menu"
         aria-orientation="vertical"
         aria-labelledby="{{ $labelledby }}"
         tabindex="-1"
         style="display: none"
    >
        {{ $content }}
    </div>
</div>

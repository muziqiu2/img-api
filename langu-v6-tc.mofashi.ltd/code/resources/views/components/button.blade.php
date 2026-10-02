<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex justify-center py-2 px-4 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 shadow-sm']) }}>
    {{ $slot }}
</button>

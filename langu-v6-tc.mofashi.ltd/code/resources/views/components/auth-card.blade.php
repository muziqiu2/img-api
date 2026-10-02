<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 px-4">
    <div>
        {{ $logo }}
    </div>

    <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white overflow-hidden sm:rounded-xl border border-gray-200 shadow-sm">
        {{ $slot }}
    </div>
</div>

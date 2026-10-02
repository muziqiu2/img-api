@if($_is_notice)
    <button type="button" class="bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 border border-gray-200 p-1 hover:bg-gray-50" id="notice-menu-button" aria-expanded="false" aria-haspopup="true">
        <span class="sr-only">Open notice</span>
        <div class="h-8 w-8 rounded-full flex items-center justify-center bg-blue-50">
            <i class="fas fa-envelope text-blue-600"></i>
        </div>
        <span class="px-2 sm:block hidden text-gray-700">公告</span>
    </button>
    @push('scripts')
        <script>
            $('#notice-menu-button').click(function () {
                openNotice();
            });
        </script>
    @endpush
@endif

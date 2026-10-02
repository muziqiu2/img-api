<!-- Strategies dropdown -->
<x-dropdown :labelledby="'strategy-menu-button'">
    <x-slot name="trigger">
        <button type="button" class="bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 border border-gray-200 p-1 hover:bg-gray-50" id="strategy-menu-button" aria-expanded="false" aria-haspopup="true">
            <span class="sr-only">Open strategies menu</span>
            <div class="h-8 w-8 rounded-full flex items-center justify-center bg-blue-50">
                <i class="fas fa-server text-blue-600"></i>
            </div>
            <span class="px-2 sm:block hidden text-gray-700" id="strategy-selected" data-id="0">获取中…</span>
        </button>
    </x-slot>

        <x-slot name="content">
            <div id="strategies">
            @foreach($_group->strategies as $strategy)
                <x-dropdown-link data-id="{{ $strategy->id }}" data-key="{{ $strategy->key }}" href="javascript:void(0)" @click="open = false">{{ $strategy->name }}</x-dropdown-link>
            @endforeach
            </div>
        </x-slot>
</x-dropdown>

@push('scripts')
    <script>
        let defaultStrategy = {{ Auth::check() ? Auth::user()->configs->get('default_strategy') : 0 }} || (localStorage.getItem('strategy') || 0);
        let setStrategy = function (id) {
            let isSelected = false;
            $('#strategies a').each(function () {
                if (parseInt($(this).data('id')) === parseInt(id)) {
                    localStorage.setItem('strategy', id)
                    $('#strategy-selected').text($(this).text()).data('id', id).data('key', $(this).data('key'));
                    isSelected = true;

                    @if(Auth::check())
                        if (defaultStrategy != id) {
                            axios.put('{{ route('settings.strategy.set') }}', {id: id}).then(response => {
                                if (! response.data.status) {
                                    toastr.error(response.data.message);
                                }
                            });
                        }
                    @endif
                }
            });
            if (! isSelected) {
                let $first = $('#strategies a:first-child');
                localStorage.setItem('strategy', $first.data('id'))
                $('#strategy-selected').text($first.text()).data('id', $first.data('id')).data('key', $first.data('key'));
            }
        };

        setStrategy(defaultStrategy);

        $('#strategies a').click(function () {
            setStrategy($(this).data('id'))
        });
    </script>
@endpush

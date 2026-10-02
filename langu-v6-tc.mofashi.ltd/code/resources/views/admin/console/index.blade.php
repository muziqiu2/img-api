@section('title', '系统控制台')

<x-app-layout>
    @if(config('app.debug'))
        <p class="mt-4 p-2 rounded-md text-sm bg-red-500 text-white">
            <i class="fas fa-exclamation-triangle"></i>
            当前系统 debug 已被打开，敏感信息暴露在外，可能会被利用从而影响系统稳定性，生产环境中请务必关闭！
        </p>
    @endif
    <div class="my-6 md:my-9">
        <p class="mb-3 flex items-center gap-2 font-semibold text-lg text-gray-800"><span class="inline-block w-1 h-5 rounded-full bg-blue-600"></span>概览</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-2xl font-bold leading-none text-gray-900 truncate tabular-nums">{{ \App\Utils::shortenNumber(\App\Models\Image::query()->count()) }}</p>
                    <p class="mt-2 text-sm text-gray-500">图片数量</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition-transform duration-200 group-hover:scale-110">
                    <i class="fas fa-images text-xl"></i>
                </div>
            </div>
            <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-2xl font-bold leading-none text-gray-900 truncate tabular-nums">{{ \App\Utils::shortenNumber(\App\Models\Album::query()->count()) }}</p>
                    <p class="mt-2 text-sm text-gray-500">相册数量</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition-transform duration-200 group-hover:scale-110">
                    <i class="fas fa-tags text-xl"></i>
                </div>
            </div>
            <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-2xl font-bold leading-none text-gray-900 truncate tabular-nums">{{ \App\Utils::shortenNumber(\App\Models\User::query()->count()) }}</p>
                    <p class="mt-2 text-sm text-gray-500">用户数量</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition-transform duration-200 group-hover:scale-110">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
            <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-2xl font-bold leading-none text-gray-900 truncate tabular-nums">{{ \App\Utils::formatSize(\App\Models\Image::query()->sum('size') * 1024) }}</p>
                    <p class="mt-2 text-sm text-gray-500">占用储存</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 transition-transform duration-200 group-hover:scale-110">
                    <i class="fas fa-server text-xl"></i>
                </div>
            </div>

            <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-2xl font-bold leading-none text-gray-900 truncate tabular-nums">{{ \App\Utils::shortenNumber($numbers['today']) }}</p>
                    <p class="mt-2 text-sm text-gray-500">今日上传</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 transition-transform duration-200 group-hover:scale-110">
                    <i class="fas fa-upload text-xl"></i>
                </div>
            </div>
            <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-2xl font-bold leading-none text-gray-900 truncate tabular-nums">{{ \App\Utils::shortenNumber($numbers['yesterday']) }}</p>
                    <p class="mt-2 text-sm text-gray-500">昨日上传</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 transition-transform duration-200 group-hover:scale-110">
                    <i class="fas fa-upload text-xl"></i>
                </div>
            </div>
            <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-2xl font-bold leading-none text-gray-900 truncate tabular-nums">{{ \App\Utils::shortenNumber($numbers['week']) }}</p>
                    <p class="mt-2 text-sm text-gray-500">本周上传</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 transition-transform duration-200 group-hover:scale-110">
                    <i class="fas fa-upload text-xl"></i>
                </div>
            </div>
            <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <div class="min-w-0">
                    <p class="text-2xl font-bold leading-none text-gray-900 truncate tabular-nums">{{ \App\Utils::shortenNumber($numbers['month']) }}</p>
                    <p class="mt-2 text-sm text-gray-500">本月上传</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 transition-transform duration-200 group-hover:scale-110">
                    <i class="fas fa-upload text-xl"></i>
                </div>
            </div>
        </div>

        <p class="mb-3 flex items-center gap-2 font-semibold text-lg text-gray-800"><span class="inline-block w-1 h-5 rounded-full bg-blue-600"></span>趋势</p>
        <div class="relative p-4 rounded-xl bg-white h-80 mb-8 shadow-custom border border-gray-200" id="chart">
            <canvas></canvas>
        </div>

        <p class="mb-3 flex items-center gap-2 font-semibold text-lg text-gray-800"><span class="inline-block w-1 h-5 rounded-full bg-blue-600"></span>系统情况</p>
        <div class="relative rounded-xl bg-white mb-8 overflow-hidden shadow-custom border border-gray-200">
            <dl>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">操作系统</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        {{ php_uname() }}
                    </dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">运行环境</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        {{ request()->server('SERVER_SOFTWARE') }}
                    </dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">PHP 版本</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        {{ phpversion() }}
                    </dd>
                </div>
                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">文件上传限制</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        {{ ini_get("upload_max_filesize") }}
                    </dd>
                </div>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">POST 数据最大限制</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        {{ ini_get('post_max_size') }}
                    </dd>
                </div>
            </dl>
        </div>

        <p class="mb-3 flex items-center gap-2 font-semibold text-lg text-gray-800"><span class="inline-block w-1 h-5 rounded-full bg-blue-600"></span>软件信息</p>
        <div class="relative rounded-xl bg-white mb-8 overflow-hidden shadow-custom border border-gray-200">
            <dl>
                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">软件版本</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ \App\Utils::config(\App\Enums\ConfigKey::AppVersion) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/echarts/echarts.min.js') }}"></script>
        <script>
            $(function () {
                'use strict'
                let chartDom = document.getElementById('chart');
                let myChart = echarts.init(chartDom);
                let options;

                options = {
                    responsive: true,
                    title: {
                        text: '近 30 天内统计'
                    },
                    tooltip: {
                        trigger: 'axis'
                    },
                    legend: {
                        top: '10%',
                        type: 'scroll',
                        data: @json($fields)
                    },
                    grid: {
                        left: '3%',
                        right: '3%',
                        bottom: '3%',
                        containLabel: true
                    },
                    toolbox: {
                        show: true,
                        feature: {
                            magicType: {
                                type: ["line", "bar"]
                            },
                            saveAsImage: {}
                        }
                    },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: @json($dates)
                    },
                    yAxis: {
                        type: 'value',
                        minInterval: 1,
                    },
                    series: @json($datasets)
                };

                options && myChart.setOption(options);

                window.onresize = function() {
                    myChart.resize();
                }
            })
        </script>
    @endpush

</x-app-layout>

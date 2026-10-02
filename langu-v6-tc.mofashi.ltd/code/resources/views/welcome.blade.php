@push('styles')
    <link rel="stylesheet" href="{{ asset('css/markdown-css/github-markdown-light.css') }}">
@endpush
<x-guest-layout>
    <div class="min-h-screen flex flex-col bg-gray-50">
        <!-- 顶栏：企业白底 + 细分隔线 -->
        <header class="w-full h-14 bg-white border-b border-gray-200 flex justify-center fixed top-0 z-[9]">
            <div class="container mx-auto px-5 sm:px-10 md:px-10 lg:px-10 xl:px-10 2xl:px-60 flex justify-between items-center">
                <div class="flex justify-start items-center max-w-[70%]">
                    <a href="{{ route('/') }}" class="font-semibold text-gray-900 text-lg truncate">{{ \App\Utils::config(\App\Enums\ConfigKey::AppName) }}</a>
                </div>
                <div class="flex justify-end items-center space-x-3">
                    @includeWhen($_is_notice, 'layouts.notice')
                    @includeWhen($_group->strategies->isNotEmpty(), 'layouts.strategies')
                    @if(Auth::check())
                        @include('layouts.user-nav')
                    @else
                        <a href="{{ route('login') }}" class="text-gray-600 hover:text-blue-600 hover:bg-blue-50 px-3 py-2 rounded-lg text-sm font-medium">登录</a>
                        @if(\App\Utils::config(\App\Enums\ConfigKey::IsEnableRegistration))
                        <a href="{{ route('register') }}" class="text-white bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-sm font-medium shadow-sm">注册</a>
                        @endif
                    @endif
                </div>
            </div>
        </header>

        <!-- 主区：品牌 Hero + 上传 -->
        <main class="flex-1 w-full mx-auto max-w-4xl px-5 sm:px-10 pt-24 pb-16">
            <div class="text-center mb-10">
                <span class="inline-block text-xs font-semibold tracking-widest text-blue-600 uppercase mb-3">Image Hosting</span>
                <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-3 tracking-tight">让每一张图片，久留云端</h1>
                <p class="text-gray-500">{{ \App\Utils::config(\App\Enums\ConfigKey::SiteDescription) }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <x-upload/>
            </div>
        </main>

        <!-- 底部备案 -->
        <footer class="w-full bg-white border-t border-gray-200 py-4">
            <p class="container mx-auto text-center text-gray-500 text-sm">
                Copyright © 2018 - present {{ \App\Utils::config(\App\Enums\ConfigKey::AppName) }}. All rights reserved. &nbsp;<a href="https://beian.miit.gov.cn/" target="_blank" rel="noreferrer" class="text-gray-500 hover:text-blue-600">{{ \App\Utils::config(\App\Enums\ConfigKey::IcpNo) }}</a>&nbsp;请勿上传违反中国大陆和香港法律的图片，违者后果自负。
            </p>
        </footer>
    </div>
    @include('common.notice')
</x-guest-layout>
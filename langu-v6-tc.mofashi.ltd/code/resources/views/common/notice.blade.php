@if($_is_notice)

    <x-modal id="notice-modal">
        {{-- 公告头部：白底 + 蓝色左侧指示条（与 box 组件统一） --}}
        <div class="notice-head">
            <span class="notice-head-bar"></span>
            <span class="notice-head-icon">📣</span>
            <span class="notice-head-text">网站公告</span>
        </div>

        {{-- 公告正文：Markdown 排版 --}}
        <div class="markdown-body">
            {!! (new Parsedown())->parse(\App\Utils::config(\App\Enums\ConfigKey::SiteNotice)) !!}
        </div>

        <div class="mt-5 w-full text-right">
            <x-button type="button" @click="$store.modal.close('notice-modal');" class="notice-ok-btn">我知道了</x-button>
        </div>
    </x-modal>

    <style>
        /* ===== 仅作用于公告弹窗（id 作用域），不影响其他通用弹窗 ===== */
        #notice-modal [class*="md:max-w-2xl"] > div {
            background: #ffffff;
            overflow: hidden;
            padding: 0;
        }

        /* 头部：白底 + 蓝色左侧指示条（与 box 组件统一，无渐变） */
        #notice-modal .notice-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 24px;
            background: #ffffff;
            border-bottom: 1px solid #e4e7ec;
            color: #1f2430;
        }
        #notice-modal .notice-head-bar {
            flex-shrink: 0;
            width: 4px;
            height: 16px;
            border-radius: 999px;
            background: #2563eb;
        }
        #notice-modal .notice-head-icon {
            font-size: 20px;
            line-height: 1;
        }
        #notice-modal .notice-head-text {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: .3px;
        }

        /* 正文区域：内边距 + 滚动（内容超长时不撑破卡片） */
        #notice-modal .markdown-body {
            padding: 20px 24px 6px;
            max-height: 55vh;
            overflow-y: auto;
            color: #374151;
            font-size: 14px;
            line-height: 1.75;
            word-break: break-word;
        }
        #notice-modal .markdown-body h1, #notice-modal .markdown-body h2,
        #notice-modal .markdown-body h3, #notice-modal .markdown-body h4 {
            color: #111827;
            font-weight: 700;
            margin: 1.1em 0 .5em;
            line-height: 1.4;
            border-bottom: 1px solid #eef0f4;
            padding-bottom: 6px;
        }
        #notice-modal .markdown-body h1:first-child,
        #notice-modal .markdown-body h2:first-child,
        #notice-modal .markdown-body h3:first-child { margin-top: 0; }
        #notice-modal .markdown-body h1 { font-size: 20px; }
        #notice-modal .markdown-body h2 { font-size: 18px; }
        #notice-modal .markdown-body h3 { font-size: 16px; }
        #notice-modal .markdown-body h4 { font-size: 15px; }
        #notice-modal .markdown-body p { margin: .6em 0; }
        #notice-modal .markdown-body ul, #notice-modal .markdown-body ol {
            margin: .6em 0;
            padding-left: 1.5em;
            list-style: disc;
        }
        #notice-modal .markdown-body ol { list-style: decimal; }
        #notice-modal .markdown-body li { margin: .25em 0; }
        #notice-modal .markdown-body blockquote {
            margin: .8em 0;
            padding: 8px 14px;
            border-left: 4px solid #2563eb;
            background: #eff4ff;
            border-radius: 6px;
            color: #4b5563;
        }
        #notice-modal .markdown-body code {
            background: #eef0f4;
            color: #d6336c;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 13px;
        }
        #notice-modal .markdown-body pre {
            background: #1f2430;
            color: #e5e7eb;
            padding: 14px 16px;
            border-radius: 10px;
            overflow-x: auto;
            margin: .8em 0;
        }
        #notice-modal .markdown-body pre code { background: transparent; color: inherit; padding: 0; }
        #notice-modal .markdown-body a { color: #2563eb; text-decoration: underline; }
        #notice-modal .markdown-body hr { border: 0; border-top: 1px solid #eef0f4; margin: 1em 0; }

        /* 底部按钮：实心蓝，无渐变无发光 */
        #notice-modal .notice-ok-btn {
            margin-right: 24px;
            margin-bottom: 8px;
            border-radius: 8px;
            padding: 8px 22px;
            background: #2563eb;
            border: none;
            color: #fff;
            font-weight: 500;
        }
        #notice-modal .notice-ok-btn:hover { background: #1d4ed8; }

        /* 暗色模式适配 */
        @media (prefers-color-scheme: dark) {
            #notice-modal [class*="md:max-w-2xl"] > div { background: #1f2430; }
            #notice-modal .notice-head { background: #1f2430; border-bottom-color: #2b3244; color: #f9fafb; }
            #notice-modal .markdown-body { color: #e5e7eb; }
            #notice-modal .markdown-body h1, #notice-modal .markdown-body h2,
            #notice-modal .markdown-body h3, #notice-modal .markdown-body h4 { color: #f9fafb; }
            #notice-modal .markdown-body h1, #notice-modal .markdown-body h2,
            #notice-modal .markdown-body h3, #notice-modal .markdown-body h4 { border-bottom-color: #2b3244; }
            #notice-modal .markdown-body blockquote { background: rgba(37, 99, 235, .15); color: #c7d4ee; }
            #notice-modal .markdown-body code { background: #2b3244; color: #f472b6; }
            #notice-modal .markdown-body a { color: #60a5fa; }
            #notice-modal .markdown-body hr { border-top-color: #2b3244; }
        }
    </style>

    @push('scripts')
        <script>
            let noticeHash = "{{ md5(\App\Utils::config(\App\Enums\ConfigKey::SiteNotice)) }}";

            let openNotice = function () {
                Alpine.store('modal').open('notice-modal');
                localStorage.setItem('notice-hash', noticeHash);
            }

            if (localStorage.getItem('notice-hash') !== noticeHash) {
                setTimeout(function () {
                    openNotice();
                }, 1000)
            }
        </script>
    @endpush
@endif

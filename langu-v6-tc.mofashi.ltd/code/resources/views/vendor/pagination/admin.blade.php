@if ($paginator->hasPages())
<nav role="navigation" aria-label="分页导航" class="admin-pagination">
    <div class="admin-pagination__row">
        {{-- 上一页 --}}
        @if ($paginator->onFirstPage())
            <span class="admin-pagination__btn is-disabled">上一页</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="admin-pagination__btn">上一页</a>
        @endif

        {{-- 页码条：移动端可横向滑动，桌面端居中 --}}
        <div class="admin-pagination__strip">
            <div class="admin-pagination__strip-inner">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="admin-pagination__dots">…</span>
                    @else
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="admin-pagination__num is-current">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="admin-pagination__num">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>
        </div>

        {{-- 下一页 --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="admin-pagination__btn">下一页</a>
        @else
            <span class="admin-pagination__btn is-disabled">下一页</span>
        @endif
    </div>

    <p class="admin-pagination__meta">第 {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }} 页 · 共 {{ $paginator->total() }} 条</p>
</nav>

<style>
    /* 自包含样式，不依赖 Tailwind 编译产物（app.css 为裁剪版，新增类会静默失效） */
    .admin-pagination { margin-top: 12px; user-select: none; }
    .admin-pagination__row { display: flex; align-items: center; gap: 8px; }
    .admin-pagination__btn {
        flex: 0 0 auto;
        display: inline-flex; align-items: center;
        padding: 10px 14px;
        font-size: 14px; line-height: 1;
        color: #374151; background: #fff;
        border: 1px solid #d1d5db; border-radius: 8px;
        text-decoration: none;
    }
    a.admin-pagination__btn:hover { background: #f9fafb; }
    a.admin-pagination__btn:active { background: #f3f4f6; }
    .admin-pagination__btn.is-disabled { color: #9ca3af; cursor: not-allowed; }
    .admin-pagination__strip {
        flex: 1 1 auto; min-width: 0;
        overflow-x: auto; -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .admin-pagination__strip::-webkit-scrollbar { display: none; }
    .admin-pagination__strip-inner {
        display: flex; align-items: center; gap: 4px;
        width: max-content; margin: 0 auto;
    }
    .admin-pagination__num {
        flex: 0 0 auto;
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 36px; height: 36px; padding: 0 8px;
        font-size: 14px;
        color: #374151; background: #fff;
        border: 1px solid #d1d5db; border-radius: 8px;
        text-decoration: none;
    }
    a.admin-pagination__num:hover { background: #f9fafb; }
    .admin-pagination__num.is-current {
        color: #fff; background: #3b82f6; border-color: #3b82f6;
        font-weight: 600;
    }
    .admin-pagination__dots { flex: 0 0 auto; padding: 0 2px; color: #9ca3af; }
    .admin-pagination__meta { margin-top: 8px; text-align: center; font-size: 12px; color: #6b7280; }
    @media (max-width: 640px) {
        .admin-pagination__strip-inner { margin: 0; }
    }
</style>
@endif

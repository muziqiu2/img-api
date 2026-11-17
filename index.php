<?php
require_once 'config.php';

// 网站展示设置（前台文案，后台可修改）
$site = getSiteSettings();

// 当前版本号与项目仓库地址（版本号随更新自动变化）
$appVersion = ltrim(getAppVersion(), 'v');
$repoUrl = 'https://github.com/' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME;

// 统计数据只查询一次并在全页复用。
// 首页是公开高频页面，使用只读统计（不合并缓冲、不写库），
// 避免每次访问都触发 SQLite 写锁与归档检查；缓冲合并由后台 getCallCount 完成。
$stats = getCallCountReadOnly();

// JSON 输出开关与图片访问模式（与后台设置保持一致）
$jsonEnabled = isJsonEnabled();
$imageMode = getImageAccessMode();

// 安全主机名（去除危险字符，防止Host头注入攻击）
$safeHost = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^a-zA-Z0-9\.\-:]/', '', $_SERVER['HTTP_HOST']) : 'example.com';
$safeHost = htmlspecialchars($safeHost, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site['site_title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="keywords" content="随机图片,二次元API,动漫壁纸,图片接口">
    <meta name="description" content="提供高质量随机二次元图片API服务，支持PC/移动端自适应">
    <link rel="icon" type="image/svg+xml" href="public/favicon.svg?v=<?php echo APP_VERSION; ?>">
    <link href="public/css/all.min.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
    <link href="public/css/tw.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
    <script src="public/js/chart.umd.min.js?v=<?php echo APP_VERSION; ?>"></script>
    <script src="public/js/clipboard.min.js?v=<?php echo APP_VERSION; ?>"></script>
</head>
<body class="min-h-screen flex flex-col font-sans">

    <div class="toast-notification" id="copyToast" role="status" aria-live="polite">
        <i class="fas fa-check" aria-hidden="true"></i>
        <span>复制成功！</span>
    </div>

    <!-- 顶部导航 -->
    <header class="sticky top-0 z-40 border-b border-zinc-200 bg-white/80 backdrop-blur">
        <div class="mx-auto max-w-6xl h-14 px-4 sm:px-6 flex items-center justify-between gap-4">
            <a href="./" class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 shrink-0 rounded-lg bg-zinc-900 text-white grid place-items-center" aria-hidden="true"><i class="fas fa-magic text-[13px]"></i></span>
                <span class="font-semibold tracking-tight text-[15px] text-zinc-900 truncate"><?php echo htmlspecialchars($site['site_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <nav class="flex items-center gap-5 sm:gap-6" aria-label="站内导航">
                <a href="#api" class="hidden sm:inline text-sm font-medium text-zinc-600 hover:text-zinc-900 transition-colors">API 接口</a>
                <a href="#guide" class="hidden sm:inline text-sm font-medium text-zinc-600 hover:text-zinc-900 transition-colors">使用指南</a>
                <a href="admin/" class="inline-flex items-center gap-1.5 h-8 px-3.5 rounded-full border border-zinc-300 bg-white text-[13px] font-medium text-zinc-800 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors">
                    <i class="fas fa-sliders-h text-[11px]" aria-hidden="true"></i> 管理后台
                </a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <!-- Hero -->
        <section class="mx-auto max-w-6xl px-4 sm:px-6 pt-16 pb-12 text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3.5 py-1.5 text-[13px] text-zinc-600 shadow-card">
                <i class="fas fa-bolt text-brand text-[11px]" aria-hidden="true"></i> 开源随机图片 API · 开箱即用
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl font-bold tracking-tighter text-zinc-900"><?php echo htmlspecialchars($site['site_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="mt-4 text-base sm:text-lg text-zinc-600 leading-relaxed max-w-xl mx-auto"><?php echo htmlspecialchars($site['site_lead'], ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                <a href="#api" class="inline-flex items-center h-10 px-5 rounded-lg bg-zinc-900 text-white text-sm font-medium hover:bg-zinc-800 transition-colors">获取接口</a>
                <a href="#guide" class="inline-flex items-center h-10 px-5 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-800 hover:bg-zinc-50 transition-colors">使用指南</a>
            </div>
        </section>

        <!-- 数据总览 -->
        <section class="mx-auto max-w-6xl px-4 sm:px-6 pb-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-card transition hover:border-zinc-300 hover:shadow-pop">
                    <div class="w-9 h-9 rounded-lg bg-zinc-100 text-zinc-600 grid place-items-center mb-3" aria-hidden="true"><i class="fas fa-chart-line text-[14px]"></i></div>
                    <p class="text-[13px] text-zinc-500">总调用次数</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900"><?php echo number_format($stats['total']); ?></p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-card transition hover:border-zinc-300 hover:shadow-pop">
                    <div class="w-9 h-9 rounded-lg bg-brand-soft text-brand grid place-items-center mb-3" aria-hidden="true"><i class="fas fa-desktop text-[14px]"></i></div>
                    <p class="text-[13px] text-zinc-500">PC端图片数</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900">
                        <a href="pc.php" target="_blank" rel="noopener" class="hover:underline underline-offset-4 decoration-zinc-300"><?php echo getImageCount('pc'); ?></a>
                    </p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-card transition hover:border-zinc-300 hover:shadow-pop">
                    <div class="w-9 h-9 rounded-lg bg-violet-50 text-violet-600 grid place-items-center mb-3" aria-hidden="true"><i class="fas fa-mobile-alt text-[14px]"></i></div>
                    <p class="text-[13px] text-zinc-500">移动端图片数</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900">
                        <a href="pe.php" target="_blank" rel="noopener" class="hover:underline underline-offset-4 decoration-zinc-300"><?php echo getImageCount('pe'); ?></a>
                    </p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 sm:px-6 pb-14">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-card">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">调用趋势</h2>
                    <span class="rounded-full border border-zinc-200 px-2.5 py-1 text-xs text-zinc-500">最近 30 天</span>
                </div>
                <div class="h-72"><canvas id="callTrendChart"></canvas></div>
            </div>
        </section>

        <!-- API 接口 -->
        <section id="api" class="mx-auto max-w-6xl px-4 sm:px-6 pb-14">
            <div class="mb-4">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900">API 接口</h2>
                <p class="mt-1 text-sm text-zinc-500">复制链接即可接入，自动判断设备类型返回对应图片</p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-card transition hover:border-zinc-300 hover:shadow-pop">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-emerald-700">GET</span>
                        <h3 class="text-sm font-semibold text-zinc-900">基础接口</h3>
                    </div>
                    <div class="relative">
                        <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-50 pl-3 h-10">
                            <code class="font-mono text-[12.5px] text-zinc-800 truncate">https://<?php echo $safeHost; ?>/api.php</code>
                        </div>
                        <button class="copy-btn absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors" data-clipboard-text="https://<?php echo $safeHost; ?>/api.php" aria-label="复制基础接口地址">
                            <i class="fas fa-copy text-[10px]" aria-hidden="true"></i> 复制
                        </button>
                    </div>
                    <p class="mt-2.5 text-[13px] text-zinc-500 leading-relaxed">自动判断设备类型返回图片，默认不缓存</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-card transition hover:border-zinc-300 hover:shadow-pop">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-emerald-700">GET</span>
                        <h3 class="text-sm font-semibold text-zinc-900">PC端接口</h3>
                    </div>
                    <div class="relative">
                        <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-50 pl-3 h-10">
                            <code class="font-mono text-[12.5px] text-zinc-800 truncate">https://<?php echo $safeHost; ?>/pc.php</code>
                        </div>
                        <button class="copy-btn absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php" aria-label="复制PC端接口地址">
                            <i class="fas fa-copy text-[10px]" aria-hidden="true"></i> 复制
                        </button>
                    </div>
                    <p class="mt-2.5 text-[13px] text-zinc-500 leading-relaxed">固定返回 PC 比例图片 · 今日调用 <?php echo $stats['daily'][date('Y-m-d')]['pc'] ?? 0; ?> 次</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-card transition hover:border-zinc-300 hover:shadow-pop">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-emerald-700">GET</span>
                        <h3 class="text-sm font-semibold text-zinc-900">移动端接口</h3>
                    </div>
                    <div class="relative">
                        <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-50 pl-3 h-10">
                            <code class="font-mono text-[12.5px] text-zinc-800 truncate">https://<?php echo $safeHost; ?>/pe.php</code>
                        </div>
                        <button class="copy-btn absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors" data-clipboard-text="https://<?php echo $safeHost; ?>/pe.php" aria-label="复制移动端接口地址">
                            <i class="fas fa-copy text-[10px]" aria-hidden="true"></i> 复制
                        </button>
                    </div>
                    <p class="mt-2.5 text-[13px] text-zinc-500 leading-relaxed">固定返回移动端比例图片 · 今日调用 <?php echo $stats['daily'][date('Y-m-d')]['pe'] ?? 0; ?> 次</p>
                </div>
            </div>
        </section>

        <!-- 参数说明 -->
        <section id="guide" class="mx-auto max-w-6xl px-4 sm:px-6 pb-14">
            <div class="rounded-xl border border-zinc-200 bg-white shadow-card">
                <div class="px-5 pt-5">
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">参数说明</h2>
                </div>
                <div class="px-5 pb-5 mt-2 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[13px] text-zinc-500 border-b border-zinc-200">
                                <th class="py-2.5 pr-6 font-medium">参数</th>
                                <th class="py-2.5 pr-6 font-medium">可选值</th>
                                <th class="py-2.5 font-medium">说明</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-700">
                            <tr class="border-b border-zinc-100">
                                <td class="py-3 pr-6"><code class="rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-xs text-zinc-800">cache</code></td>
                                <td class="py-3 pr-6 text-zinc-600">数字（秒）</td>
                                <td class="py-3">缓存控制时间，默认 0 秒（不缓存）</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-6"><code class="rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-xs text-zinc-800">format</code></td>
                                <td class="py-3 pr-6"><code class="rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-xs text-zinc-800">json</code></td>
                                <td class="py-3">
                                    <?php if ($jsonEnabled): ?>
                                        返回图片地址 JSON（已开启）
                                    <?php else: ?>
                                        返回图片地址 JSON（当前未开启，需在后台「网站设置 → JSON 格式输出」开启）
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-3 text-[13px] text-zinc-500">图片访问模式由后台「网站设置 → 图片访问模式」统一控制，调用方传参不再影响返回方式。</p>
                </div>
            </div>
        </section>

        <!-- 调用示例 -->
        <section class="mx-auto max-w-6xl px-4 sm:px-6 pb-14">
            <div class="mb-4">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900">调用示例</h2>
            </div>
            <div class="space-y-2.5">
                <details class="acc rounded-xl border border-zinc-200 bg-white shadow-card overflow-hidden" open>
                    <summary class="flex cursor-pointer select-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-zinc-800">
                        直接显示图片
                        <i class="fas fa-chevron-down acc-icon text-xs text-zinc-400 transition-transform" aria-hidden="true"></i>
                    </summary>
                    <div class="px-4 pb-4 pt-3 border-t border-zinc-100">
                        <div class="relative mb-3">
                            <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-50 pl-3 h-10">
                                <code class="font-mono text-[12.5px] text-zinc-800 truncate">https://<?php echo $safeHost; ?>/pc.php</code>
                            </div>
                            <button class="copy-btn absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php" aria-label="复制接口地址">
                                <i class="fas fa-copy text-[10px]" aria-hidden="true"></i> 复制
                            </button>
                        </div>
                        <a href="pc.php" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 h-24 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 text-sm font-medium text-brand hover:bg-brand-soft hover:border-brand transition-colors">
                            <i class="fas fa-external-link-alt text-xs" aria-hidden="true"></i> 点击链接查看随机图片
                        </a>
                    </div>
                </details>

                <details class="acc rounded-xl border border-zinc-200 bg-white shadow-card overflow-hidden">
                    <summary class="flex cursor-pointer select-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-zinc-800">
                        图片访问模式
                        <i class="fas fa-chevron-down acc-icon text-xs text-zinc-400 transition-transform" aria-hidden="true"></i>
                    </summary>
                    <div class="px-4 pb-4 pt-3 border-t border-zinc-100 text-sm text-zinc-600 leading-relaxed">
                        <p>图片访问模式由后台「网站设置 → 图片访问模式」统一控制，对 api.php / pc.php / pe.php 全部生效：</p>
                        <ul class="mt-2 space-y-1.5 list-disc list-inside">
                            <li><strong class="text-zinc-900">302 跳转模式</strong>（默认）：API 直接重定向到真实图片 URL</li>
                            <li><strong class="text-zinc-900">代理模式</strong>：由服务器代为下载并转发图片，隐藏真实图片链接</li>
                        </ul>
                    </div>
                </details>

                <details class="acc rounded-xl border border-zinc-200 bg-white shadow-card overflow-hidden">
                    <summary class="flex cursor-pointer select-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-zinc-800">
                        HTML 调用示例
                        <i class="fas fa-chevron-down acc-icon text-xs text-zinc-400 transition-transform" aria-hidden="true"></i>
                    </summary>
                    <div class="px-4 pb-4 pt-3 border-t border-zinc-100">
                        <div class="relative mb-3">
                            <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-50 pl-3 h-10">
                                <code class="font-mono text-[12.5px] text-zinc-800 truncate">&lt;img src="https://<?php echo $safeHost; ?>/api.php" alt="随机图片"&gt;</code>
                            </div>
                            <button class="copy-btn absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors" data-clipboard-text="&lt;img src=&quot;https://<?php echo $safeHost; ?>/api.php&quot; alt=&quot;随机图片&quot;&gt;" aria-label="复制 HTML 调用示例">
                                <i class="fas fa-copy text-[10px]" aria-hidden="true"></i> 复制
                            </button>
                        </div>
                    </div>
                </details>

                <details class="acc rounded-xl border border-zinc-200 bg-white shadow-card overflow-hidden">
                    <summary class="flex cursor-pointer select-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-zinc-800">
                        启用缓存示例
                        <i class="fas fa-chevron-down acc-icon text-xs text-zinc-400 transition-transform" aria-hidden="true"></i>
                    </summary>
                    <div class="px-4 pb-4 pt-3 border-t border-zinc-100">
                        <div class="relative mb-3">
                            <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-50 pl-3 h-10">
                                <code class="font-mono text-[12.5px] text-zinc-800 truncate">https://<?php echo $safeHost; ?>/pc.php?cache=3600</code>
                            </div>
                            <button class="copy-btn absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php?cache=3600" aria-label="复制缓存示例地址">
                                <i class="fas fa-copy text-[10px]" aria-hidden="true"></i> 复制
                            </button>
                        </div>
                        <p class="text-[13px] text-zinc-500">缓存 3600 秒（1 小时），1 小时内重复调用将返回相同图片</p>
                    </div>
                </details>

                <details class="acc rounded-xl border border-zinc-200 bg-white shadow-card overflow-hidden">
                    <summary class="flex cursor-pointer select-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-zinc-800">
                        JSON 格式调用示例
                        <i class="fas fa-chevron-down acc-icon text-xs text-zinc-400 transition-transform" aria-hidden="true"></i>
                    </summary>
                    <div class="px-4 pb-4 pt-3 border-t border-zinc-100 text-sm text-zinc-600 leading-relaxed">
                        <?php if ($jsonEnabled): ?>
                            <div class="relative mb-3">
                                <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-50 pl-3 h-10">
                                    <code class="font-mono text-[12.5px] text-zinc-800 truncate">https://<?php echo $safeHost; ?>/pc.php?format=json</code>
                                </div>
                                <button class="copy-btn absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php?format=json" aria-label="复制 JSON 示例地址">
                                    <i class="fas fa-copy text-[10px]" aria-hidden="true"></i> 复制
                                </button>
                            </div>
                            <p>返回示例（渠道切换后实际内容可能不同）：</p>
                            <pre class="mt-2 rounded-lg bg-zinc-900 p-4 font-mono text-[12.5px] leading-relaxed text-zinc-100 overflow-x-auto">{
    "success": true,
    "type": "pc",
    "mode": "<?php echo htmlspecialchars($imageMode, ENT_QUOTES); ?>",
    "cache": 0,
    "url": "https://.../image.webp"
}</pre>
                            <?php if ($imageMode === 'proxy'): ?>
                            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[13px] text-amber-800"><i class="fas fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>当前为代理模式，JSON 会返回真实图片 URL，等于对外暴露了图片链接，请确认按需使用。</p>
                            <?php else: ?>
                            <p class="mt-3 text-[13px] text-zinc-500">api.php / pc.php / pe.php 均支持 <code class="rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 font-mono text-xs text-zinc-800">?format=json</code>。</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>JSON 格式输出当前<strong class="text-zinc-900">未开启</strong>。如需使用，请在后台「网站设置 → JSON 格式输出」开启。</p>
                            <div class="relative mt-3 mb-1">
                                <div class="flex items-center rounded-lg border border-zinc-200 bg-zinc-50 pl-3 h-10">
                                    <code class="font-mono text-[12.5px] text-zinc-800 truncate">https://<?php echo $safeHost; ?>/pc.php?format=json</code>
                                </div>
                                <button class="copy-btn absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-900 hover:border-zinc-900 hover:text-white transition-colors" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php?format=json" aria-label="复制 JSON 示例地址">
                                    <i class="fas fa-copy text-[10px]" aria-hidden="true"></i> 复制
                                </button>
                            </div>
                            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[13px] text-amber-800"><i class="fas fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>开启后接口才返回 JSON；当前设置需在后台开启。</p>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </section>

        <!-- 图片访问模式 -->
        <section class="mx-auto max-w-6xl px-4 sm:px-6 pb-16">
            <div class="mb-4">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900">图片访问模式</h2>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-card">
                    <div class="flex items-start gap-3.5">
                        <span class="w-10 h-10 shrink-0 rounded-lg border border-zinc-200 bg-white text-brand grid place-items-center" aria-hidden="true"><i class="fas fa-link text-sm"></i></span>
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900">302 跳转模式（默认）</h3>
                            <p class="mt-1 text-[13px] text-zinc-500 leading-relaxed">直接重定向到随机图片 URL，适用于大多数场景</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-card">
                    <div class="flex items-start gap-3.5">
                        <span class="w-10 h-10 shrink-0 rounded-lg border border-zinc-200 bg-white text-violet-600 grid place-items-center" aria-hidden="true"><i class="fas fa-image text-sm"></i></span>
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900">代理模式</h3>
                            <p class="mt-1 text-[13px] text-zinc-500 leading-relaxed">由服务器代为下载并转发图片，隐藏真实图片链接</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 py-5 flex flex-wrap items-center justify-center sm:justify-between gap-3 text-sm text-zinc-500">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($site['site_copyright'], ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($site['site_icp'])): ?>
                · <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener" class="hover:text-zinc-900 transition-colors"><?php echo htmlspecialchars($site['site_icp'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endif; ?>
            </p>
            <a href="<?php echo htmlspecialchars($repoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 px-3 py-1 font-mono text-xs text-zinc-600 hover:border-zinc-400 hover:text-zinc-900 transition-colors">
                <i class="fab fa-github" aria-hidden="true"></i> v<?php echo htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
    </footer>

    <script>
        // 把首页统计注入到 window.STATS，供外置 public/js/home.js 使用
        window.STATS = <?php echo json_encode($stats); ?>;
    </script>
    <script src="public/js/home.js?v=<?php echo APP_VERSION; ?>"></script>
</body>
</html>

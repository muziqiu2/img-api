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
    
    <!-- 本地资源 -->
    <link href="public/css/bootstrap.min.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
    <link href="public/css/all.min.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
    <script src="public/js/jquery.min.js?v=<?php echo APP_VERSION; ?>"></script>
    <script src="public/js/chart.umd.min.js?v=<?php echo APP_VERSION; ?>"></script>
    <script src="public/js/clipboard.min.js?v=<?php echo APP_VERSION; ?>"></script>
    
    <link href="public/css/home.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
</head>
<body>
    <div class="toast-notification" id="copyToast">
        <i class="fas fa-check"></i>
        <span>复制成功！</span>
    </div>

    <header class="header text-center">
        <div class="container">
            <h1 class="site-name"><i class="fas fa-magic"></i><?php echo htmlspecialchars($site['site_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="site-lead"><?php echo htmlspecialchars($site['site_lead'], ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="admin/" class="admin-link">
                <i class="fas fa-cog"></i> 管理后台
            </a>
        </div>
    </header>

    <main class="container mb-5">
        <div class="row mb-5">
            <div class="col-md-4 mb-3">
                <div class="stat-card shadow-sm">
                    <p class="stat-label">总调用次数</p>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card shadow-sm">
                    <p class="stat-label">PC端图片数</p>
                    <div class="stat-value"><a href="pc.php" target="_blank" rel="noopener"><?php echo getImageCount('pc'); ?></a></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card shadow-sm">
                    <p class="stat-label">移动端图片数</p>
                    <div class="stat-value"><a href="pe.php" target="_blank" rel="noopener"><?php echo getImageCount('pe'); ?></a></div>
                </div>
            </div>
        </div>

        <div class="paper-card">
            <h3 class="section-title"><i class="fas fa-chart-line me-2"></i>调用趋势</h3>
            <div style="height: 300px;">
                <canvas id="callTrendChart"></canvas>
            </div>
        </div>

        <div class="paper-card">
            <h3 class="section-title"><i class="fas fa-book-open me-2"></i>API使用指南</h3>
            
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <h5>基础接口</h5>
                    <div class="api-link-container">
                        <div class="api-link">
                            <code>https://<?php echo $safeHost; ?>/api.php</code>
                        </div>
                        <button class="copy-btn" data-clipboard-text="https://<?php echo $safeHost; ?>/api.php">
                            <i class="fas fa-copy"></i> 复制
                        </button>
                    </div>
                    <p class="text-muted small mt-1">自动判断设备类型返回图片，默认不缓存</p>
                </div>
                <div class="col-md-6 mb-3">
                    <h5>PC端接口</h5>
                    <div class="api-link-container">
                        <div class="api-link">
                            <code>https://<?php echo $safeHost; ?>/pc.php</code>
                        </div>
                        <button class="copy-btn" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php">
                            <i class="fas fa-copy"></i> 复制
                        </button>
                    </div>
                    <p class="text-muted small mt-1">今日调用: <?php echo $stats['daily'][date('Y-m-d')]['pc'] ?? 0; ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <h5>移动端接口</h5>
                    <div class="api-link-container">
                        <div class="api-link">
                            <code>https://<?php echo $safeHost; ?>/pe.php</code>
                        </div>
                        <button class="copy-btn" data-clipboard-text="https://<?php echo $safeHost; ?>/pe.php">
                            <i class="fas fa-copy"></i> 复制
                        </button>
                    </div>
                    <p class="text-muted small mt-1">今日调用: <?php echo $stats['daily'][date('Y-m-d')]['pe'] ?? 0; ?></p>
                </div>
            </div>

            <div class="mb-4">
                <h5><i class="fas fa-code me-2"></i>参数说明</h5>
                <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>参数</th>
                            <th>可选值</th>
                            <th>说明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>cache</td>
                            <td>数字(秒)</td>
                            <td>缓存控制时间，默认0秒（不缓存）</td>
                        </tr>
                        <tr>
                            <td>format</td>
                            <td>json</td>
                            <td>
                                <?php if ($jsonEnabled): ?>
                                    返回图片地址 JSON（可选，需在后台开启）
                                <?php else: ?>
                                    返回图片地址 JSON（当前未开启，需在后台「网站设置 → JSON 格式输出」开启）
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <p class="text-muted small mb-0">图片访问模式由后台「网站设置 → 图片访问模式」统一控制，调用方传参不再影响返回方式。</p>
            </div>

            <div class="mb-4">
                <h5><i class="fas fa-terminal me-2"></i>调用示例</h5>
                <div class="accordion" id="examplesAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading1">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                直接显示图片
                            </button>
                        </h2>
                        <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#examplesAccordion">
                            <div class="accordion-body">
                                <div class="api-link-container mb-2">
                                    <div class="api-link">
                                        <code>https://<?php echo $safeHost; ?>/pc.php</code>
                                    </div>
                                    <button class="copy-btn" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php">
                                        <i class="fas fa-copy"></i> 复制
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <div class="example-container">
                                        <a href="pc.php" target="_blank" class="example-link">
                                            <i class="fas fa-external-link-alt"></i>
                                            点击链接查看随机图片
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                图片访问模式
                            </button>
                        </h2>
                        <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#examplesAccordion">
                            <div class="accordion-body">
                                <p class="text-muted">图片访问模式由后台「网站设置 → 图片访问模式」统一控制，对 api.php / pc.php / pe.php 全部生效：</p>
                                <ul class="mb-0">
                                    <li><strong>302 跳转模式</strong>（默认）：API 直接重定向到真实图片 URL</li>
                                    <li><strong>代理模式</strong>：由服务器代为下载并转发图片，隐藏真实图片链接</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading3">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                HTML调用示例
                            </button>
                        </h2>
                        <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#examplesAccordion">
                            <div class="accordion-body">
                                <div class="api-link-container mb-2">
                                    <div class="api-link">
                                        <code>&lt;img src="https://<?php echo $safeHost; ?>/api.php" alt="随机图片"&gt;</code>
                                    </div>
                                    <button class="copy-btn" data-clipboard-text="&lt;img src=&quot;https://<?php echo $safeHost; ?>/api.php&quot; alt=&quot;随机图片&quot;&gt;">
                                        <i class="fas fa-copy"></i> 复制
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading4">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                启用缓存示例
                            </button>
                        </h2>
                        <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#examplesAccordion">
                            <div class="accordion-body">
                                <div class="api-link-container mb-2">
                                    <div class="api-link">
                                        <code>https://<?php echo $safeHost; ?>/pc.php?cache=3600</code>
                                    </div>
                                    <button class="copy-btn" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php?cache=3600">
                                        <i class="fas fa-copy"></i> 复制
                                    </button>
                                </div>
                                <p class="text-muted small">缓存3600秒（1小时），1小时内重复调用将返回相同图片</p>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading5">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                                JSON 格式调用示例
                            </button>
                        </h2>
                        <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#examplesAccordion">
                            <div class="accordion-body">
                                <?php if ($jsonEnabled): ?>
                                    <div class="api-link-container mb-2">
                                        <div class="api-link">
                                            <code>https://<?php echo $safeHost; ?>/pc.php?format=json</code>
                                        </div>
                                        <button class="copy-btn" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php?format=json">
                                            <i class="fas fa-copy"></i> 复制
                                        </button>
                                    </div>
                                    <p>返回示例（渠道切换后实际内容可能不同）：</p>
                                    <pre class="bg-light p-3 rounded" style="white-space:pre-wrap;">{
    "success": true,
    "type": "pc",
    "mode": "<?php echo htmlspecialchars($imageMode); ?>",
    "cache": 0,
    "url": "https://.../image.webp"
}</pre>
                                    <?php if ($imageMode === 'proxy'): ?>
                                    <p class="text-warning small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>当前为代理模式，JSON 会返回真实图片 URL，等于对外暴露了图片链接，请确认按需使用。</p>
                                    <?php else: ?>
                                    <p class="text-muted small mb-0">api.php / pc.php / pe.php 均支持 <code>?format=json</code>。</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-2">JSON 格式输出当前<strong>未开启</strong>。如需使用，请在后台「网站设置 → JSON 格式输出」开启。</p>
                                    <div class="api-link-container mb-2">
                                        <div class="api-link">
                                            <code>https://<?php echo $safeHost; ?>/pc.php?format=json</code>
                                        </div>
                                        <button class="copy-btn" data-clipboard-text="https://<?php echo $safeHost; ?>/pc.php?format=json">
                                            <i class="fas fa-copy"></i> 复制
                                        </button>
                                    </div>
                                    <p class="text-warning small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>开启后接口才返回 JSON；当前设置重启后需在后台开启。</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="paper-card">
            <h3 class="section-title"><i class="fas fa-exchange-alt me-2"></i>图片访问模式</h3>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="mode-card">
                        <h5><i class="fas fa-link icon-brand"></i> 302 跳转模式（默认）</h5>
                        <p class="text-sm">直接重定向到随机图片URL，适用于大多数场景</p>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="mode-card">
                        <h5><i class="fas fa-image icon-brand"></i> 代理模式</h5>
                        <p class="text-sm">由服务器代为下载并转发图片，隐藏真实图片链接</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container text-center">
            <p class="mb-0 text-muted">
                © <?php echo date('Y'); ?> <?php echo htmlspecialchars($site['site_copyright'], ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($site['site_icp'])): ?>
                | <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener" class="text-muted"><?php echo htmlspecialchars($site['site_icp'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endif; ?>
                | <a href="<?php echo htmlspecialchars($repoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="text-muted" style="white-space:nowrap;">v<?php echo htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8'); ?></a>
            </p>
        </div>
    </footer>

    <script src="public/js/bootstrap.bundle.min.js?v=<?php echo APP_VERSION; ?>"></script>
    <script>
        // 把首页统计注入到 window.STATS，供外置 public/js/home.js 使用
        window.STATS = <?php echo json_encode($stats); ?>;
    </script>
    <script src="public/js/home.js?v=<?php echo APP_VERSION; ?>"></script>
</body>
</html>

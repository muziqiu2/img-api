<?php
require_once 'config.php';

// 安全主机名（去除危险字符，防止Host头注入攻击）
$safeHost = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^a-zA-Z0-9\.\-:]/', '', $_SERVER['HTTP_HOST']) : 'example.com';
$safeHost = htmlspecialchars($safeHost, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>魔法师随机图片API</title>
    <meta name="keywords" content="随机图片,二次元API,动漫壁纸,图片接口">
    <meta name="description" content="提供高质量随机二次元图片API服务，支持PC/移动端自适应">
    
    <!-- 本地资源 -->
    <link href="/public/css/bootstrap.min.css" rel="stylesheet">
    <link href="/public/css/all.min.css" rel="stylesheet">
    <script src="/public/js/jquery.min.js"></script>
    <script src="/public/js/chart.umd.min.js"></script>
    <script src="/public/js/clipboard.min.js"></script>
    
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #8b5cf6;
            --dark: #1e293b;
            --light: #f8fafc;
        }
        
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .header .container {
            position: relative;
            z-index: 1;
        }
        
        .admin-link {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 2;
        }
        
        @media (max-width: 768px) {
            .admin-link {
                position: static;
                display: inline-block;
                margin-top: 1rem;
            }
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .api-link-container {
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .api-link {
            padding: 0.75rem 1rem;
            background-color: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }
        
        .api-link code {
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-right: 60px;
        }
        
        .copy-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 10px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
            z-index: 1;
        }
        
        .copy-btn:hover {
            background-color: var(--secondary);
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: scale(1.03);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        .section-title {
            position: relative;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .section-title::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary);
            border-radius: 3px;
        }
        
        footer {
            margin-top: auto;
            padding: 1.5rem 0;
            border-top: 1px solid #e2e8f0;
            margin-top: 3rem;
        }
        
        .example-container {
            width: 100%;
            height: 200px;
            border-radius: 8px;
            margin-bottom: 1rem;
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed #cbd5e1;
            transition: all 0.3s ease;
        }
        
        .example-container:hover {
            background-color: #e2e8f0;
        }
        
        .example-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .example-link i {
            margin-right: 0.5rem;
        }
        
        .example-link:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
        
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 1000;
            pointer-events: none;
        }
        
        .toast-notification i {
            margin-right: 8px;
        }
        
        .toast-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 2rem 0;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="toast-notification" id="copyToast">
        <i class="fas fa-check"></i>
        <span>复制成功！</span>
    </div>

    <header class="header text-center">
        <div class="container">
            <h1 class="display-4 mb-3"><i class="fas fa-magic me-2"></i>魔法师随机图片API</h1>
            <p class="lead">免费提供高质量随机二次元图片API服务</p>
            <a href="admin/" class="admin-link btn btn-light btn-sm">
                <i class="fas fa-cog"></i> 管理后台
            </a>
        </div>
    </header>

    <main class="container mb-5">
        <div class="row mb-5">
            <div class="col-md-4 mb-3">
                <div class="stat-card shadow-sm">
                    <p class="stat-label">总调用次数</p>
                    <div class="stat-value"><?php echo number_format(getTotalCalls()); ?></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card shadow-sm">
                    <p class="stat-label">PC端图片数</p>
                    <div class="stat-value"><?php echo getImageCount('pc'); ?></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card shadow-sm">
                    <p class="stat-label">移动端图片数</p>
                    <div class="stat-value"><?php echo getImageCount('pe'); ?></div>
                </div>
            </div>
        </div>

        <div class="card p-4 mb-5">
            <h3 class="section-title"><i class="fas fa-chart-line me-2"></i>调用趋势</h3>
            <div style="height: 300px;">
                <canvas id="callTrendChart"></canvas>
            </div>
        </div>

        <div class="card p-4 mb-5">
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
                    <p class="text-muted small mt-1">今日调用: <?php echo getCallCount()['daily'][date('Y-m-d')]['pc'] ?? 0; ?></p>
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
                    <p class="text-muted small mt-1">今日调用: <?php echo getCallCount()['daily'][date('Y-m-d')]['pe'] ?? 0; ?></p>
                </div>
            </div>

            <div class="mb-4">
                <h5><i class="fas fa-code me-2"></i>参数说明</h5>
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
                            <td>return</td>
                            <td>json/img</td>
                            <td>返回格式（默认重定向图片）</td>
                        </tr>
                        <tr>
                            <td>cache</td>
                            <td>数字(秒)</td>
                            <td>缓存控制时间，默认0秒（不缓存）</td>
                        </tr>
                    </tbody>
                </table>
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
                                返回JSON数据
                            </button>
                        </h2>
                        <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#examplesAccordion">
                            <div class="accordion-body">
                                <div class="api-link-container mb-2">
                                    <div class="api-link">
                                        <code>https://<?php echo $safeHost; ?>/pe.php?return=json</code>
                                    </div>
                                    <button class="copy-btn" data-clipboard-text="https://<?php echo $safeHost; ?>/pe.php?return=json">
                                        <i class="fas fa-copy"></i> 复制
                                    </button>
                                </div>
                                <pre class="bg-light p-3 rounded mt-2"><code>{
  "success": true,
  "url": "https://example.com/image.jpg",
  "type": "pe",
  "timestamp": 1622505600
}</code></pre>
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
                </div>
            </div>
        </div>

        <div class="card p-4 mb-5">
            <h3 class="section-title"><i class="fas fa-exchange-alt me-2"></i>返回格式说明</h3>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded h-100">
                        <h5><i class="fas fa-link text-primary me-1"></i> 默认(重定向)</h5>
                        <p class="text-sm">直接重定向到随机图片URL，适用于大多数场景</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded h-100">
                        <h5><i class="fas fa-file-code text-success me-1"></i> JSON格式</h5>
                        <p class="text-sm">返回包含图片信息的JSON数据，适合需要处理图片信息的场景</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded h-100">
                        <h5><i class="fas fa-image text-info me-1"></i> 图片流</h5>
                        <p class="text-sm">直接输出图片二进制数据，适合需要隐藏真实图片URL的场景</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container text-center">
            <p class="mb-0 text-muted">© <?php echo date('Y'); ?> 魔法师随机图片API | 免费二次元图片接口服务</p>
        </div>
    </footer>

    <script src="/public/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化复制功能
            const clipboard = new ClipboardJS('.copy-btn');
            
            clipboard.on('success', function(e) {
                e.clearSelection();
                showToast();
            });
            
            clipboard.on('error', function(e) {
                console.error('复制失败:', e.action);
            });

            // 调用趋势图表
            const countData = <?php echo json_encode(getCallCount()); ?>;
            const dailyData = countData.daily || {};
            
            // 获取最近30天的日期
            const today = new Date();
            const last30Days = [];
            for (let i = 29; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - i);
                last30Days.push(date.toISOString().split('T')[0]);
            }
            
            // 筛选最近30天的数据，如果数据不足30天则显示全部可用数据
            const allDates = Object.keys(dailyData).sort();
            let filteredDates;
            
            if (allDates.length > 30) {
                // 数据超过30天，只取最近30天
                filteredDates = last30Days.filter(date => dailyData[date]);
                // 如果筛选后数据不足，则取实际存在的最近30天数据
                if (filteredDates.length < 30 && allDates.length >= 30) {
                    filteredDates = allDates.slice(-30);
                }
            } else {
                // 数据不足30天，显示全部
                filteredDates = allDates;
            }
            
            const totalCalls = filteredDates.map(date => dailyData[date]?.total || 0);
            const pcCalls = filteredDates.map(date => dailyData[date]?.pc || 0);
            const peCalls = filteredDates.map(date => dailyData[date]?.pe || 0);
            
            // 格式化日期标签（只显示月-日）
            const formattedLabels = filteredDates.map(date => {
                const d = new Date(date);
                return (d.getMonth() + 1) + '/' + d.getDate();
            });
            
            // 使用正确的Chart对象创建图表
            const ctx = document.getElementById('callTrendChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: formattedLabels,
                    datasets: [
                        {
                            label: '总调用',
                            data: totalCalls,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: filteredDates.length > 15 ? 2 : 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'PC端',
                            data: pcCalls,
                            borderColor: '#3b82f6',
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            pointRadius: filteredDates.length > 15 ? 2 : 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: '移动端',
                            data: peCalls,
                            borderColor: '#8b5cf6',
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            pointRadius: filteredDates.length > 15 ? 2 : 4,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: function(tooltipItems) {
                                    // 显示完整日期
                                    const index = tooltipItems[0].dataIndex;
                                    return filteredDates[index];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '调用次数'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: '日期'
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 15
                            }
                        }
                    }
                }
            });
        });

        function showToast() {
            const toast = document.getElementById('copyToast');
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2000);
        }
    </script>
</body>
</html>

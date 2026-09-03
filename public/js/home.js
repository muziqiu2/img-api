// 按本地时区解析 'YYYY-MM-DD'：避免 new Date('YYYY-MM-DD') 按 UTC 午夜解析，
// 在 UTC-x 时区（美洲/欧洲）下用 getMonth()/getDate() 取月日会偏移一天
function parseLocalDate(ymd) {
    const parts = String(ymd).split('-').map(Number);
    return new Date(parts[0], (parts[1] || 1) - 1, parts[2] || 1);
}

// 复制功能与图表各自独立初始化：任一依赖（ClipboardJS/Chart）加载失败不拖累另一功能
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ClipboardJS === 'undefined') {
        console.error('clipboard.min.js 未加载，复制按钮不可用');
        return;
    }
    try {
        const clipboard = new ClipboardJS('.copy-btn');
        clipboard.on('success', function(e) {
            e.clearSelection();
            showToast();
        });
        clipboard.on('error', function(e) {
            console.error('复制失败:', e.action);
        });
    } catch (e) {
        console.error('Clipboard 初始化失败:', e);
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // 调用趋势图表
    const canvas = document.getElementById('callTrendChart');
    if (!canvas || typeof Chart === 'undefined') return;
    // stats 数据由 index.php 以 window.STATS 注入
    const countData = window.STATS || { daily: {} };
    const dailyData = countData.daily || {};

    // 获取最近30天的日期（后端按 Asia/Shanghai 记录，此处按访问者本地时区求值，
    // 仅用于选取窗口，个别时区差异最多影响边界 1 天，不影响图表正确性）
    const today = new Date();
    const last30Days = [];
    for (let i = 29; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        // 使用本地时区格式化日期，与后端 date('Y-m-d') 保持一致
        last30Days.push(date.getFullYear() + '-' +
            String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0'));
    }

    // 窗口选取逻辑：
    // 1) 最近 30 个自然日内的有数据日期优先（尊重数据空缺，不跨越到更早的日期）；
    // 2) 若近 30 天完全无数据（站点曾长期停更），退而展示最近的有数据的 30 天；
    // 3) 两者皆无则空图表。
    const allDates = Object.keys(dailyData).sort();
    const cutoff = last30Days[0]; // 窗口最早一天（29 天前）
    let filteredDates = allDates.filter(date => date >= cutoff);
    if (filteredDates.length === 0 && allDates.length > 0) {
        filteredDates = allDates.slice(-30);
    }

    const totalCalls = filteredDates.map(date => dailyData[date]?.total || 0);
    const pcCalls = filteredDates.map(date => dailyData[date]?.pc || 0);
    const peCalls = filteredDates.map(date => dailyData[date]?.pe || 0);

    // 格式化日期标签（只显示月-日）
    const formattedLabels = filteredDates.map(date => {
        const d = parseLocalDate(date);
        return (d.getMonth() + 1) + '/' + d.getDate();
    });

    // 使用正确的Chart对象创建图表
    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: formattedLabels,
            datasets: [
                {
                    label: '总调用',
                    data: totalCalls,
                    borderColor: '#2f6fed',
                    backgroundColor: 'rgba(47, 111, 237, 0.10)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: filteredDates.length > 15 ? 2 : 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'PC端',
                    data: pcCalls,
                    borderColor: '#1f2733',
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    pointRadius: filteredDates.length > 15 ? 2 : 4,
                    pointHoverRadius: 6
                },
                {
                    label: '移动端',
                    data: peCalls,
                    borderColor: '#8aa0ba',
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
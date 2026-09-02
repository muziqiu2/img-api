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
    // stats 数据由 index.php 以 window.STATS 注入
    const countData = window.STATS || { daily: {} };
    const dailyData = countData.daily || {};

    // 获取最近30天的日期
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
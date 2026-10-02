// 网站运行时间统计功能
function show_date_time() {
    const BirthDay = new Date("02/04/2023 00:00:00");
    const today = new Date();
    const timeold = today - BirthDay;
    const daysold = Math.floor(timeold / (24 * 60 * 60 * 1000));
    const hrsold = Math.floor((timeold % (24 * 60 * 60 * 1000)) / (60 * 60 * 1000));
    const minsold = Math.floor((timeold % (60 * 60 * 1000)) / (60 * 1000));
    const seconds = Math.floor((timeold % (60 * 1000)) / 1000);
    const spanElement = document.getElementById("span_dt_dt");
    if (spanElement) {
        spanElement.innerHTML = `<font style="color:#C40000">${daysold}</font> 天 <font style="color:#C40000">${hrsold}</font> 时 <font style="color:#C40000">${minsold}</font> 分 <font style="color:#C40000">${seconds}</font> 秒`;
    }
    setTimeout(show_date_time, 1000);
}

// 页面加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    show_date_time();
});
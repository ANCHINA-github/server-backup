document.addEventListener('DOMContentLoaded', function() {
    // 获取核心元素
    const mask = document.getElementById('notifyMask');
    const container = document.getElementById('notifyContainer');
    const closeBtn = document.getElementById('notifyClose');
    const countdown = document.getElementById('notifyCountdown');
    
    // 倒计时配置
    let remainingTime = 5;
    let countdownTimer = null;

    // 关闭通知的核心方法
    const closeNotify = function() {
        container.classList.remove('show');
        mask.style.display = 'none';
        clearInterval(countdownTimer); // 清除倒计时
        setTimeout(() => container.style.display = 'none', 500);
    };

    // 显示通知的核心方法
    const showNotify = function() {
        mask.style.display = 'block';
        container.style.display = 'block';
        setTimeout(() => container.classList.add('show'), 10); // 触发滑入动画
        
        // 启动倒计时
        countdownTimer = setInterval(() => {
            remainingTime--;
            countdown.textContent = `${remainingTime}秒后自动关闭`;
            remainingTime <= 0 && closeNotify();
        }, 1000);
    };

    // 页面加载完成后显示通知
    showNotify();

    // 绑定关闭事件
    closeBtn.addEventListener('click', closeNotify);
    mask.addEventListener('click', closeNotify);
    container.addEventListener('click', (e) => e.stopPropagation()); // 阻止内容区触发蒙层关闭

    // 窗口resize适配
    window.addEventListener('resize', () => {
        if (container.classList.contains('show')) {
            container.style.transform = 'translate(-50%, 0)';
        }
    });
});
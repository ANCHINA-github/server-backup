<?php
// ==================== PHP下载处理逻辑 ====================
// 检测是否触发下载请求（android版）
if (isset($_GET['download']) && $_GET['download'] === 'android') {
    // ******** 关键配置：APK文件实际路径 ********
    $apk_file = './downloads/shizhong.apk'; // 将APK放在downloads文件夹
    $apk_name = '十中信息墙圣诞版.apk'; // 下载时显示的文件名
// 开始下载处理
    // 检查文件是否存在
    if (!file_exists($apk_file)) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<script>alert("下载文件不存在！");window.history.back();</script>';
        exit;
    }

    // 设置强制下载的HTTP头
    header('Content-Type: application/vnd.android.package-archive'); // APK文件MIME类型
    header('Content-Disposition: attachment; filename="'.$apk_name.'"'); // 强制下载并指定文件名
    header('Content-Length: ' . filesize($apk_file)); // 文件大小
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // 输出文件内容（两种方式选其一即可）
    readfile($apk_file); // 简单方式：直接输出文件
    // 或分段输出（适合大文件）：
    // $fp = fopen($apk_file, 'rb');
    // while (!feof($fp)) {
    //     echo fread($fp, 1024 * 8);
    //     flush();
    // }
    // fclose($fp);
    
    exit; // 终止脚本，避免输出后续HTML
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="././home.svg">
    <title>武冈信息墙-下载和介绍</title>
     <!--
  #####    #####   #######
 #     #  #     #  #      
       #  #     #  #      
   ####   #     #   ##### 
       #  #     #        #
 #     #  #     #  #     #
  #####    #####    ##### 
                           -->


    <style>
        /* 全局样式重置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", "Heiti SC", sans-serif;
        }

        .indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.6);
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .indicator.active {
            background-color: #007bff;
        }

        /* 导航栏样式 (section1) - 修复布局问题 */
        .section1 {
            background-color: #c70000ff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
            /* 防止窄屏时换行后布局混乱 */
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        /* 导航链接容器 */
        .nav-links {
            display: flex;
            gap: clamp(1rem, 2vw, 2rem); /* 自适应间距 */
        }

        /* 导航链接样式 */
        .nav-links a {
            text-decoration: none;
            color: gold;
            font-size: clamp(0.9rem, 1.5vw, 1rem); /* 自适应字体 */
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #ffffffff;
        }

        /* LOGO样式 */
        .logo {
            font-size: clamp(1rem, 1.5vw, 1.2rem);
            font-weight: bold;
            color: #007bff;
            /* 保证LOGO始终在右侧 */
            flex-shrink: 0;
        }

        .logo img {
            max-height: clamp(30px, 5vw, 40px); /* 自适应LOGO高度 */
            width: auto;
        }

        /* 下载按钮区样式 (section2) */
        .section2 {
            padding: 3rem 2rem;
            background-color: #c70000ff;
            display: flex;
            justify-content: center;
            gap: clamp(1.5rem, 3vw, 2rem);
            /* 关键：相对定位，让伪元素基于此容器 */
             position: relative;
           /* 文字层级高于伪元素，避免被遮挡 */
         z-index: 1;
        }
        /* 新增伪元素：承载背景图 + 模糊 */
         .section2::before {
             content: "";
            position: absolute;
           top: 0;
            left: 0;
             width: 100%;
              height: 100%;
              /* 背景图设置 */
            background-image: url('./index-download-img.jpg');
             background-position: center center;
             background-size: cover;
              /* 核心：背景模糊（5px） */
            filter: blur(5px);
               /* 伪元素层级低于文字 */
                z-index: -1;
                 /* 可选：裁剪溢出的模糊边缘 */
                   overflow: hidden;
            }

        /* 下载按钮样式 */
        .download-btn {
            padding: clamp(0.8rem, 2vw, 1rem) clamp(1.5rem, 3vw, 2rem);
            background-color: gold;
            color: red;
            border: none;
            border-radius: 8px;
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            cursor: pointer;
            transition: all 0.3s;
        }

        /* 禁用状态的按钮样式 */
        .download-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed; /* 禁用状态光标样式 */
        }
        /* 响应式适配 - 全设备兼容 */
        @media (max-width: 768px) {
            /* 导航栏：保持横向布局，缩小内边距 */
            .section1 {
                padding: 0.8rem 1rem;
                justify-content: space-between;
                align-items: center;
            }

            /* 轮播按钮适配平板/手机 */
            .carousel-btn {
                width: 35px;
                height: 35px;
                font-size: 16px;
            }

            .prev-btn {
                left: 10px;
            }

            .next-btn {
                right: 10px;
            }

            .indicator {
                width: 8px;
                height: 8px;
            }

            /* 下载区适配 */
            .section2 {
                flex-direction: column;
                align-items: center;
                padding: 2rem 1rem;
            }

            .download-btn {
                width: clamp(200px, 80%, 300px);
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            /* 手机端极致适配 */
            .section1 {
                padding: 0.6rem 0.8rem;
            }

            .nav-links {
                gap: 0.8rem;
            }

            .carousel-btn {
                width: 30px;
                height: 30px;
                font-size: 14px;
            }

            .carousel-indicators {
                bottom: 10px;
                gap: 8px;
            }

            .indicator {
                width: 7px;
                height: 7px;
            }
        }

        /* 浏览器兼容处理 */
        @supports (-webkit-touch-callout: none) {
            /* iOS设备适配 */
            .carousel-slide img {
                -webkit-object-fit: cover;
                object-fit: cover;
            }
        }

        /* 防止IE浏览器兼容问题 */
        @media all and (-ms-high-contrast: none), (-ms-high-contrast: active) {
            .carousel-slide img {
                width: 100%;
                height: auto;
            }
        }

        /* =================================== 雪花框架CSS =================================== */
        /* 雪花容器：全局唯一，不影响页面布局 */
        .snow-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none; /* 穿透鼠标事件，不影响所有交互 */
            z-index: 99; /* 低于应急通知（一般弹窗z-index≥1000），高于页面普通内容 */
            overflow: hidden; /* 隐藏超出视口的雪花 */
        }

        /* 雪花基础样式：小巧六角形、白色半透明 */
        .snowflake {
            position: absolute;    
            /* 六角形clip-path */
            clip-path: polygon(
                50% 0%, 
                61% 35%, 
                98% 35%, 
                68% 57%, 
                79% 91%, 
                50% 70%, 
                21% 91%, 
                32% 57%, 
                2% 35%, 
                39% 35%
            );
            background-color: rgba(255, 255, 255, 0.95); /* 半透明白色，与红色背景对比明显 */
            opacity: 0.7;
            user-select: none; /* 禁止选中，避免干扰 */
            transform: translateZ(0); /* 开启硬件加速，提升性能 */
        }

        /* 雪花下落动画：无序摆动+匀速下落 */
        @keyframes snowfall {
            0% {
                transform: translateY(0) translateX(0) rotate(0deg);
                opacity: 0.7;
            }
            100% {
                transform: translateY(100vh) translateX(calc(var(--offset, 0) * 1vw)) rotate(360deg);
                opacity: 0;
            }
        }
        /* =================================== 雪花框架CSS结束 =================================== */
    </style>
</head>
<body>

<!-- =================================== 新增：雪花容器 =================================== -->
<div class="snow-container" id="snowContainer"></div>
<!-- =================================== 雪花容器结束 =================================== -->

    <!-- 导航区 (section1) -->
    <section class="section1">
        <div class="nav-links">
            <a href="#">首页</a>
            <a href="./suggestions.html" target="_blank">积极建议墙</a>
            <a href="./update-data.html" target="_blank">更新日志</a>
            <a href="./feedback/feedback.php" >反馈</a>
        </div>
        <div class="logo">
            <img src="./home.svg" alt="LOGO">
        </div>
    </section>

    <!-- 下载按钮区 (section2) -->
    <section class="section2">
        <!-- 安卓下载按钮：点击跳转到带下载参数的当前页面 -->
        <button class="download-btn" onclick="window.location.href='?download=android'">下载软件</button>
        <button class="download-btn" disabled>下载IOS版软件（暂不支持）</button>
        <button class="download-btn" onclick="window.open('http://an.kijk.top/no10.html', '_blank')">免下载使用网页版</button>
    </section>

    <script>
        // 轮播功能实现
        document.addEventListener('DOMContentLoaded', function() {
            const slidesContainer = document.querySelector('.carousel-slides');
            const slides = document.querySelectorAll('.carousel-slide');
            const prevBtn = document.querySelector('.prev-btn');
            const nextBtn = document.querySelector('.next-btn');
            const indicators = document.querySelectorAll('.indicator');
            const slideCount = slides.length;
            let currentIndex = 0;
            let autoPlayInterval;

            // 设置轮播自动播放
            const startAutoPlay = () => {
                autoPlayInterval = setInterval(() => {
                    goToSlide(currentIndex + 1);
                }, 3000); // 3秒切换一次
            };

            // 停止自动播放
            const stopAutoPlay = () => {
                clearInterval(autoPlayInterval);
            };

            // 切换到指定轮播图
            const goToSlide = (index) => {
                // 处理循环
                currentIndex = (index + slideCount) % slideCount;
                // 计算偏移量
                const slideWidth = slides[0].clientWidth;
                slidesContainer.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
                // 更新指示器状态
                updateIndicators();
            };

            // 更新指示器激活状态
            const updateIndicators = () => {
                indicators.forEach((indicator, index) => {
                    if (index === currentIndex) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
            };

            // 左右按钮点击事件
            prevBtn.addEventListener('click', () => {
                stopAutoPlay();
                goToSlide(currentIndex - 1);
                startAutoPlay();
            });

            nextBtn.addEventListener('click', () => {
                stopAutoPlay();
                goToSlide(currentIndex + 1);
                startAutoPlay();
            });

            // 指示器点击事件
            indicators.forEach((indicator) => {
                indicator.addEventListener('click', () => {
                    stopAutoPlay();
                    const index = parseInt(indicator.dataset.index);
                    goToSlide(index);
                    startAutoPlay();
                });
            });

            // 窗口大小变化时重新计算轮播宽度
            window.addEventListener('resize', () => {
                const slideWidth = slides[0].clientWidth;
                slidesContainer.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
            });

            // 鼠标悬停轮播时停止自动播放
            document.querySelector('.section0').addEventListener('mouseenter', stopAutoPlay);
            document.querySelector('.section0').addEventListener('mouseleave', startAutoPlay);

            // 启动自动播放
            startAutoPlay();
        });
    </script>

<!-- =================================== 雪花框架JS =================================== -->
<script>
    // 雪花配置项（适配当前页面，性能友好）
    const SNOW_CONFIG = {
        snowSizeMin: 2, // 雪花最小尺寸（小巧精致）
        snowSizeMax: 10, // 雪花最大尺寸
        snowSpeedMin: 8, // 最小下落时间（s）
        snowSpeedMax: 15, // 最大下落时间（s，速度适中）
        snowCount: 80, // 同时存在的雪花数量（避免卡顿）
        createInterval: 300, // 雪花创建间隔（ms，源源不尽）
        offsetRange: 20 // 横向偏移范围（vw，无序摆动）
    };

    // 雪花核心类
    class SnowFlake {
        constructor(container) {
            this.container = container;
            this.initSnow();
        }

        // 初始化单个雪花的属性
        initSnow() {
            this.snow = document.createElement('div');
            this.snow.classList.add('snowflake');

            // 随机尺寸
            const size = Math.random() * (SNOW_CONFIG.snowSizeMax - SNOW_CONFIG.snowSizeMin) + SNOW_CONFIG.snowSizeMin;
            this.snow.style.width = `${size}px`;
            this.snow.style.height = `${size}px`;

            // 随机初始水平位置（左右全屏）
            this.snow.style.left = `${Math.random() * 100}vw`;

            // 随机初始垂直位置（顶部上方，实现"源源不绝"）
            this.snow.style.top = `${-Math.random() * 20}px`;

            // 随机横向偏移（实现无序摆动）
            const offset = (Math.random() - 0.5) * SNOW_CONFIG.offsetRange;
            this.snow.style.setProperty('--offset', offset);

            // 随机下落速度
            const duration = Math.random() * (SNOW_CONFIG.snowSpeedMax - SNOW_CONFIG.snowSpeedMin) + SNOW_CONFIG.snowSpeedMin;
            this.snow.style.animation = `snowfall ${duration}s linear forwards`;

            // 随机动画延迟（避免雪花同步下落）
            this.snow.style.animationDelay = `${Math.random() * 5}s`;

            // 添加到容器
            this.container.appendChild(this.snow);

            // 雪花落地后自动移除（避免DOM堆积）
            setTimeout(() => {
                this.snow.remove();
            }, duration * 1000);
        }
    }

    // 初始化雪花框架
    function initSnowFrame() {
        // 获取/创建雪花容器
        let snowContainer = document.getElementById('snowContainer');
        if (!snowContainer) {
            snowContainer = document.createElement('div');
            snowContainer.className = 'snow-container';
            snowContainer.id = 'snowContainer';
            document.body.appendChild(snowContainer);
        }

        // 控制同时存在的雪花数量，避免性能问题
        let currentSnowCount = 0;
        const maxSnowCount = SNOW_CONFIG.snowCount;

        // 定时创建雪花（源源不尽）
        setInterval(() => {
            if (currentSnowCount < maxSnowCount) {
                new SnowFlake(snowContainer);
                currentSnowCount++;

                // 雪花移除后减少计数
                setTimeout(() => {
                    currentSnowCount--;
                }, SNOW_CONFIG.snowSpeedMax * 1000);
            }
        }, SNOW_CONFIG.createInterval);
    }

    // 页面加载完成后初始化（不干扰其他脚本）
    if (document.readyState === 'complete') {
        initSnowFrame();
    } else {
        window.addEventListener('DOMContentLoaded', initSnowFrame);
    }
</script>
<!-- =================================== 雪花框架JS结束 =================================== -->
</body>
</html>
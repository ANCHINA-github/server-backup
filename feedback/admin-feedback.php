<?php
// 读取反馈数据（增强鲁棒性，避免文件读取/解析警告）
$jsonFile = __DIR__ . '/feedback-data.json';
$feedbackData = [];
if (file_exists($jsonFile)) {
    // 抑制文件读取警告 + 检查读取结果
    $jsonContent = @file_get_contents($jsonFile);
    if ($jsonContent !== false) {
        // 确保解析失败时返回空数组，兼容PHP各版本
        $feedbackData = json_decode($jsonContent, true) ?: [];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <!-- 增强移动端适配：禁止缩放 + 适配iOS刘海屏 + 标准视口 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <!-- 兼容IE浏览器的HTML5标签识别 -->
    <!--[if lt IE 9]>
    <script src="https://cdn.jsdelivr.net/npm/html5shiv@3.7.3/dist/html5shiv.min.js"></script>
    <![endif]-->
    <title>反馈管理后台</title>
    <style>
        /* 全局重置：兼容各浏览器默认样式 */
        * {
            margin: 0;
            padding: 0;
            /* 兼容旧版webkit/gecko浏览器 */
            -webkit-box-sizing: border-box;
               -moz-box-sizing: border-box;
                    box-sizing: border-box;
            /* 多系统字体适配：Windows(微软雅黑)、macOS/iOS(苹方)、Linux(文泉驿)、安卓(思源黑体) */
            font-family: "Microsoft Yahei", "PingFang SC", "Hiragino Sans GB", "Heiti SC", "WenQuanYi Micro Hei", "Source Han Sans CN", sans-serif;
        }

        body {
            background: #f5f5f5;
            padding: 20px;
            /* 适配不同屏幕高度，避免内容过短 */
            min-height: 100vh;
            /* 字体大小基准，适配不同设备 */
            font-size: 16px;
            line-height: 1.5;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            /* 阴影兼容旧版浏览器 */
            -webkit-box-shadow: 0 2px 10px rgba(0,0,0,0.1);
               -moz-box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            /* 适配小屏幕容器内边距 */
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
        }

        .empty-tip {
            text-align: center;
            color: #999;
            padding: 40px 0;
            font-size: 16px;
            line-height: 1.8;
        }

        /* 表格滚动容器：解决移动端表格横向溢出问题 */
        .table-container {
            width: 100%;
            overflow-x: auto;
            /* 移动端滚动条样式优化 */
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            /* 避免表格被压缩 */
            min-width: 600px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            /* 提升文字可读性 */
            line-height: 1.5;
        }

        th {
            background: #f8f9fa;
            color: #333;
            font-weight: 500;
            /* 表头文字不换行 */
            white-space: nowrap;
        }

        tr:hover {
            background: #f8f9fa;
            /* 兼容旧版浏览器hover效果 */
            -webkit-transition: background 0.2s ease;
               -moz-transition: background 0.2s ease;
                    transition: background 0.2s ease;
        }

        .content-cell {
            max-width: 300px;
            word-break: break-all;
            /* 兼容所有浏览器的换行规则 */
            word-wrap: break-word;
        }

        /* 平板/移动端适配（768px以下） */
        @media (max-width: 768px) {
            body {
                padding: 10px;
                font-size: 14px;
            }

            .container {
                padding: 15px;
            }

            h1 {
                font-size: 20px;
                margin-bottom: 15px;
            }

            th, td {
                padding: 8px 6px;
                font-size: 14px;
            }

            .content-cell {
                max-width: 150px;
            }

            .empty-tip {
                padding: 30px 0;
                font-size: 14px;
            }
        }

        /* 小屏手机适配（480px以下） */
        @media (max-width: 480px) {
            th, td {
                padding: 6px 4px;
                font-size: 13px;
            }

            .container {
                padding: 10px;
            }

            h1 {
                font-size: 18px;
            }
        }

        /* 大屏PC适配（1200px以上） */
        @media (min-width: 1200px) {
            .container {
                max-width: 1200px;
            }

            table {
                min-width: 800px;
            }

            .content-cell {
                max-width: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>反馈管理后台</h1>
        
        <?php if (empty($feedbackData)): ?>
            <div class="empty-tip">暂无反馈数据</div>
        <?php else: ?>
            <!-- 表格滚动容器：解决移动端横向溢出 -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>序号</th>
                            <th>提交时间</th>
                            <th>昵称</th>
                            <th>身份</th>
                            <th class="content-cell">反馈内容</th>
                            <th>联系邮箱</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbackData as $index => $item): ?>
                            <tr>
                                <td><?php echo (int)($index + 1); ?></td>
                                <td><?php echo htmlspecialchars($item['create_time'] ?? '', ENT_QUOTES | ENT_HTML5); ?></td>
                                <td><?php echo htmlspecialchars($item['nickname'] ?? '', ENT_QUOTES | ENT_HTML5); ?></td>
                                <td><?php echo htmlspecialchars($item['identity'] ?? '', ENT_QUOTES | ENT_HTML5); ?></td>
                                <td class="content-cell"><?php echo htmlspecialchars($item['content'] ?? '', ENT_QUOTES | ENT_HTML5); ?></td>
                                <td><?php echo htmlspecialchars($item['email'] ?? '', ENT_QUOTES | ENT_HTML5); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
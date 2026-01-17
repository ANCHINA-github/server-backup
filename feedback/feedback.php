<?php
// 初始化提示信息
$message = '';
$messageType = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 收集表单数据并过滤
    $nickname = trim($_POST['feedback-user'] ?? '');
    $identity = trim($_POST['feed-identity'] ?? '');
    $content = trim($_POST['feedback-content'] ?? '');
    $email = trim($_POST['feedback-mail'] ?? '');

    // 后端验证规则
    $errors = [];
    if (empty($nickname)) {
        $errors[] = '昵称不能为空';
    }
    if (!in_array($identity, ['学生', '教师', '其他'])) {
        $errors[] = '请选择正确的身份类型';
    }
    if (empty($content)) {
        $errors[] = '反馈内容不能为空';
    }
    if (empty($email)) {
        $errors[] = '联系邮箱不能为空';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '邮箱格式不正确（示例：xxx@xxx.com）';
    }

    // 验证通过则写入JSON文件
    if (empty($errors)) {
        $jsonFile = __DIR__ . '/feedback-data.json';
        // 读取现有数据（文件不存在则创建空数组）
        $feedbackData = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
        // 添加新反馈（含时间戳）
        $feedbackData[] = [
            'nickname' => $nickname,
            'identity' => $identity,
            'content' => $content,
            'email' => $email,
            'create_time' => date('Y-m-d H:i:s') // 提交时间
        ];
        // 写入JSON文件（保留中文，格式化输出）
        if (file_put_contents($jsonFile, json_encode($feedbackData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            $message = '反馈提交成功！';
            $messageType = 'success';
            // 清空表单
            $_POST = [];
        } else {
            $errors[] = '反馈提交失败，请检查文件写入权限';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../logo.png">
    <title>意见反馈</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", sans-serif;
        }
        body {
            background: #000000ff;
            background-image: url('./feedback-img.png');
             background-position: center;
             background-repeat: no-repeat;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
           background-color: #c60000a6;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #fc6000ff;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #fc6000ff;
            font-weight: 500;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #ff6f00ff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            color: #fc0000ff;
        }
        .message {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>意见反馈</h1>
        <!-- 提示信息 -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- 反馈表单 -->
        <form method="post" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="feedback-user">昵称：</label>
                <input type="text" id="feedback-user" name="feedback-user" 
                       value="<?php echo htmlspecialchars($_POST['feedback-user'] ?? '', ENT_QUOTES); ?>" 
                       placeholder="请输入您的昵称">
            </div>
            <div class="form-group">
                <label for="feed-identity">身份：</label>
                <select id="feed-identity" name="feed-identity">
                    <option value="">请选择身份</option>
                    <option value="学生" <?php echo (($_POST['feed-identity'] ?? '') === '学生') ? 'selected' : ''; ?>>学生</option>
                    <option value="教师" <?php echo (($_POST['feed-identity'] ?? '') === '教师') ? 'selected' : ''; ?>>教师</option>
                    <option value="其他" <?php echo (($_POST['feed-identity'] ?? '') === '其他') ? 'selected' : ''; ?>>其他</option>
                </select>
            </div>
            <div class="form-group">
                <label for="feedback-content">反馈内容：</label>
                <textarea id="feedback-content" name="feedback-content" placeholder="请详细描述您的反馈内容"><?php echo htmlspecialchars($_POST['feedback-content'] ?? '', ENT_QUOTES); ?></textarea>
            </div>
            <div class="form-group">
                <label for="feedback-mail">联系邮箱：</label>
                <input type="email" id="feedback-mail" name="feedback-mail" 
                       value="<?php echo htmlspecialchars($_POST['feedback-mail'] ?? '', ENT_QUOTES); ?>" 
                       placeholder="请输入您的邮箱，方便回复">
            </div>
            <button type="submit">提交反馈</button>
            <a href="javascript:history.go(-1);" style="color: white;">->点我返回上一页-<</a>
        </form>
    </div>

    <script>
        // 前端表单验证（增强用户体验）
        function validateForm() {
            const nickname = document.getElementById('feedback-user').value.trim();
            const identity = document.getElementById('feed-identity').value;
            const content = document.getElementById('feedback-content').value.trim();
            const email = document.getElementById('feedback-mail').value.trim();

            if (!nickname) {
                alert('请输入昵称');
                return false;
            }
            if (!identity) {
                alert('请选择身份');
                return false;
            }
            if (!content) {
                alert('请输入反馈内容');
                return false;
            }
            if (!email) {
                alert('请输入联系邮箱');
                return false;
            }
            // 前端邮箱格式验证
            const emailReg = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
            if (!emailReg.test(email)) {
                alert('邮箱格式不正确（示例：xxx@xxx.com）');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
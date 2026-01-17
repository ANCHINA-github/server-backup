<?php
// 解决跨域问题（可选，若前端和后端同域可注释）
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
// 设置响应格式为JSON
header('Content-Type: application/json');

// 1. 接收前端POST的JSON数据
$rawData = file_get_contents('php://input');
$messageData = json_decode($rawData, true);

// 2. 验证数据合法性
if (!$messageData || !isset($messageData['userimg']) || !isset($messageData['username']) || !isset($messageData['usermessage'])) {
    http_response_code(400); // 错误请求
    echo json_encode(['status' => 'error', 'msg' => '缺少必要的消息数据']);
    exit;
}

// 3. 定义chat-data.json文件路径（确保路径正确）
$jsonFile = __DIR__ . '/chat-data.json';

// 4. 读取原有消息数据（若文件不存在则初始化空数组）
if (!file_exists($jsonFile)) {
    // 创建文件并写入空数组
    file_put_contents($jsonFile, json_encode([]), LOCK_EX);
    $messages = [];
} else {
    $jsonContent = file_get_contents($jsonFile);
    $messages = json_decode($jsonContent, true);
    // 若JSON解析失败，重置为空数组
    if (json_last_error() !== JSON_ERROR_NONE) {
        $messages = [];
    }
}
// 5. 追加新消息（可添加时间戳等扩展字段）
$newMessage = [
    'userimg' => $messageData['userimg'],
    'username' => $messageData['username'],
    'usermessage' => $messageData['usermessage'],
    'timestamp' => time() // 可选：添加消息发送时间戳
];
$messages[] = $newMessage;

// 6. 将更新后的消息写回JSON文件
$writeResult = file_put_contents($jsonFile, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
if ($writeResult === false) {
    http_response_code(500); // 服务器内部错误
    echo json_encode(['status' => 'error', 'msg' => '消息存储失败，请检查文件权限']);
    exit;
}

// 7. 返回成功响应
echo json_encode(['status' => 'success', 'msg' => '消息发送成功']);
exit;
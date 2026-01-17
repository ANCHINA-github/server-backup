<?php
/**
 * 简单图片审核系统 - 无数据库版本
 * 功能：审核img目录下的图片，确保不重复审核，自动感知新图片
 */

// 启用错误显示（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 启动session（用于显示消息）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== 配置区域 ====================
$base_dir = __DIR__ . '/';
$config = [
    'img_dir' => $base_dir . 'img/',              // 图片目录
    'audited_dir' => $base_dir . 'img_audited/',  // 已审核目录
    'rejected_dir' => $base_dir . 'img_rejected/',// 已拒绝目录
    'skipped_dir' => $base_dir . 'img_skipped/',  // 已跳过目录（可选）
    'data_file' => $base_dir . 'audit_data.json', // 审核记录文件
    'allowed_ext' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'], // 支持的图片格式
    'max_file_size' => 100 * 1024 * 1024, // 最大文件大小 100MB
    'auto_refresh_interval' => 30, // 自动刷新检查新图片的间隔（秒）
];

// ==================== 辅助函数 ====================
/**
 * 格式化文件大小
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' 字节';
    }
}

/**
 * 创建目录（如果不存在）
 */
function ensureDirectory($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * 获取安全的文件名
 */
function getSafeFilename($filename) {
    return preg_replace('/[^\w\-\.]/', '_', $filename);
}

/**
 * 获取文件扩展名
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * 检查是否是支持的图片格式
 */
function isSupportedImage($filename, $allowed_ext) {
    $ext = getFileExtension($filename);
    return in_array($ext, $allowed_ext);
}

// ==================== 初始化 ====================
// 确保所有目录存在
foreach ([$config['img_dir'], $config['audited_dir'], $config['rejected_dir'], $config['skipped_dir']] as $dir) {
    ensureDirectory($dir);
}

// ==================== 加载审核数据 ====================
function loadAuditData($data_file) {
    if (file_exists($data_file)) {
        $content = file_get_contents($data_file);
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
    }
    
    // 默认数据
    return [
        'audited' => [],      // 已审核文件
        'rejected' => [],     // 已拒绝文件
        'skipped' => [],      // 已跳过文件
        'last_scan' => time(), // 最后扫描时间
        'total_processed' => 0, // 总共处理数量
        'audit_history' => []  // 审核历史记录
    ];
}

// 加载数据
$audit_data = loadAuditData($config['data_file']);

// ==================== 扫描图片函数 ====================
function scanImagesRecursive($dir, $base_dir, $allowed_ext) {
    $images = [];
    
    if (!is_dir($dir)) {
        return $images;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filename = $file->getFilename();
            
            // 跳过隐藏文件
            if (strpos($filename, '.') === 0) {
                continue;
            }
            
            if (isSupportedImage($filename, $allowed_ext)) {
                $full_path = $file->getPathname();
                $relative_path = str_replace($base_dir, '', $full_path);
                $relative_path = ltrim($relative_path, '/\\');
                
                // 跳过已处理目录中的文件
                if (strpos($full_path, '_audited') !== false || 
                    strpos($full_path, '_rejected') !== false ||
                    strpos($full_path, '_skipped') !== false) {
                    continue;
                }
                
                // 获取相对于网站根目录的URL路径
                $web_path = getWebPath($full_path);
                
                $images[] = [
                    'full_path' => $full_path,
                    'web_path' => $web_path,  // 新增：网页可访问的路径
                    'relative_path' => $relative_path,
                    'filename' => $filename,
                    'size' => $file->getSize(),
                    'mtime' => $file->getMTime(),
                    'ctime' => $file->getCTime(),
                    'extension' => getFileExtension($filename)
                ];
            }
        }
    }
    
    // 按修改时间排序（最新的在前面）
    usort($images, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
    
    return $images;
}

/**
 * 获取图片的Web可访问路径
 */
function getWebPath($full_path) {
    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    
    // 尝试多种方法获取Web路径
    if (!empty($doc_root) && strpos($full_path, $doc_root) === 0) {
        // 如果在文档根目录下
        return str_replace($doc_root, '', $full_path);
    } elseif (strpos($full_path, $script_dir) === 0) {
        // 如果在脚本目录下
        $relative = str_replace($script_dir, '', $full_path);
        $script_name = basename($_SERVER['SCRIPT_NAME']);
        $script_dir_web = dirname($_SERVER['SCRIPT_NAME']);
        
        if ($script_dir_web === '.') {
            return ltrim($relative, '/\\');
        } else {
            return $script_dir_web . ltrim($relative, '/\\');
        }
    } else {
        // 尝试通过相对路径
        $relative = str_replace(__DIR__, '', $full_path);
        return ltrim($relative, '/\\');
    }
}

// ==================== 获取未审核图片 ====================
function getUnauditedImages($config, &$audit_data) {
    $all_images = scanImagesRecursive($config['img_dir'], $config['img_dir'], $config['allowed_ext']);
    $unaudited = [];
    
    // 获取所有已处理的文件路径
    $processed_files = array_merge(
        $audit_data['audited'],
        $audit_data['rejected'],
        $audit_data['skipped']
    );
    
    foreach ($all_images as $image) {
        $relative_path = $image['relative_path'];
        
        // 检查是否已处理
        if (!in_array($relative_path, $processed_files)) {
            // 检查文件是否还存在
            if (file_exists($image['full_path'])) {
                // 检查文件大小限制
                if ($image['size'] <= $config['max_file_size']) {
                    $unaudited[] = $image;
                }
            }
        }
    }
    
    return $unaudited;
}

// ==================== 处理审核操作 ====================
function processAuditAction($action, $image_data, $notes, $config, &$audit_data) {
    $success = false;
    $message = '';
    $image_path = $image_data['full_path'];
    $relative_path = $image_data['relative_path'];
    $filename = $image_data['filename'];
    
    // 创建审核记录
    $audit_record = [
        'filename' => $filename,
        'relative_path' => $relative_path,
        'action' => $action,
        'time' => date('Y-m-d H:i:s'),
        'notes' => $notes,
        'file_size' => $image_data['size'],
        'file_type' => $image_data['extension']
    ];
    
    switch ($action) {
        case 'approve':
            // 移动到已审核目录
            $dest_dir = $config['audited_dir'];
            $dest_path = $dest_dir . getSafeFilename($filename);
            
            // 处理文件名冲突
            $counter = 1;
            while (file_exists($dest_path)) {
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $dest_path = $dest_dir . getSafeFilename($name . '_' . $counter . '.' . $ext);
                $counter++;
            }
            
            if (rename($image_path, $dest_path)) {
                $audit_data['audited'][] = $relative_path;
                $message = "✅ 图片已通过审核";
                $success = true;
                
                // 记录历史
                $audit_record['new_path'] = $dest_path;
                $audit_data['audit_history'][] = $audit_record;
            } else {
                $message = "❌ 移动文件失败";
            }
            break;
            
        case 'reject':
            // 移动到已拒绝目录
            $dest_dir = $config['rejected_dir'];
            $dest_path = $dest_dir . getSafeFilename($filename);
            
            // 处理文件名冲突
            $counter = 1;
            while (file_exists($dest_path)) {
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $dest_path = $dest_dir . getSafeFilename($name . '_' . $counter . '.' . $ext);
                $counter++;
            }
            
            if (rename($image_path, $dest_path)) {
                $audit_data['rejected'][] = $relative_path;
                $message = "❌ 图片已拒绝";
                $success = true;
                
                // 记录历史
                $audit_record['new_path'] = $dest_path;
                $audit_data['audit_history'][] = $audit_record;
            } else {
                $message = "❌ 移动文件失败";
            }
            break;
            
        case 'skip':
            // 标记为跳过
            if (!in_array($relative_path, $audit_data['skipped'])) {
                $audit_data['skipped'][] = $relative_path;
                $message = "⏭️ 图片已跳过";
                $success = true;
                
                // 可选：移动到跳过目录
                if (is_dir($config['skipped_dir'])) {
                    $dest_path = $config['skipped_dir'] . getSafeFilename($filename);
                    if (rename($image_path, $dest_path)) {
                        $audit_record['new_path'] = $dest_path;
                    }
                }
                
                // 记录历史
                $audit_data['audit_history'][] = $audit_record;
            } else {
                $message = "⚠️ 图片已跳过";
                $success = true;
            }
            break;
    }
    
    // 更新统计数据
    if ($success) {
        $audit_data['total_processed']++;
        $audit_data['last_scan'] = time();
        
        // 保存审核数据
        if (saveAuditData($config['data_file'], $audit_data)) {
            return [
                'success' => true,
                'message' => $message,
                'record' => $audit_record
            ];
        } else {
            return [
                'success' => false,
                'message' => '保存审核记录失败'
            ];
        }
    }
    
    return [
        'success' => $success,
        'message' => $message
    ];
}

// ==================== 保存审核数据 ====================
function saveAuditData($data_file, $data) {
    // 限制历史记录数量（保留最近1000条）
    if (count($data['audit_history']) > 1000) {
        $data['audit_history'] = array_slice($data['audit_history'], -1000);
    }
    
    return file_put_contents($data_file, json_encode($data, JSON_PRETTY_UNICODE | JSON_PRETTY_PRINT));
}

// ==================== 获取统计信息 ====================
function getAuditStats($config, $audit_data, $unaudited_images) {
    $total_images = count(scanImagesRecursive($config['img_dir'], $config['img_dir'], $config['allowed_ext']));
    
    // 计算各个目录的图片数量
    $audited_count = 0;
    $rejected_count = 0;
    $skipped_count = 0;
    
    if (is_dir($config['audited_dir'])) {
        $audited_count = count(scanImagesRecursive($config['audited_dir'], $config['audited_dir'], $config['allowed_ext']));
    }
    
    if (is_dir($config['rejected_dir'])) {
        $rejected_count = count(scanImagesRecursive($config['rejected_dir'], $config['rejected_dir'], $config['allowed_ext']));
    }
    
    if (is_dir($config['skipped_dir'])) {
        $skipped_count = count(scanImagesRecursive($config['skipped_dir'], $config['skipped_dir'], $config['allowed_ext']));
    }
    
    return [
        'total' => $total_images,
        'unaudited' => count($unaudited_images),
        'audited' => $audited_count,
        'rejected' => $rejected_count,
        'skipped' => $skipped_count,
        'total_processed' => $audit_data['total_processed'] ?? 0,
        'last_scan' => date('Y-m-d H:i:s', $audit_data['last_scan'] ?? time())
    ];
}

// ==================== 主程序逻辑 ====================
// 处理POST请求（审核操作）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $image_data_json = $_POST['image_data'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if ($action && $image_data_json) {
        $image_data = json_decode($image_data_json, true);
        
        if ($image_data && file_exists($image_data['full_path'])) {
            $result = processAuditAction($action, $image_data, $notes, $config, $audit_data);
            $_SESSION['audit_message'] = $result['message'];
            $_SESSION['last_action'] = $action;
            
            // 重定向以避免重复提交
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// 获取未审核图片
$unaudited_images = getUnauditedImages($config, $audit_data);
$current_image = !empty($unaudited_images) ? $unaudited_images[0] : null;
$stats = getAuditStats($config, $audit_data, $unaudited_images);

// ==================== HTML页面开始 ====================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片审核系统 - 无数据库版</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;700&display=swap">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --success-color: #06d6a0;
            --success-dark: #05c08f;
            --danger-color: #ef476f;
            --danger-dark: #e03e64;
            --warning-color: #ffd166;
            --warning-dark: #ffc745;
            --info-color: #118ab2;
            --dark-color: #073b4c;
            --light-color: #f8f9fa;
            --gray-color: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans SC', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            min-height: 90vh;
        }
        
        /* 头部样式 */
        .header {
            background: linear-gradient(135deg, var(--dark-color) 0%, var(--primary-color) 100%);
            color: white;
            padding: 30px 40px;
            position: relative;
            overflow: hidden;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 700;
        }
        
        .header h1 i {
            color: var(--success-color);
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
            max-width: 800px;
        }
        
        /* 统计卡片样式 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: var(--light-color);
            border-bottom: 1px solid #e9ecef;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }
        
        .stat-card.total::before { background: var(--primary-color); }
        .stat-card.unaudited::before { background: var(--warning-color); }
        .stat-card.audited::before { background: var(--success-color); }
        .stat-card.rejected::before { background: var(--danger-color); }
        .stat-card.skipped::before { background: var(--gray-color); }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        
        .stat-label {
            font-size: 1rem;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        /* 主要内容区域 */
        .main-content {
            display: flex;
            min-height: 600px;
        }
        
        @media (max-width: 1200px) {
            .main-content {
                flex-direction: column;
            }
        }
        
        /* 图片区域 */
        .image-section {
            flex: 3;
            padding: 40px;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 600px;
        }
        
        .image-container {
            width: 100%;
            max-width: 1000px;
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .image-wrapper {
            width: 100%;
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 10px;
            background: linear-gradient(45deg, #2c3e50, #4a6491);
            position: relative;
        }
        
        #current-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .image-info {
            margin-top: 20px;
            padding: 20px;
            background: #e9ecef;
            border-radius: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* 控制区域 */
        .controls-section {
            flex: 1;
            padding: 40px 30px;
            background: white;
            border-left: 1px solid #e9ecef;
            min-width: 380px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .action-btn {
            padding: 20px 30px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            color: white;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, var(--success-color) 0%, var(--success-dark) 100%);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, var(--danger-color) 0%, var(--danger-dark) 100%);
        }
        
        .btn-skip {
            background: linear-gradient(135deg, var(--gray-color) 0%, #5a6268 100%);
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .action-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* 备注区域 */
        .notes-section {
            margin-top: 30px;
        }
        
        .notes-section h3 {
            margin-bottom: 15px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notes-textarea {
            width: 100%;
            height: 120px;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            transition: var(--transition);
        }
        
        .notes-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            width: 100%;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: var(--success-color);
            margin-bottom: 20px;
        }
        
        .empty-state h2 {
            font-size: 2rem;
            color: var(--dark-color);
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .action-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--gray-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: #333;
        }
        
        .btn-warning:hover {
            background: var(--warning-dark);
            transform: translateY(-2px);
        }
        
        /* 消息提示 */
        .message-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            transform: translateX(150%);
            transition: transform 0.5s ease;
            z-index: 1000;
            max-width: 400px;
        }
        
        .message-alert.show {
            transform: translateX(0);
        }
        
        .message-alert.success {
            border-left: 5px solid var(--success-color);
        }
        
        .message-alert.error {
            border-left: 5px solid var(--danger-color);
        }
        
        /* 键盘提示 */
        .keyboard-hint {
            margin-top: 20px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            color: #666;
        }
        
        .keyboard-hint kbd {
            background: white;
            padding: 4px 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-family: monospace;
            margin: 0 5px;
            box-shadow: 0 2px 0 #ccc;
        }
        
        /* 系统信息面板 */
        .system-panel {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e9ecef;
        }
        
        .system-panel h3 {
            margin-bottom: 15px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .system-info {
            font-size: 14px;
            color: #666;
        }
        
        .system-info p {
            margin-bottom: 8px;
        }
        
        /* 加载动画 */
        .loader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }
        
        .loader.show {
            display: flex;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 响应式调整 */
        @media (max-width: 768px) {
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                padding: 20px;
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .image-section, .controls-section {
                padding: 20px;
            }
            
            .image-wrapper {
                height: 300px;
            }
            
            .action-btn {
                padding: 15px 20px;
                font-size: 1rem;
            }
            
            .btn {
                padding: 10px 20px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* 图片错误样式 */
        .image-error {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .image-error i {
            font-size: 3rem;
            color: var(--danger-color);
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['audit_message'])): ?>
    <div class="message-alert <?php echo isset($_SESSION['last_action']) && $_SESSION['last_action'] === 'reject' ? 'error' : 'success'; ?> show" id="message-alert">
        <i class="fas <?php 
            if (isset($_SESSION['last_action'])) {
                switch($_SESSION['last_action']) {
                    case 'approve': echo 'fa-check-circle'; break;
                    case 'reject': echo 'fa-times-circle'; break;
                    case 'skip': echo 'fa-forward'; break;
                }
            }
        ?>" style="font-size: 1.5rem;"></i>
        <span><?php echo htmlspecialchars($_SESSION['audit_message']); ?></span>
    </div>
    <?php 
        unset($_SESSION['audit_message']);
        unset($_SESSION['last_action']);
    endif; ?>
    
    <div class="loader" id="loader">
        <div class="spinner"></div>
        <p>正在处理...</p>
    </div>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-images"></i> 图片审核系统</h1>
            <p>无需数据库 | 自动感知新图片 | 确保不重复审核</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label"><i class="fas fa-image"></i> 总图片数</div>
            </div>
            
            <div class="stat-card unaudited">
                <div class="stat-number"><?php echo $stats['unaudited']; ?></div>
                <div class="stat-label"><i class="fas fa-clock"></i> 待审核</div>
            </div>
            
            <div class="stat-card audited">
                <div class="stat-number"><?php echo $stats['audited']; ?></div>
                <div class="stat-label"><i class="fas fa-check-circle"></i> 已通过</div>
            </div>
            
            <div class="stat-card rejected">
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label"><i class="fas fa-times-circle"></i> 已拒绝</div>
            </div>
            
            <div class="stat-card skipped">
                <div class="stat-number"><?php echo $stats['skipped']; ?></div>
                <div class="stat-label"><i class="fas fa-forward"></i> 已跳过</div>
            </div>
        </div>
        
        <div class="main-content">
            <?php if ($current_image): ?>
                <div class="image-section">
                    <div class="image-container">
                        <div class="image-wrapper" id="image-wrapper">
                            <?php
                            // 输出图片，使用web_path（网页可访问的路径）
                            $image_src = $current_image['web_path'];
                            // 确保路径以/开头
                            if (!empty($image_src) && $image_src[0] !== '/') {
                                $image_src = '/' . $image_src;
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                 id="current-image"
                                 alt="<?php echo htmlspecialchars($current_image['filename']); ?>"
                                 onerror="showImageError(this, '<?php echo htmlspecialchars($current_image['filename']); ?>')">
                        </div>
                        
                        <div class="image-info">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-file"></i> 文件名:</span>
                                    <span><?php echo htmlspecialchars($current_image['filename']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-weight"></i> 文件大小:</span>
                                    <span><?php echo formatFileSize($current_image['size']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-calendar"></i> 修改时间:</span>
                                    <span><?php echo date('Y-m-d H:i:s', $current_image['mtime']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-file-image"></i> 文件类型:</span>
                                    <span><?php echo strtoupper($current_image['extension']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-info-circle"></i> 状态:</span>
                                    <span style="color: var(--warning-color); font-weight: bold;">待审核</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="keyboard-hint">
                        <p><i class="fas fa-keyboard"></i> 键盘快捷键： 
                            <kbd>A</kbd> 通过 | <kbd>R</kbd> 拒绝 | <kbd>S</kbd> 跳过 | <kbd>空格</kbd> 刷新
                        </p>
                    </div>
                </div>
                
                <div class="controls-section">
                    <form method="POST" id="audit-form" onsubmit="showLoader()">
                        <input type="hidden" name="image_data" value='<?php echo json_encode($current_image, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                        
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approve" class="action-btn btn-approve"
                                    onclick="return confirmAction('通过', this.form)">
                                <i class="fas fa-check-circle"></i>
                                <span>通过审核 (A)</span>
                            </button>
                            
                            <button type="submit" name="action" value="reject" class="action-btn btn-reject"
                                    onclick="return confirmAction('拒绝', this.form)">
                                <i class="fas fa-times-circle"></i>
                                <span>拒绝图片 (R)</span>
                            </button>
                            
                            <button type="submit" name="action" value="skip" class="action-btn btn-skip">
                                <i class="fas fa-forward"></i>
                                <span>跳过此图 (S)</span>
                            </button>
                        </div>
                        
                        <div class="notes-section">
                            <h3><i class="fas fa-edit"></i> 审核备注 (可选)</h3>
                            <textarea name="notes" class="notes-textarea" 
                                      placeholder="请输入审核备注... (支持快速输入：输入1=色情，2=暴力，3=广告，4=其他)"></textarea>
                        </div>
                    </form>
                    
                    <div class="system-panel">
                        <h3><i class="fas fa-info-circle"></i> 系统信息</h3>
                        <div class="system-info">
                            <p><strong>最后扫描:</strong> <?php echo $stats['last_scan']; ?></p>
                            <p><strong>已处理总数:</strong> <?php echo $stats['total_processed']; ?> 张</p>
                            <p><strong>图片目录:</strong> <?php echo htmlspecialchars($config['img_dir']); ?></p>
                            <p><strong>支持格式:</strong> <?php echo implode(', ', $config['allowed_ext']); ?></p>
                            <p><i class="fas fa-lightbulb"></i> 提示：将新图片放入 img 目录即可自动识别</p>
                        </div>
                    </div>
                    
                    <div class="action-group" style="margin-top: 20px;">
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-redo"></i> 刷新页面
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state" style="width: 100%;">
                    <i class="fas fa-check-circle"></i>
                    <h2>恭喜！所有图片都已审核完成 🎉</h2>
                    <p>系统会自动检测新添加到 img 目录的图片</p>
                    
                    <div class="action-group">
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-redo"></i> 刷新检查新图片
                        </button>
                    </div>
                    
                    <div style="margin-top: 40px; padding: 25px; background: #f8f9fa; border-radius: var(--border-radius); max-width: 800px; margin-left: auto; margin-right: auto;">
                        <h4 style="margin-bottom: 15px; color: var(--dark-color);"><i class="fas fa-lightbulb"></i> 使用说明：</h4>
                        <ul style="text-align: left; color: #666; line-height: 1.8;">
                            <li><strong>添加新图片：</strong>直接将图片放入 <code>img</code> 目录即可</li>
                            <li><strong>自动识别：</strong>系统会自动扫描新图片并显示在审核页面</li>
                            <li><strong>不重复审核：</strong>已审核的图片不会重复出现</li>
                            <li><strong>分类存储：</strong>已通过的图片移动到 <code>img_audited</code>，拒绝的移动到 <code>img_rejected</code></li>
                            <li><strong>审核效率：</strong>使用键盘快捷键可大幅提高审核速度</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ==================== 全局变量 ====================
        let currentImage = document.getElementById('current-image');
        
        // ==================== 图片错误处理 ====================
        function showImageError(imgElement, filename) {
            const wrapper = document.getElementById('image-wrapper');
            wrapper.innerHTML = `
                <div class="image-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>图片加载失败</h3>
                    <p>文件名: ${filename}</p>
                    <p>可能原因：</p>
                    <ul style="text-align: left; margin-top: 10px;">
                        <li>图片路径不可访问</li>
                        <li>图片文件损坏</li>
                        <li>服务器权限问题</li>
                    </ul>
                    <button onclick="location.reload()" style="margin-top: 15px; padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-redo"></i> 重新加载
                    </button>
                </div>
            `;
        }
        
        // ==================== 键盘快捷键 ====================
        document.addEventListener('keydown', function(e) {
            // 如果焦点在textarea中，不触发快捷键
            if (e.target.tagName === 'TEXTAREA') {
                return;
            }
            
            // 根据按键执行操作
            switch(e.key.toLowerCase()) {
                case 'a':
                    if (!document.querySelector('.empty-state')) {
                        e.preventDefault();
                        if (confirmAction('通过')) {
                            document.querySelector('button[value="approve"]').click();
                        }
                    }
                    break;
                    
                case 'r':
                    if (!document.querySelector('.empty-state')) {
                        e.preventDefault();
                        if (confirmAction('拒绝')) {
                            document.querySelector('button[value="reject"]').click();
                        }
                    }
                    break;
                    
                case 's':
                    if (!document.querySelector('.empty-state')) {
                        e.preventDefault();
                        document.querySelector('button[value="skip"]').click();
                    }
                    break;
                    
                case ' ':
                    e.preventDefault();
                    location.reload();
                    break;
                    
                // 数字快捷键用于快速输入备注
                case '1':
                    if (!document.querySelector('.empty-state')) {
                        const textarea = document.querySelector('textarea[name="notes"]');
                        if (textarea) {
                            e.preventDefault();
                            textarea.value = '色情内容';
                            showNotification('已快速输入：色情内容');
                        }
                    }
                    break;
                    
                case '2':
                    if (!document.querySelector('.empty-state')) {
                        const textarea = document.querySelector('textarea[name="notes"]');
                        if (textarea) {
                            e.preventDefault();
                            textarea.value = '暴力内容';
                            showNotification('已快速输入：暴力内容');
                        }
                    }
                    break;
                    
                case '3':
                    if (!document.querySelector('.empty-state')) {
                        const textarea = document.querySelector('textarea[name="notes"]');
                        if (textarea) {
                            e.preventDefault();
                            textarea.value = '广告内容';
                            showNotification('已快速输入：广告内容');
                        }
                    }
                    break;
                    
                case '4':
                    if (!document.querySelector('.empty-state')) {
                        const textarea = document.querySelector('textarea[name="notes"]');
                        if (textarea) {
                            e.preventDefault();
                            textarea.value = '其他违规';
                            showNotification('已快速输入：其他违规');
                        }
                    }
                    break;
            }
        });
        
        // ==================== 确认操作 ====================
        function confirmAction(action) {
            let defaultMessage = '';
            
            switch(action) {
                case '通过':
                    defaultMessage = '确定通过此图片吗？';
                    break;
                case '拒绝':
                    defaultMessage = '确定拒绝此图片吗？';
                    break;
            }
            
            const notes = document.querySelector('textarea[name="notes"]')?.value.trim();
            let message = defaultMessage;
            
            if (notes) {
                message = `${defaultMessage}\n\n备注: ${notes}`;
            }
            
            return confirm(message);
        }
        
        // ==================== 显示加载动画 ====================
        function showLoader() {
            const loader = document.getElementById('loader');
            if (loader) {
                loader.classList.add('show');
                
                // 禁用所有提交按钮
                const buttons = document.querySelectorAll('button[type="submit"]');
                buttons.forEach(btn => {
                    btn.disabled = true;
                    const icon = btn.querySelector('i');
                    const text = btn.querySelector('span');
                    if (icon) {
                        icon.className = 'fas fa-spinner fa-spin';
                    }
                    if (text) {
                        text.textContent = '处理中...';
                    }
                });
            }
        }
        
        // ==================== 显示通知 ====================
        function showNotification(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `message-alert ${type === 'info' ? 'success' : type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(alertDiv);
            
            // 显示动画
            setTimeout(() => alertDiv.classList.add('show'), 100);
            
            // 3秒后自动移除
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 500);
            }, 3000);
        }
        
        // ==================== 自动刷新检查新图片 ====================
        function checkForNewImages() {
            fetch(window.location.href, { cache: 'no-store' })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newCount = doc.querySelector('.stat-card.unaudited .stat-number')?.textContent || 0;
                    const currentCount = document.querySelector('.stat-card.unaudited .stat-number')?.textContent || 0;
                    
                    if (parseInt(newCount) > parseInt(currentCount)) {
                        showNotification('检测到新图片，正在刷新页面...', 'success');
                        setTimeout(() => location.reload(), 1500);
                    }
                })
                .catch(error => console.error('检查新图片失败:', error));
        }
        
        // 每隔30秒检查一次新图片
        setInterval(checkForNewImages, <?php echo $config['auto_refresh_interval'] * 1000; ?>);
        
        // ==================== 页面加载完成后初始化 ====================
        document.addEventListener('DOMContentLoaded', function() {
            // 移除消息提示
            const messageAlert = document.getElementById('message-alert');
            if (messageAlert) {
                setTimeout(() => {
                    messageAlert.classList.remove('show');
                    setTimeout(() => {
                        if (messageAlert.parentNode) {
                            messageAlert.parentNode.removeChild(messageAlert);
                        }
                    }, 500);
                }, 3000);
            }
            
            // 自动聚焦到第一个按钮（如果存在）
            const firstBtn = document.querySelector('.action-btn');
            if (firstBtn) {
                firstBtn.focus();
            }
        });
        
        // ==================== 图片点击显示大图 ====================
        if (currentImage) {
            currentImage.addEventListener('click', function() {
                const imgUrl = this.src;
                window.open(imgUrl, '_blank');
            });
        }
    </script>
</body>
</html>
<?php
// backend/api/logs.php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$logFile = __DIR__ . '/../logs/tasks.log';

// ============ GET - 讀取 Log ============
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($logFile)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => '尚無 Log 檔案'
        ]);
        exit();
    }

    // 讀取 log 檔案
    $content = file_get_contents($logFile);
    $lines = array_filter(explode(PHP_EOL, $content));
    $lines = array_reverse($lines); // 最新的在前面
    
    // 取得查詢參數
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // 過濾
    if ($search) {
        $lines = array_filter($lines, function($line) use ($search) {
            return stripos($line, $search) !== false;
        });
    }
    
    // 限制數量
    if ($limit > 0) {
        $lines = array_slice($lines, 0, $limit);
    }
    
    echo json_encode([
        'success' => true,
        'data' => array_values($lines),
        'total' => count($lines),
        'message' => '讀取成功'
    ]);
    exit();
}

// ============ DELETE - 清空 Log ============
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
        echo json_encode([
            'success' => true,
            'message' => 'Log 已清空'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Log 檔案不存在'
        ]);
    }
    exit();
}

// 不支援的方法
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => '不支援的請求方法'
]);
?>
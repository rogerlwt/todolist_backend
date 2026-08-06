<?php
// backend/api/tasks.php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============ 設定檔案路徑 ============
$dataDir = __DIR__ . '/../data';
$dataFile = __DIR__ . '/../data/tasks.json';

if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/tasks.log';

// 確保 log 目錄存在
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// ============ Log 函數 ============
function writeLog($filePath, $message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    $logEntry = [
        'timestamp' => $timestamp,
        'ip' => $ip,
        'method' => $method,
        'uri' => $uri,
        'message' => $message,
        'data' => $data
    ];
    
    // 格式化的 log 行
    $logLine = sprintf(
        "[%s] [%s] [%s] %s - %s%s",
        $timestamp,
        $ip,
        $method,
        $uri,
        $message,
        $data ? ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) : ''
    );
    
    // 寫入檔案（附加模式）
    file_put_contents($filePath, $logLine . PHP_EOL, FILE_APPEND);
    
    return $logEntry;
}

function writeErrorLog($filePath, $error, $context = null) {
    return writeLog($filePath, 'ERROR: ' . $error, $context);
}

// ============ 工具函數 ============
function readTasks($filePath) {
    if (!file_exists($filePath)) {
        file_put_contents($filePath, json_encode(['tasks' => [], 'lastId' => 0]));
        return ['tasks' => [], 'lastId' => 0];
    }
    
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    
    if (!$data) {
        writeLog(__DIR__ . '/../logs/tasks.log', 'JSON 解析失敗', ['content' => $content]);
        return ['tasks' => [], 'lastId' => 0];
    }
    
    return $data;
}

function writeTasks($filePath, $data) {
    return file_put_contents(
        $filePath, 
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function generateId($data) {
    $data['lastId'] = ($data['lastId'] ?? 0) + 1;
    return $data['lastId'];
}

// ============ 主要邏輯 ============
$method = $_SERVER['REQUEST_METHOD'];
$data = readTasks($dataFile);

// 確保 tasks 存在
if (!isset($data['tasks'])) {
    $data['tasks'] = [];
}
if (!isset($data['lastId'])) {
    $data['lastId'] = 0;
}

// 解析請求體
$input = json_decode(file_get_contents('php://input'), true);

// 記錄請求
writeLog($logFile, '收到請求', [
    'method' => $method,
    'input' => $input
]);

// ============ 處理請求 ============
switch ($method) {
    case 'GET':
        writeLog($logFile, 'GET 請求 - 回傳所有任務', [
            'total' => count($data['tasks'])
        ]);
        
        echo json_encode([
            'success' => true,
            'data' => $data['tasks'],
            'total' => count($data['tasks'])
        ]);
        break;

    case 'POST':
        if (!$input) {
            http_response_code(400);
            $errorMsg = '無效的請求資料（無法解析 JSON）';
            writeErrorLog($logFile, $errorMsg, ['raw_input' => file_get_contents('php://input')]);
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            break;
        }

        // 批量更新（從前端傳入完整 tasks 陣列）
        if (isset($input['tasks'])) {
            $data['tasks'] = $input['tasks'];
            
            // 更新 lastId
            $maxId = 0;
            foreach ($data['tasks'] as $task) {
                if (isset($task['id']) && $task['id'] > $maxId) {
                    $maxId = $task['id'];
                }
            }
            $data['lastId'] = $maxId;
            
            writeLog($logFile, 'POST 請求 - 批量更新任務', [
                'task_count' => count($data['tasks']),
                'last_id' => $data['lastId']
            ]);
            
            if (writeTasks($dataFile, $data)) {
                echo json_encode([
                    'success' => true,
                    'message' => '任務已更新',
                    'data' => $data['tasks']
                ]);
            } else {
                http_response_code(500);
                $errorMsg = '寫入檔案失敗';
                writeErrorLog($logFile, $errorMsg, ['file' => $dataFile]);
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
            break;
        }

        // 單個任務新增
        if (isset($input['task'])) {
            $newTask = $input['task'];
            
            // 驗證必要欄位
            if (!isset($newTask['text']) || empty($newTask['text'])) {
                http_response_code(400);
                $errorMsg = '任務內容不能為空';
                writeErrorLog($logFile, $errorMsg, ['task' => $newTask]);
                echo json_encode(['success' => false, 'message' => $errorMsg]);
                break;
            }
            
            // 生成新 ID
            $newId = generateId($data);
            $newTask['id'] = $newId;
            
            // 設定預設值
            if (!isset($newTask['completed'])) {
                $newTask['completed'] = false;
            }
            if (!isset($newTask['tag'])) {
                $newTask['tag'] = '一般';
            }
            if (!isset($newTask['date'])) {
                $newTask['date'] = date('Y-m-d');
            }
            
            $data['tasks'][] = $newTask;
            
            writeLog($logFile, 'POST 請求 - 新增單個任務', [
                'task_id' => $newId,
                'task_text' => $newTask['text'],
                'tag' => $newTask['tag']
            ]);
            
            if (writeTasks($dataFile, $data)) {
                echo json_encode([
                    'success' => true,
                    'message' => '任務已新增',
                    'task' => $newTask
                ]);
            } else {
                http_response_code(500);
                $errorMsg = '寫入檔案失敗';
                writeErrorLog($logFile, $errorMsg, ['file' => $dataFile]);
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
            break;
        }

        http_response_code(400);
        $errorMsg = '無效的請求格式';
        writeErrorLog($logFile, $errorMsg, ['input' => $input]);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        break;

    case 'PUT':
        if (!$input || !isset($input['task'])) {
            http_response_code(400);
            $errorMsg = 'PUT 請求缺少 task 資料';
            writeErrorLog($logFile, $errorMsg, ['input' => $input]);
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            break;
        }

        $updatedTask = $input['task'];
        $found = false;
        
        if (!isset($updatedTask['id'])) {
            http_response_code(400);
            $errorMsg = 'PUT 請求缺少任務 ID';
            writeErrorLog($logFile, $errorMsg, ['task' => $updatedTask]);
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            break;
        }
        
        foreach ($data['tasks'] as &$task) {
            if ($task['id'] == $updatedTask['id']) {
                $task = array_merge($task, $updatedTask);
                $found = true;
                writeLog($logFile, 'PUT 請求 - 更新任務', [
                    'task_id' => $updatedTask['id'],
                    'updates' => $updatedTask
                ]);
                break;
            }
        }
        
        if ($found) {
            if (writeTasks($dataFile, $data)) {
                echo json_encode([
                    'success' => true,
                    'message' => '任務已更新',
                    'task' => $updatedTask
                ]);
            } else {
                http_response_code(500);
                $errorMsg = '寫入檔案失敗';
                writeErrorLog($logFile, $errorMsg, ['file' => $dataFile]);
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
        } else {
            http_response_code(404);
            $errorMsg = "找不到 ID {$updatedTask['id']} 的任務";
            writeErrorLog($logFile, $errorMsg, ['task_id' => $updatedTask['id']]);
            echo json_encode(['success' => false, 'message' => $errorMsg]);
        }
        break;

    case 'DELETE':
        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            $errorMsg = 'DELETE 請求缺少任務 ID';
            writeErrorLog($logFile, $errorMsg, ['input' => $input]);
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            break;
        }

        $deleteId = $input['id'];
        $found = false;
        
        foreach ($data['tasks'] as $key => $task) {
            if ($task['id'] == $deleteId) {
                unset($data['tasks'][$key]);
                $found = true;
                writeLog($logFile, 'DELETE 請求 - 刪除任務', [
                    'task_id' => $deleteId,
                    'task_text' => $task['text'] ?? 'unknown'
                ]);
                break;
            }
        }
        
        if ($found) {
            $data['tasks'] = array_values($data['tasks']);
            
            if (writeTasks($dataFile, $data)) {
                echo json_encode([
                    'success' => true,
                    'message' => '任務已刪除'
                ]);
            } else {
                http_response_code(500);
                $errorMsg = '寫入檔案失敗';
                writeErrorLog($logFile, $errorMsg, ['file' => $dataFile]);
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
        } else {
            http_response_code(404);
            $errorMsg = "找不到 ID {$deleteId} 的任務";
            writeErrorLog($logFile, $errorMsg, ['task_id' => $deleteId]);
            echo json_encode(['success' => false, 'message' => $errorMsg]);
        }
        break;

    default:
        http_response_code(405);
        $errorMsg = "不支援的請求方法: {$method}";
        writeErrorLog($logFile, $errorMsg);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        break;
}
?>
<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============ Set File Path ============
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

// ============ Log Functions ============
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
    
    $logLine = sprintf(
        "[%s] [%s] [%s] %s - %s%s",
        $timestamp,
        $ip,
        $method,
        $uri,
        $message,
        $data ? ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) : ''
    );
    
    file_put_contents($filePath, $logLine . PHP_EOL, FILE_APPEND);
    
    return $logEntry;
}

function writeErrorLog($filePath, $error, $context = null) {
    return writeLog($filePath, 'ERROR: ' . $error, $context);
}

// ============ Tasks Functions ============
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

// ============ Main Function ============
$method = $_SERVER['REQUEST_METHOD'];
$data = readTasks($dataFile);

if (!isset($data['tasks'])) {
    $data['tasks'] = [];
}
if (!isset($data['lastId'])) {
    $data['lastId'] = 0;
}

$input = json_decode(file_get_contents('php://input'), true);

writeLog($logFile, '收到請求', [
    'method' => $method,
    'input' => $input
]);

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

        if (isset($input['tasks'])) {
            $data['tasks'] = $input['tasks'];
            
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

        if (isset($input['task'])) {
            $newTask = $input['task'];
            
            if (!isset($newTask['text']) || empty($newTask['text'])) {
                http_response_code(400);
                $errorMsg = '任務內容不能為空';
                writeErrorLog($logFile, $errorMsg, ['task' => $newTask]);
                echo json_encode(['success' => false, 'message' => $errorMsg]);
                break;
            }
            
            $newId = generateId($data);
            $newTask['id'] = $newId;
            
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

    default:
        http_response_code(405);
        $errorMsg = "不支援的請求方法: {$method}";
        writeErrorLog($logFile, $errorMsg);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        break;
}
?>
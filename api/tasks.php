<?php
// backend/api/tasks.php

// 設定 CORS 允許跨域請求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// 處理 OPTIONS 請求（預檢請求）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 資料檔案路徑
$dataFile = __DIR__ . '/../data/tasks.json';

// ============ 工具函數 ============

// 讀取資料
function readTasks($filePath) {
    if (!file_exists($filePath)) {
        // 如果檔案不存在，創建空資料
        file_put_contents($filePath, json_encode(['tasks' => [], 'lastId' => 0]));
        return ['tasks' => [], 'lastId' => 0];
    }
    
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    
    if (!$data) {
        return ['tasks' => [], 'lastId' => 0];
    }
    
    return $data;
}

// 寫入資料
function writeTasks($filePath, $data) {
    return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 生成新 ID
function generateId($data) {
    $data['lastId'] = ($data['lastId'] ?? 0) + 1;
    return $data['lastId'];
}

// ============ 處理請求 ============

$method = $_SERVER['REQUEST_METHOD'];
$data = readTasks($dataFile);

// 確保 tasks 存在
if (!isset($data['tasks'])) {
    $data['tasks'] = [];
}
if (!isset($data['lastId'])) {
    $data['lastId'] = 0;
}

// 解析請求體（針對 POST, PUT, DELETE）
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        // 取得所有任務
        echo json_encode([
            'success' => true,
            'data' => $data['tasks'],
            'total' => count($data['tasks'])
        ]);
        break;

    case 'POST':
        // 新增或更新任務
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '無效的請求資料']);
            break;
        }

        // 如果是從前端來的完整 tasks 陣列（批量更新）
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
            
            if (writeTasks($dataFile, $data)) {
                echo json_encode([
                    'success' => true,
                    'message' => '任務已更新',
                    'data' => $data['tasks']
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => '儲存失敗']);
            }
            break;
        }

        // 單個任務新增
        if (isset($input['task'])) {
            $newTask = $input['task'];
            // 確保必要欄位存在
            if (!isset($newTask['text']) || empty($newTask['text'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => '任務內容不能為空']);
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
            
            if (writeTasks($dataFile, $data)) {
                echo json_encode([
                    'success' => true,
                    'message' => '任務已新增',
                    'task' => $newTask
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => '儲存失敗']);
            }
            break;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '無效的請求資料']);
        break;

    case 'PUT':
        // 更新特定任務
        if (!$input || !isset($input['task'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '無效的請求資料']);
            break;
        }

        $updatedTask = $input['task'];
        $found = false;
        
        foreach ($data['tasks'] as &$task) {
            if ($task['id'] == $updatedTask['id']) {
                $task = array_merge($task, $updatedTask);
                $found = true;
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
                echo json_encode(['success' => false, 'message' => '儲存失敗']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '找不到該任務']);
        }
        break;

    case 'DELETE':
        // 刪除特定任務
        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '請提供任務 ID']);
            break;
        }

        $deleteId = $input['id'];
        $found = false;
        
        foreach ($data['tasks'] as $key => $task) {
            if ($task['id'] == $deleteId) {
                unset($data['tasks'][$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            // 重新索引陣列
            $data['tasks'] = array_values($data['tasks']);
            
            if (writeTasks($dataFile, $data)) {
                echo json_encode([
                    'success' => true,
                    'message' => '任務已刪除'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => '儲存失敗']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '找不到該任務']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '不支援的請求方法']);
        break;
}
?>
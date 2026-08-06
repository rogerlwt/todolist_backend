<?php

header('Content-Type: application/json');

$logFile = __DIR__ . '/tasks.log';

if (file_exists($logFile)) {
    file_put_contents($logFile, '');
    echo json_encode(['success' => true, 'message' => 'Log 已清空']);
} else {
    echo json_encode(['success' => false, 'message' => 'Log 檔案不存在']);
}
?>
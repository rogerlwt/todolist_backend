<?php
// backend/logs/view.php

header('Content-Type: text/html; charset=utf-8');

$logFile = __DIR__ . '/tasks.log';

if (!file_exists($logFile)) {
    echo "<h1>尚無 Log 檔案</h1>";
    exit();
}

$content = file_get_contents($logFile);
$lines = array_reverse(explode(PHP_EOL, $content));

// 過濾空行
$lines = array_filter($lines, function($line) {
    return trim($line) !== '';
});

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Server Log Viewer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e2e;
            color: #cdd6f4;
            padding: 20px;
        }
        h1 {
            color: #89b4fa;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .controls button {
            padding: 8px 16px;
            background: #313244;
            color: #cdd6f4;
            border: 1px solid #45475a;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .controls button:hover {
            background: #45475a;
        }
        .controls .count {
            color: #a6e3a1;
            font-size: 14px;
        }
        .log-container {
            background: #181825;
            border-radius: 8px;
            padding: 16px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .log-entry {
            padding: 4px 8px;
            border-bottom: 1px solid #313244;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .log-entry:hover {
            background: #313244;
        }
        .log-entry .timestamp {
            color: #89b4fa;
        }
        .log-entry .level-info {
            color: #a6e3a1;
        }
        .log-entry .level-error {
            color: #f38ba8;
            font-weight: bold;
        }
        .log-entry .ip {
            color: #f9e2af;
        }
        .log-entry .method {
            color: #cba6f7;
            font-weight: bold;
        }
        .log-entry .uri {
            color: #89b4fa;
        }
        .log-entry .data {
            color: #94e2d5;
        }
        .search-box {
            flex: 1;
            padding: 8px 12px;
            background: #313244;
            border: 1px solid #45475a;
            border-radius: 6px;
            color: #cdd6f4;
            font-size: 14px;
            font-family: 'Courier New', monospace;
        }
        .search-box:focus {
            outline: none;
            border-color: #89b4fa;
        }
    </style>
</head>
<body>
    <h1>📋 Server Log Viewer</h1>
    
    <div class="controls">
        <button onclick="window.location.reload()">🔄 重新整理</button>
        <button onclick="clearLog()">🗑️ 清空 Log</button>
        <span class="count">共 <?= count($lines) ?> 行</span>
        <input type="text" class="search-box" id="searchBox" placeholder="搜尋 Log..." onkeyup="filterLogs()">
    </div>

    <div class="log-container" id="logContainer">
        <?php foreach ($lines as $line): ?>
            <div class="log-entry">
                <?= htmlspecialchars($line) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function filterLogs() {
            const search = document.getElementById('searchBox').value.toLowerCase();
            const entries = document.querySelectorAll('.log-entry');
            entries.forEach(entry => {
                const text = entry.textContent.toLowerCase();
                entry.style.display = text.includes(search) ? 'block' : 'none';
            });
        }

        function clearLog() {
            if (confirm('確定要清空所有 Log 嗎？')) {
                fetch('clear.php', { method: 'POST' })
                    .then(() => window.location.reload());
            }
        }
    </script>
</body>
</html>
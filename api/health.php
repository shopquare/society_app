<?php
// api/health.php
// Production System Health & Monitoring Check

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$startTime = microtime(true);
$checks = [];
$isHealthy = true;

// 1. PHP & Environment Check
$checks['environment'] = [
    'php_version' => PHP_VERSION,
    'app_version' => '1.0.4',
    'timezone' => date_default_timezone_get(),
    'timestamp' => date('Y-m-d H:i:s T')
];

// 2. Database Connectivity & Table Check
try {
    require_once dirname(__DIR__) . '/config/db.php';
    $dbStart = microtime(true);
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);

    $checks['database'] = [
        'status' => 'connected',
        'latency_ms' => $dbLatency,
        'tables_count' => count($tables),
        'initialized' => isDatabaseInitialized($conn)
    ];
} catch (Throwable $e) {
    $isHealthy = false;
    $checks['database'] = [
        'status' => 'disconnected',
        'error' => $e->getMessage()
    ];
}

// 3. Writable Storage Check
$uploadsDir = dirname(__DIR__) . '/uploads';
$storageWritable = is_dir($uploadsDir) && is_writable($uploadsDir);
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
    $storageWritable = is_dir($uploadsDir) && is_writable($uploadsDir);
}
$checks['storage'] = [
    'uploads_writable' => $storageWritable,
    'uploads_path' => '/uploads'
];

// 4. Memory Usage Check
$checks['memory'] = [
    'usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
    'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
];

$totalLatency = round((microtime(true) - $startTime) * 1000, 2);

http_response_code($isHealthy ? 200 : 503);
echo json_encode([
    'status' => $isHealthy ? 'healthy' : 'degraded',
    'total_latency_ms' => $totalLatency,
    'checks' => $checks
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

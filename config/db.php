<?php
// config/db.php

require_once __DIR__ . '/env.php';
loadEnvFile();

// 1. Force PHP timezone (default: Asia/Kolkata)
date_default_timezone_set(envValue('APP_TIMEZONE', 'Asia/Kolkata') ?? 'Asia/Kolkata');

// Define database constants from environment
define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_USER', envValue('DB_USER', ''));
define('DB_PASS', envValue('DB_PASS', ''));
define('DB_NAME', envValue('DB_NAME', ''));

try {
    $conn = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );

    // 2. Force MySQL session timezone to IST (+05:30)
    $conn->exec("SET time_zone = '+05:30'");

    // 3. Automated migration check (auto-creates tables & seeds if DB is fresh)
    require_once dirname(__DIR__) . '/includes/db_migrator.php';
    ensureDatabaseMigrated($conn);
} catch (PDOException $exception) {
    error_log('Database Connection Failed: ' . $exception->getMessage());
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    die(json_encode([
        'status' => 'error',
        'message' => 'Database service is temporarily unavailable.'
    ]));
}

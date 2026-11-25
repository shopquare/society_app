<?php
// api/migrate.php
// JSON Endpoint to trigger or verify database migrations

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/db_migrator.php';

header('Content-Type: application/json; charset=UTF-8');

$result = runDatabaseMigration($conn);

$users = [];
$societies = [];

try {
    $users = $conn->query("SELECT u.id, u.email, u.role_id, u.status, p.first_name, p.last_name FROM sms_users u LEFT JOIN sms_user_profiles p ON u.id = p.user_id ORDER BY u.role_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $societies = $conn->query("SELECT id, society_name, society_code, city FROM sms_societies")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // ignore
}

echo json_encode([
    'status' => $result['success'] ? 'success' : 'error',
    'message' => $result['message'],
    'database' => defined('DB_NAME') ? DB_NAME : '',
    'tables_count' => count($result['tables']),
    'tables' => $result['tables'],
    'active_society' => $societies[0] ?? null,
    'demo_users' => array_map(function($u) {
        return [
            'role_id' => $u['role_id'],
            'name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
            'email' => $u['email'],
            'password' => 'password'
        ];
    }, $users)
], JSON_PRETTY_PRINT);

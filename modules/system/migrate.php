<?php
// modules/system/migrate.php
// Visual Database Migration & Diagnostic Console

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/db_migrator.php';

$migrationResult = runDatabaseMigration($conn);
$tables = [];
$users = [];
$societies = [];

try {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $users = $conn->query("SELECT u.id, u.email, u.role_id, u.status, p.first_name, p.last_name FROM sms_users u LEFT JOIN sms_user_profiles p ON u.id = p.user_id ORDER BY u.role_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $societies = $conn->query("SELECT id, society_name, society_code, city FROM sms_societies")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$rolesMap = [
    1 => 'Super Admin',
    2 => 'Committee',
    3 => 'Resident',
    4 => 'Security Guard'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Migration — SocietyHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">
    <div class="max-w-4xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-200 mb-4">
                <i class="fa-solid fa-database text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Production Database Setup</h1>
            <p class="text-slate-500 mt-2">Automated schema creation and seed verification</p>
        </div>

        <!-- Status Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
            <div class="flex items-center justify-between pb-6 border-b border-slate-100">
                <div class="flex items-center space-x-3">
                    <span class="flex h-3 w-3 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Database Status: Ready</h2>
                        <p class="text-sm text-slate-500">Connected to <code class="bg-slate-100 px-2 py-0.5 rounded text-indigo-600 font-mono text-xs"><?= htmlspecialchars(defined('DB_NAME') ? DB_NAME : 'database') ?></code> on <code class="bg-slate-100 px-2 py-0.5 rounded text-indigo-600 font-mono text-xs"><?= htmlspecialchars(defined('DB_HOST') ? DB_HOST : 'localhost') ?></code></p>
                    </div>
                </div>
                <a href="<?= appBasePath() ?>/login" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-xl shadow-sm transition">
                    Go to Login <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                </a>
            </div>

            <!-- Society Banner -->
            <?php if (!empty($societies)): ?>
            <div class="mt-6 bg-indigo-50/60 rounded-xl p-4 border border-indigo-100 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Active Society</span>
                    <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($societies[0]['society_name']) ?></h3>
                    <p class="text-xs text-slate-500"><?= htmlspecialchars($societies[0]['city']) ?></p>
                </div>
                <div class="bg-white px-4 py-2 rounded-lg border border-indigo-200 text-center">
                    <span class="text-xs text-slate-500 block">Registration Code</span>
                    <span class="font-mono font-bold text-indigo-700 text-sm tracking-widest"><?= htmlspecialchars($societies[0]['society_code']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tables & Demo Users Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Tables Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-900 flex items-center">
                        <i class="fa-solid fa-table-cells text-indigo-600 mr-2"></i> Created Tables
                    </h3>
                    <span class="text-xs font-semibold bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full"><?= count($tables) ?> Tables Active</span>
                </div>
                <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                    <?php foreach ($tables as $t): ?>
                    <div class="flex items-center justify-between py-2 px-3 bg-slate-50 rounded-lg text-sm border border-slate-100">
                        <span class="font-mono text-xs text-slate-700"><?= htmlspecialchars($t) ?></span>
                        <i class="fa-solid fa-check text-emerald-500 text-xs"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Demo Users Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-900 flex items-center">
                        <i class="fa-solid fa-users text-indigo-600 mr-2"></i> Demo Accounts
                    </h3>
                    <span class="text-xs font-medium text-slate-500">Password for all: <code class="bg-slate-100 px-1 py-0.5 rounded font-mono font-bold text-slate-800">password</code></span>
                </div>
                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    <?php foreach ($users as $u): ?>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-slate-900"><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'User') ?></span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $u['role_id'] == 1 ? 'bg-purple-100 text-purple-700' : ($u['role_id'] == 2 ? 'bg-blue-100 text-blue-700' : ($u['role_id'] == 4 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700')) ?>">
                                <?= htmlspecialchars($rolesMap[$u['role_id']] ?? 'Role ' . $u['role_id']) ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span class="font-mono"><?= htmlspecialchars($u['email']) ?></span>
                            <span class="text-emerald-600 font-medium capitalize"><?= htmlspecialchars($u['status']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Footer Action -->
        <div class="mt-8 text-center">
            <a href="<?= appBasePath() ?>/login" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-base shadow-lg shadow-indigo-200 transition">
                <i class="fa-solid fa-right-to-bracket mr-2"></i> Launch Application Login
            </a>
        </div>
    </div>
</body>
</html>

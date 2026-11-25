<?php
// /society_app/routes.php

$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$base_path = appBasePath();

// Remove query string first, then strip base path prefix cleanly
$path = strtok($request_uri, '?') ?: '/';
if ($base_path !== '' && str_starts_with($path, $base_path)) {
    $path = substr($path, strlen($base_path));
}
$path = '/' . trim($path, '/');

// Direct APK downloads (case-insensitive)
if (preg_match('#^/(apk(/.*)?|download/apk)$#i', $path)) {
    $apkFile = __DIR__ . '/apk/SocietyHub.apk';
    if (is_file($apkFile)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="SocietyHub.apk"');
        header('Content-Length: ' . filesize($apkFile));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($apkFile);
        exit;
    }
    header('Location: https://github.com/shopquare/society_app/releases/latest', true, 302);
    exit;
}

switch ($path) {
    // --- AUTHENTICATION & DOWNLOAD ROUTES ---
    case '/':
    case '/login':
        require __DIR__ . '/modules/auth/login.php';
        break;

    case '/download':
    case '/download/':
        require __DIR__ . '/download.php';
        break;

    case '/register':
        require __DIR__ . '/modules/auth/register.php';
        break;

    case '/migrate':
        require __DIR__ . '/modules/system/migrate.php';
        break;

    // --- RESIDENT ROUTES (Role 3) ---
    case '/resident/dashboard':
        requireRole(3);
        require __DIR__ . '/modules/resident/dashboard.php';
        break;

    case '/resident/financials':
        requireRole(3);
        require __DIR__ . '/modules/resident/financials.php';
        break;

    // --- ADMIN ROUTES (Roles 1 & 2) ---
    case '/admin/dashboard':
        requireAdmin();
        require __DIR__ . '/modules/admin/dashboard.php';
        break;

    case '/admin/users':
        requireAdmin();
        require __DIR__ . '/modules/admin/users.php';
        break;

    case '/admin/financials':
        requireAdmin();
        require __DIR__ . '/modules/admin/financials.php';
        break;

    case '/admin/notices':
        requireAdmin();
        require __DIR__ . '/modules/admin/notices.php';
        break;

    case '/admin/guards':
        requireAdmin();
        require __DIR__ . '/modules/admin/guards.php';
        break;

    case '/admin/complaints':
        requireAdmin();
        require __DIR__ . '/modules/admin/complaints.php';
        break;

    case '/admin/settings':
        requireAdmin();
        require __DIR__ . '/modules/admin/settings.php';
        break;

    // --- GUARD ROUTES (Role 4) ---
    case '/guard/dashboard':
        requireRole(4);
        require __DIR__ . '/modules/guard/dashboard.php';
        break;

    // --- 404 FALLBACK ---
    default:
        http_response_code(404);
        echo "<!DOCTYPE html><html lang='en'><body style='font-family:sans-serif;text-align:center;margin-top:60px;'><h1>404 - Page Not Found</h1><p><a href='" . htmlspecialchars($base_path . '/login', ENT_QUOTES, 'UTF-8') . "'>Return to Login</a></p></body></html>";
        break;
}

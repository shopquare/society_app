<?php
// /society_app/includes/auth_middleware.php

require_once __DIR__ . '/../config/env.php';

/**
 * Starts a hardened PHP session if not already active.
 */
function startSecureSession(int $lifetime = 0): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => appBasePath() ?: '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Returns the canonical dashboard path for a given role ID.
 */
function getRoleDashboardPath($roleId): string {
    $base = appBasePath();
    switch ((int)$roleId) {
        case 1:
        case 2:
            return $base . '/admin/dashboard';
        case 4:
            return $base . '/guard/dashboard';
        case 3:
        default:
            return $base . '/resident/dashboard';
    }
}

/**
 * Enforces that a user must be logged in to view the page.
 */
function requireLogin(): void {
    startSecureSession();
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: ' . appBasePath() . '/login');
        exit();
    }
}

/**
 * Checks if the current user has one of the required role IDs.
 */
function hasRole($requiredRoles): bool {
    if (!isset($_SESSION['role_id'])) {
        return false;
    }
    $currentRole = (int)$_SESSION['role_id'];
    if (is_array($requiredRoles)) {
        $allowed = array_map('intval', $requiredRoles);
        return in_array($currentRole, $allowed, true);
    }
    return $currentRole === (int)$requiredRoles;
}

/**
 * Enforces that the logged-in user holds one of the specified role IDs.
 */
function requireRole($requiredRoles): void {
    requireLogin();
    if (!hasRole($requiredRoles)) {
        header('Location: ' . getRoleDashboardPath($_SESSION['role_id'] ?? 0));
        exit();
    }
}

/**
 * Enforces Admin/Committee privileges (Role IDs 1 & 2).
 */
function requireAdmin(): void {
    requireRole([1, 2]);
}

/**
 * Clears session data and cookie, then redirects to login.
 */
function logoutUser(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . appBasePath() . '/login');
    exit();
}

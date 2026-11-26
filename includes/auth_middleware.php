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
        if (!empty($_COOKIE['society_remember'])) {
            if (attemptPersistentLogin((string)$_COOKIE['society_remember'])) {
                return;
            }
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: ' . appBasePath() . '/login');
        exit();
    }
}

/**
 * Issues a cryptographically secure 30-day persistent remember-me token.
 */
function issuePersistentToken(PDO $conn, int $userId): void {
    try {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $expiresAt = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

        $stmt = $conn->prepare(
            "INSERT INTO sms_persistent_tokens (user_id, token_hash, user_agent, expires_at)
             VALUES (:user_id, :token_hash, :user_agent, :expires_at)"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':user_agent' => $userAgent,
            ':expires_at' => $expiresAt
        ]);

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        setcookie(
            'society_remember',
            $rawToken,
            [
                'expires' => time() + (30 * 86400),
                'path' => appBasePath() ?: '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    } catch (Throwable $e) {
        error_log('issuePersistentToken error: ' . $e->getMessage());
    }
}

/**
 * Attempts to authenticate a user using their persistent remember-me cookie.
 */
function attemptPersistentLogin(string $rawToken): bool {
    global $conn;
    if (!$conn || strlen($rawToken) < 32) {
        return false;
    }

    try {
        $tokenHash = hash('sha256', $rawToken);
        $now = (new DateTime())->format('Y-m-d H:i:s');

        $stmt = $conn->prepare(
            "SELECT t.id, t.user_id, u.society_id, u.role_id, u.status, p.first_name, p.last_name, uu.unit_number
             FROM sms_persistent_tokens t
             INNER JOIN sms_users u ON t.user_id = u.id
             LEFT JOIN sms_user_profiles p ON u.id = p.user_id
             LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
             WHERE t.token_hash = :hash AND t.expires_at > :now AND u.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([':hash' => $tokenHash, ':now' => $now]);
        $user = $stmt->fetch();

        if ($user) {
            startSecureSession();
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['society_id'] = (int)$user['society_id'];
            $_SESSION['role_id'] = (int)$user['role_id'];
            $_SESSION['first_name'] = $user['first_name'] ?? 'Resident';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            $_SESSION['unit_number'] = $user['unit_number'] ?? '101';
            $_SESSION['created_at'] = time();

            // Refresh token expiry for another 30 days
            $newExpiry = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
            $upd = $conn->prepare("UPDATE sms_persistent_tokens SET expires_at = :exp WHERE id = :id");
            $upd->execute([':exp' => $newExpiry, ':id' => $user['id']]);

            if (function_exists('loadUserPermissionContext')) {
                loadUserPermissionContext($conn, (int)$user['user_id'], (int)$user['society_id'], (int)$user['role_id']);
            }

            return true;
        }
    } catch (Throwable $e) {
        error_log('attemptPersistentLogin error: ' . $e->getMessage());
    }

    return false;
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
    if (!empty($_COOKIE['society_remember'])) {
        global $conn;
        if ($conn) {
            try {
                $hash = hash('sha256', (string)$_COOKIE['society_remember']);
                $del = $conn->prepare("DELETE FROM sms_persistent_tokens WHERE token_hash = :hash");
                $del->execute([':hash' => $hash]);
            } catch (Throwable $_) {}
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        setcookie('society_remember', '', time() - 86400, appBasePath() ?: '/', '', $isHttps, true);
    }
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

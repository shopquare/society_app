<?php
// includes/permissions.php
// Granular Role-Based Access Control (RBAC) with anti-escalation & society isolation.

require_once __DIR__ . '/auth_middleware.php';

/**
 * Loads granular permission keys for a given user from DB (cached safely in session).
 */
function loadUserPermissionContext(PDO $conn, int $userId, int $societyId, int $roleId): array {
    // Super Admin always holds platform/society wildcard
    if ($roleId === 1) {
        $isPlatform = ($userId === 1);
        try {
            $uChk = $conn->query("SHOW COLUMNS FROM sms_users LIKE 'is_platform_admin'")->fetch();
            if ($uChk) {
                $st = $conn->prepare("SELECT is_platform_admin FROM sms_users WHERE id = :uid LIMIT 1");
                $st->execute([':uid' => $userId]);
                $isPlatform = ((int)$st->fetchColumn() === 1) || ($userId === 1);
            }
        } catch (Throwable $_) {}

        $perms = ['*'];
        $_SESSION['permissions'] = $perms;
        $_SESSION['custom_role_name'] = $isPlatform ? 'Platform Super Admin' : 'Super Admin';
        $_SESSION['is_platform_admin'] = $isPlatform;
        return $perms;
    }

    // Check if user has an explicit custom/system admin role assignment in sms_user_admin_roles
    if ($roleId === 2) {
        try {
            $stmt = $conn->prepare(
                "SELECT ar.id AS admin_role_id, ar.role_name, ar.role_slug, ar.is_active
                 FROM sms_user_admin_roles uar
                 INNER JOIN sms_admin_roles ar ON uar.admin_role_id = ar.id
                 WHERE uar.user_id = :uid AND uar.society_id = :sid
                 LIMIT 1"
            );
            $stmt->execute([':uid' => $userId, ':sid' => $societyId]);
            $assigned = $stmt->fetch();

            if ($assigned) {
                if ((int)$assigned['is_active'] !== 1) {
                    $_SESSION['permissions'] = [];
                    $_SESSION['custom_role_name'] = $assigned['role_name'] . ' (Disabled)';
                    $_SESSION['is_platform_admin'] = false;
                    return [];
                }
                $pStmt = $conn->prepare(
                    "SELECT p.perm_key
                     FROM sms_role_permissions rp
                     INNER JOIN sms_permissions p ON rp.permission_id = p.id
                     WHERE rp.admin_role_id = :rid"
                );
                $pStmt->execute([':rid' => (int)$assigned['admin_role_id']]);
                $perms = $pStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $_SESSION['permissions'] = $perms;
                $_SESSION['custom_role_name'] = $assigned['role_name'];
                $_SESSION['is_platform_admin'] = false;
                return $perms;
            }

            // Fallback for unassigned legacy committee accounts: Society Chairman defaults
            $pStmt = $conn->query(
                "SELECT p.perm_key FROM sms_role_permissions rp
                 INNER JOIN sms_permissions p ON rp.permission_id = p.id
                 WHERE rp.admin_role_id = 2"
            );
            $perms = $pStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $_SESSION['permissions'] = $perms;
            $_SESSION['custom_role_name'] = 'Committee Member';
            $_SESSION['is_platform_admin'] = false;
            return $perms;
        } catch (Throwable $e) {
            error_log('loadUserPermissionContext error: ' . $e->getMessage());
        }
    }

    // Resident (3) and Guard (4) capability tokens
    $defaults = [];
    if ($roleId === 3) {
        $defaults = ['community.participate', 'facilities.book', 'finance.view_own', 'services.view', 'documents.request'];
        $_SESSION['custom_role_name'] = 'Resident';
    } elseif ($roleId === 4) {
        $defaults = ['gate.operate', 'handovers.guard', 'incidents.guard', 'lost_found.report'];
        $_SESSION['custom_role_name'] = 'Security Guard';
    }
    $_SESSION['permissions'] = $defaults;
    $_SESSION['is_platform_admin'] = false;
    return $defaults;
}

/**
 * Checks whether the current authenticated user holds a specific permission key.
 * Automatically resolves/refreshes from DB if session cache is empty.
 */
function hasPermission(string $permissionKey): bool {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    $roleId = (int)($_SESSION['role_id'] ?? 0);
    if ($roleId === 1 && $permissionKey !== 'saas.platform') {
        return true;
    }

    if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
        global $conn;
        if ($conn instanceof PDO && !empty($_SESSION['user_id']) && !empty($_SESSION['society_id'])) {
            loadUserPermissionContext($conn, (int)$_SESSION['user_id'], (int)$_SESSION['society_id'], $roleId);
        } else {
            return false;
        }
    }

    $perms = $_SESSION['permissions'] ?? [];
    if (in_array('*', $perms, true)) {
        if ($permissionKey === 'saas.platform') {
            return isPlatformSuperAdmin();
        }
        return true;
    }

    return in_array($permissionKey, $perms, true);
}

/**
 * Checks whether user holds at least one of the listed permission keys.
 */
function hasAnyPermission(array $permissionKeys): bool {
    foreach ($permissionKeys as $key) {
        if (hasPermission((string)$key)) {
            return true;
        }
    }
    return false;
}

/**
 * Checks if current session is a Platform Super Admin (for SaaS administration).
 */
function isPlatformSuperAdmin(): bool {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
        return false;
    }
    if (isset($_SESSION['is_platform_admin'])) {
        return (bool)$_SESSION['is_platform_admin'];
    }
    return (int)($_SESSION['user_id'] ?? 0) === 1;
}

/**
 * Enforces permission on HTML web pages (redirects cleanly to dashboard if unauthorized).
 */
function requirePermission(string $permissionKey): void {
    requireLogin();
    if (!hasPermission($permissionKey)) {
        header('Location: ' . getRoleDashboardPath($_SESSION['role_id'] ?? 0));
        exit();
    }
}

/**
 * Enforces permission on JSON API endpoints (returns HTTP 403).
 */
function requireApiPermission(string $permissionKey): void {
    requireApiAuth([1, 2]);
    if (!hasPermission($permissionKey)) {
        jsonError("Forbidden: missing permission '{$permissionKey}'.", 403);
    }
}

/**
 * Prevents privilege escalation: checks whether actor is allowed to grant every permission in $permKeys.
 */
function canActorGrantPermissions(array $permKeys): bool {
    if (isPlatformSuperAdmin()) {
        return true;
    }
    if ((int)($_SESSION['role_id'] ?? 0) === 1) {
        return !in_array('saas.platform', $permKeys, true);
    }
    if (!hasPermission('roles.manage')) {
        return false;
    }
    foreach ($permKeys as $k) {
        if ($k === 'saas.platform' || !hasPermission((string)$k)) {
            return false;
        }
    }
    return true;
}

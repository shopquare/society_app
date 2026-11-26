<?php
// api/admin_manage_roles.php
// Granular RBAC Roles, Permissions & Multi-Admin Assignment API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);
requireApiPermission('roles.manage');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];
$actorRoleId = (int)$_SESSION['role_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 1. Permission catalog
        $permissions = $conn->query("SELECT id, perm_key, module_name, description FROM sms_permissions ORDER BY module_name ASC, id ASC")->fetchAll();

        // 2. Society + system template roles
        $rStmt = $conn->prepare(
            "SELECT ar.*,
                    (SELECT COUNT(*) FROM sms_role_permissions rp WHERE rp.admin_role_id = ar.id) AS perm_count,
                    (SELECT COUNT(*) FROM sms_user_admin_roles uar WHERE uar.admin_role_id = ar.id AND uar.society_id = :sid_cnt) AS user_count
             FROM sms_admin_roles ar
             WHERE ar.society_id = :sid OR ar.society_id IS NULL
             ORDER BY ar.is_system DESC, ar.id ASC"
        );
        $rStmt->execute([':sid' => $societyId, ':sid_cnt' => $societyId]);
        $roles = $rStmt->fetchAll();

        // Attach permission keys per role
        $rpStmt = $conn->query(
            "SELECT rp.admin_role_id, p.perm_key
             FROM sms_role_permissions rp
             INNER JOIN sms_permissions p ON rp.permission_id = p.id"
        );
        $rolePermMap = [];
        foreach ($rpStmt->fetchAll() as $row) {
            $rolePermMap[(int)$row['admin_role_id']][] = $row['perm_key'];
        }
        foreach ($roles as &$r) {
            $r['permissions'] = $rolePermMap[(int)$r['id']] ?? [];
        }
        unset($r);

        // 3. Society admin & committee accounts
        $aStmt = $conn->prepare(
            "SELECT u.id, u.email, u.role_id, u.status, u.last_login,
                    p.first_name, p.last_name, p.phone_number,
                    uar.admin_role_id, ar.role_name AS assigned_role_name
             FROM sms_users u
             INNER JOIN sms_user_profiles p ON u.id = p.user_id
             LEFT JOIN sms_user_admin_roles uar ON u.id = uar.user_id AND uar.society_id = u.society_id
             LEFT JOIN sms_admin_roles ar ON uar.admin_role_id = ar.id
             WHERE u.society_id = :sid AND u.role_id IN (1, 2)
             ORDER BY u.role_id ASC, u.id ASC"
        );
        $aStmt->execute([':sid' => $societyId]);
        $admins = $aStmt->fetchAll();

        jsonSuccess([
            'permissions' => $permissions,
            'roles' => $roles,
            'admins' => $admins,
            'current_permissions' => $_SESSION['permissions'] ?? []
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load role management configuration.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'create_role' || $action === 'update_role') {
            $roleId = (int)($data['role_id'] ?? 0);
            $roleName = trim((string)($data['role_name'] ?? ''));
            $description = trim((string)($data['description'] ?? ''));
            $permKeys = is_array($data['permissions'] ?? null) ? array_values(array_unique(array_map('strval', $data['permissions']))) : [];

            if ($roleName === '') {
                jsonError('Role name is required.', 400);
            }
            if (!canActorGrantPermissions($permKeys)) {
                jsonError('Privilege escalation blocked: cannot assign permissions above your own authority.', 403);
            }

            $conn->beginTransaction();

            if ($action === 'create_role') {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($roleName)));
                $slug = trim($slug, '_') ?: ('custom_role_' . time());

                $ins = $conn->prepare(
                    "INSERT INTO sms_admin_roles (society_id, role_slug, role_name, base_role_id, is_system, is_active, description)
                     VALUES (:sid, :slug, :name, 2, 0, 1, :desc)"
                );
                $ins->execute([
                    ':sid' => $societyId, ':slug' => $slug . '_' . substr(md5((string)microtime(true)), 0, 4),
                    ':name' => $roleName, ':desc' => $description
                ]);
                $roleId = (int)$conn->lastInsertId();
            } else {
                if ($roleId <= 0) {
                    $conn->rollBack();
                    jsonError('Valid role ID is required.', 400);
                }
                $chk = $conn->prepare("SELECT id, society_id, is_system, role_slug FROM sms_admin_roles WHERE id = :id LIMIT 1");
                $chk->execute([':id' => $roleId]);
                $existingRole = $chk->fetch();
                if (!$existingRole) {
                    $conn->rollBack();
                    jsonError('Role not found.', 404);
                }
                if ($existingRole['role_slug'] === 'super_admin' || ($existingRole['society_id'] !== null && (int)$existingRole['society_id'] !== $societyId)) {
                    $conn->rollBack();
                    jsonError('Unauthorized role modification blocked.', 403);
                }
                if ($existingRole['society_id'] === null && $actorRoleId !== 1) {
                    $conn->rollBack();
                    jsonError('Only Super Admin can edit platform template roles.', 403);
                }

                $upd = $conn->prepare("UPDATE sms_admin_roles SET role_name = :name, description = :desc WHERE id = :id");
                $upd->execute([':name' => $roleName, ':desc' => $description, ':id' => $roleId]);

                $del = $conn->prepare("DELETE FROM sms_role_permissions WHERE admin_role_id = :id");
                $del->execute([':id' => $roleId]);
            }

            // Insert permissions
            if (!empty($permKeys)) {
                $pMap = $conn->query("SELECT perm_key, id FROM sms_permissions")->fetchAll(PDO::FETCH_KEY_PAIR);
                $rpIns = $conn->prepare("INSERT IGNORE INTO sms_role_permissions (admin_role_id, permission_id) VALUES (:rid, :pid)");
                foreach ($permKeys as $pk) {
                    if (isset($pMap[$pk])) {
                        $rpIns->execute([':rid' => $roleId, ':pid' => (int)$pMap[$pk]]);
                    }
                }
            }

            $conn->commit();
            logAudit($conn, $societyId, $actorId, strtoupper($action), 'admin_role', $roleId, ['role_name' => $roleName, 'perm_count' => count($permKeys)]);
            jsonSuccess(['message' => 'Role saved successfully.', 'role_id' => $roleId]);
        } elseif ($action === 'toggle_role') {
            $roleId = (int)($data['role_id'] ?? 0);
            $chk = $conn->prepare("SELECT id, society_id, role_slug, is_active FROM sms_admin_roles WHERE id = :id LIMIT 1");
            $chk->execute([':id' => $roleId]);
            $role = $chk->fetch();
            if (!$role || $role['role_slug'] === 'super_admin' || ($role['society_id'] !== null && (int)$role['society_id'] !== $societyId)) {
                jsonError('Cannot toggle this role.', 403);
            }
            $newState = ((int)$role['is_active'] === 1) ? 0 : 1;
            $up = $conn->prepare("UPDATE sms_admin_roles SET is_active = :st WHERE id = :id");
            $up->execute([':st' => $newState, ':id' => $roleId]);
            logAudit($conn, $societyId, $actorId, 'TOGGLE_ROLE', 'admin_role', $roleId, ['is_active' => $newState]);
            jsonSuccess(['message' => $newState ? 'Role enabled.' : 'Role disabled.', 'is_active' => $newState]);
        } elseif ($action === 'assign_user_role') {
            $targetUserId = (int)($data['user_id'] ?? 0);
            $adminRoleId = (int)($data['admin_role_id'] ?? 0);
            if ($targetUserId <= 0 || $adminRoleId <= 0) {
                jsonError('User and role are required.', 400);
            }
            // Verify target belongs to actor society & is not role_id=1 unless platform admin
            $uChk = $conn->prepare("SELECT id, role_id FROM sms_users WHERE id = :uid AND society_id = :sid LIMIT 1");
            $uChk->execute([':uid' => $targetUserId, ':sid' => $societyId]);
            $targetUser = $uChk->fetch();
            if (!$targetUser) {
                jsonError('Target admin user not found in this society.', 404);
            }
            if ((int)$targetUser['role_id'] === 1 && !isPlatformSuperAdmin()) {
                jsonError('Cannot reassign Super Admin privileges.', 403);
            }

            // Check permissions of assigned role to block escalation
            $rpStmt = $conn->prepare("SELECT p.perm_key FROM sms_role_permissions rp INNER JOIN sms_permissions p ON rp.permission_id = p.id WHERE rp.admin_role_id = :rid");
            $rpStmt->execute([':rid' => $adminRoleId]);
            $targetPerms = $rpStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (!canActorGrantPermissions($targetPerms)) {
                jsonError('Privilege escalation blocked: target role contains permissions you do not hold.', 403);
            }

            $up = $conn->prepare(
                "INSERT INTO sms_user_admin_roles (user_id, society_id, admin_role_id, assigned_by)
                 VALUES (:uid, :sid, :rid, :by)
                 ON DUPLICATE KEY UPDATE admin_role_id = VALUES(admin_role_id), assigned_by = VALUES(assigned_by)"
            );
            $up->execute([':uid' => $targetUserId, ':sid' => $societyId, ':rid' => $adminRoleId, ':by' => $actorId]);

            // Refresh own session if editing self
            if ($targetUserId === $actorId) {
                loadUserPermissionContext($conn, $actorId, $societyId, $actorRoleId);
            }
            logAudit($conn, $societyId, $actorId, 'ASSIGN_ADMIN_ROLE', 'user', $targetUserId, ['admin_role_id' => $adminRoleId]);
            jsonSuccess(['message' => 'Admin role assigned successfully.']);
        } elseif ($action === 'create_admin') {
            if (!hasPermission('admins.manage')) {
                jsonError('Missing admins.manage permission.', 403);
            }
            $email = trim((string)($data['email'] ?? ''));
            $firstName = trim((string)($data['first_name'] ?? ''));
            $lastName = trim((string)($data['last_name'] ?? ''));
            $phone = normalizeIndianPhoneNumber($data['phone_number'] ?? '');
            $adminRoleId = (int)($data['admin_role_id'] ?? 2);

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $firstName === '' || $lastName === '') {
                jsonError('Valid name and email are required.', 400);
            }
            if ($phone === null) {
                jsonError('Please enter a valid 10-digit Indian mobile number.', 400);
            }
            if ($adminRoleId === 1 && !isPlatformSuperAdmin()) {
                jsonError('Cannot create Super Admin account.', 403);
            }

            $dupEmail = $conn->prepare("SELECT id FROM sms_users WHERE email = :e LIMIT 1");
            $dupEmail->execute([':e' => $email]);
            if ($dupEmail->fetch()) {
                jsonError('Email address is already in use.', 409);
            }

            $dupPhone = $conn->prepare("SELECT user_id FROM sms_user_profiles WHERE phone_number = :p LIMIT 1");
            $dupPhone->execute([':p' => $phone]);
            if ($dupPhone->fetch()) {
                jsonError('Phone number is already registered.', 409);
            }

            $rpStmt = $conn->prepare("SELECT p.perm_key FROM sms_role_permissions rp INNER JOIN sms_permissions p ON rp.permission_id = p.id WHERE rp.admin_role_id = :rid");
            $rpStmt->execute([':rid' => $adminRoleId]);
            $targetPerms = $rpStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (!canActorGrantPermissions($targetPerms)) {
                jsonError('Privilege escalation blocked for chosen role.', 403);
            }

            $tempPassword = 'SG@' . bin2hex(random_bytes(4)) . '!';
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

            $conn->beginTransaction();
            $uIns = $conn->prepare("INSERT INTO sms_users (society_id, email, password_hash, role_id, status) VALUES (:sid, :e, :h, 2, 'active')");
            $uIns->execute([':sid' => $societyId, ':e' => $email, ':h' => $hash]);
            $newUid = (int)$conn->lastInsertId();

            $pIns = $conn->prepare("INSERT INTO sms_user_profiles (user_id, first_name, last_name, phone_number) VALUES (:uid, :fn, :ln, :ph)");
            $pIns->execute([':uid' => $newUid, ':fn' => $firstName, ':ln' => $lastName, ':ph' => $phone]);

            $rIns = $conn->prepare("INSERT INTO sms_user_admin_roles (user_id, society_id, admin_role_id, assigned_by) VALUES (:uid, :sid, :rid, :by)");
            $rIns->execute([':uid' => $newUid, ':sid' => $societyId, ':rid' => $adminRoleId, ':by' => $actorId]);
            $conn->commit();

            logAudit($conn, $societyId, $actorId, 'CREATE_ADMIN_USER', 'user', $newUid, ['email' => $email, 'admin_role_id' => $adminRoleId]);
            jsonSuccess(['message' => 'Admin account provisioned.', 'temporary_password' => $tempPassword, 'user_id' => $newUid], 201);
        } else {
            jsonError('Unknown role action.', 400);
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Unable to update role settings.');
    }
}

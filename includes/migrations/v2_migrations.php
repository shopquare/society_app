<?php
// includes/migrations/v2_migrations.php
// Idempotent, non-destructive schema upgrades for SecureGate v2 (local v_two branch).

/**
 * Helper to check if a table exists in the current database.
 */
function v2TableExists(PDO $conn, string $table): bool {
    $stmt = $conn->query("SHOW TABLES LIKE " . $conn->quote($table));
    return $stmt ? (bool)$stmt->fetchColumn() : false;
}

/**
 * Helper to check if a column exists on a table.
 */
function v2ColumnExists(PDO $conn, string $table, string $column): bool {
    $stmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE " . $conn->quote($column));
    return (bool)$stmt->fetch();
}

/**
 * Helper to execute an ALTER statement safely ignoring duplicate/already-exists errors.
 */
function v2SafeAlter(PDO $conn, string $sql): void {
    try {
        $conn->exec($sql);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (!str_contains($msg, 'Duplicate') && !str_contains($msg, 'already exists')) {
            error_log('v2SafeAlter notice: ' . $msg . ' | SQL: ' . substr($sql, 0, 120));
        }
    }
}

/**
 * Orchestrates all v2 phase migrations safely and idempotently.
 */
function runV2Migrations(PDO $conn): void {
    migratePhase0Foundation($conn);
}

/**
 * Phase 0: Schema Drift Repairs, RBAC Permissions, Notification Infrastructure & Building Topology.
 */
function migratePhase0Foundation(PDO $conn): void {
    // 1. Legacy schema drift repairs so existing v1.x endpoints work cleanly
    if (v2TableExists($conn, 'sms_family_members') && !v2ColumnExists($conn, 'sms_family_members', 'is_emergency_contact')) {
        v2SafeAlter($conn, "ALTER TABLE sms_family_members ADD COLUMN is_emergency_contact TINYINT(1) DEFAULT 0 AFTER phone_number");
    }
    if (v2TableExists($conn, 'sms_society_facilities') && !v2ColumnExists($conn, 'sms_society_facilities', 'booking_cost_per_slot')) {
        v2SafeAlter($conn, "ALTER TABLE sms_society_facilities ADD COLUMN booking_cost_per_slot DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER capacity");
    }

    // 2. RBAC Permission & Admin Role Tables
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sms_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            perm_key VARCHAR(80) NOT NULL UNIQUE,
            module_name VARCHAR(60) NOT NULL,
            description VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_admin_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NULL,
            role_slug VARCHAR(60) NOT NULL,
            role_name VARCHAR(80) NOT NULL,
            base_role_id INT NOT NULL DEFAULT 2,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_admin_role_scope (society_id, role_slug),
            INDEX idx_admin_roles_society (society_id, is_active),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_role_permissions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            admin_role_id INT NOT NULL,
            permission_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_role_perm (admin_role_id, permission_id),
            FOREIGN KEY (admin_role_id) REFERENCES sms_admin_roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES sms_permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_user_admin_roles (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL UNIQUE,
            society_id INT NOT NULL,
            admin_role_id INT NOT NULL,
            assigned_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_admin_role_soc (society_id, admin_role_id),
            FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE,
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_role_id) REFERENCES sms_admin_roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seed Permissions Catalog
    $perms = [
        ['gates.view', 'Security', 'View gates, guard assignments, and gate-wise statistics'],
        ['gates.manage', 'Security', 'Create, edit, activate/deactivate gates and assign guards'],
        ['handovers.view', 'Security', 'View shift handover logs and unresolved incidents'],
        ['handovers.manage', 'Security', 'Start, acknowledge, resolve, and audit gate handovers'],
        ['incidents.manage', 'Security', 'Manage security incidents and SOS assignments'],
        ['polls.view', 'Community', 'View society polls and election status'],
        ['polls.manage', 'Community', 'Create polls, manage elections, close polls, and export results'],
        ['events.manage', 'Community', 'Create, edit, cancel events and manage registrations'],
        ['notices.manage', 'Community', 'Publish, schedule, pin, and target announcements with attachments'],
        ['community.moderate', 'Community', 'Moderate discussions, Lost & Found claims, and Suggestions Box'],
        ['facilities.manage', 'Facilities', 'Configure amenities, operating hours, fees, and blocked dates'],
        ['facilities.approve', 'Facilities', 'Approve, reject, or refund facility bookings'],
        ['finance.view', 'Financials', 'View invoices, dues dashboard, budgets, and reports'],
        ['finance.manage', 'Financials', 'Generate invoices, record/reconcile payments, configure penalties & income/expenses'],
        ['vendors.manage', 'Financials', 'Register vendors, record vendor invoices and payments'],
        ['budgets.manage', 'Financials', 'Create annual and category-wise society budgets'],
        ['services.manage', 'Services', 'Approve, verify, and manage local service directory listings'],
        ['ads.manage', 'Monetization', 'Manage company advertisements and monetization packages'],
        ['roles.manage', 'Governance', 'Manage custom admin roles and granular permission assignments'],
        ['admins.manage', 'Governance', 'Provision and manage committee and admin sub-accounts'],
        ['buildings.manage', 'Governance', 'Configure society towers, buildings, and flat types'],
        ['residents.manage', 'Governance', 'Verify, approve, or manage residents, tenants, and staff'],
        ['guards.manage', 'Security', 'Provision and manage security guard accounts'],
        ['notifications.broadcast', 'Communications', 'Send emergency broadcasts, view delivery logs, and retry notifications'],
        ['documents.manage', 'Digital Office', 'Upload society documents and official circulars'],
        ['documents.verify', 'Digital Office', 'Process NOC, move-in/out, tenant verification, and certificate requests'],
        ['reports.view', 'Governance', 'Generate and export automated PDF and CSV society reports'],
        ['audit.view', 'Governance', 'View immutable security and administrative audit logs'],
        ['saas.platform', 'SaaS Platform', 'Platform Super Admin access to manage societies and subscriptions']
    ];
    $pStmt = $conn->prepare("INSERT IGNORE INTO sms_permissions (perm_key, module_name, description) VALUES (:k, :m, :d)");
    foreach ($perms as $p) {
        $pStmt->execute([':k' => $p[0], ':m' => $p[1], ':d' => $p[2]]);
    }

    // Seed Default System Roles (society_id = 1 / NULL template; seeded safely with fixed IDs 1..6)
    $roles = [
        [1, null, 'super_admin', 'Super Admin', 1, 1, 'Complete platform and society governance access'],
        [2, 1, 'society_chairman', 'Society Chairman', 2, 1, 'Society-wide management, approvals, governance, and reporting'],
        [3, 1, 'secretary', 'Secretary', 2, 1, 'Resident directory, notices, documents, community, and approvals'],
        [4, 1, 'treasurer', 'Treasurer', 2, 1, 'Maintenance billing, payments, income/expenses, vendors, and budgets'],
        [5, 1, 'security_manager', 'Security Manager', 2, 1, 'Gates, guards, gate handovers, incidents, and security reports'],
        [6, 1, 'facility_manager', 'Facility Manager', 2, 1, 'Facilities configuration, booking approvals, and schedules']
    ];
    $rStmt = $conn->prepare(
        "INSERT IGNORE INTO sms_admin_roles (id, society_id, role_slug, role_name, base_role_id, is_system, description)
         VALUES (:id, :sid, :slug, :name, :base, :sys, :desc)"
    );
    foreach ($roles as $r) {
        $rStmt->execute([
            ':id' => $r[0], ':sid' => $r[1], ':slug' => $r[2],
            ':name' => $r[3], ':base' => $r[4], ':sys' => $r[5], ':desc' => $r[6]
        ]);
    }

    // Map default role permissions cleanly if role_permissions for roles 1..6 aren't populated yet
    $permMap = $conn->query("SELECT perm_key, id FROM sms_permissions")->fetchAll(PDO::FETCH_KEY_PAIR);
    $rpStmt = $conn->prepare("INSERT IGNORE INTO sms_role_permissions (admin_role_id, permission_id) VALUES (:rid, :pid)");

    $roleTemplates = [
        1 => array_keys($permMap),
        2 => array_values(array_diff(array_keys($permMap), ['saas.platform'])),
        3 => ['residents.manage', 'notices.manage', 'documents.manage', 'documents.verify', 'polls.view', 'polls.manage', 'events.manage', 'community.moderate', 'services.manage', 'reports.view', 'notifications.broadcast'],
        4 => ['finance.view', 'finance.manage', 'vendors.manage', 'budgets.manage', 'reports.view', 'ads.manage'],
        5 => ['gates.view', 'gates.manage', 'handovers.view', 'handovers.manage', 'incidents.manage', 'guards.manage', 'reports.view', 'notifications.broadcast'],
        6 => ['facilities.manage', 'facilities.approve', 'events.manage', 'reports.view']
    ];

    // Seed once per role id if zero rows assigned yet (preserves manual admin edits afterwards)
    foreach ($roleTemplates as $roleId => $keys) {
        $cnt = (int)$conn->query("SELECT COUNT(*) FROM sms_role_permissions WHERE admin_role_id = " . (int)$roleId)->fetchColumn();
        if ($cnt === 0) {
            foreach ($keys as $k) {
                if (isset($permMap[$k])) {
                    $rpStmt->execute([':rid' => $roleId, ':pid' => (int)$permMap[$k]]);
                }
            }
        }
    }

    // Bind default Super Admin (user 1) & Committee (user 2) if unassigned
    $conn->exec("INSERT IGNORE INTO sms_user_admin_roles (user_id, society_id, admin_role_id, assigned_by)
        SELECT id, society_id, 1, 1 FROM sms_users WHERE role_id = 1 AND id = 1");
    $conn->exec("INSERT IGNORE INTO sms_user_admin_roles (user_id, society_id, admin_role_id, assigned_by)
        SELECT id, society_id, 2, 1 FROM sms_users WHERE role_id = 2 AND id = 2");

    // 3. Centralized Notification Infrastructure
    if (!v2TableExists($conn, 'sms_notifications')) {
        $conn->exec("
            CREATE TABLE sms_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NULL,
                user_id BIGINT NOT NULL,
                category VARCHAR(50) NOT NULL DEFAULT 'general',
                priority ENUM('Critical', 'High', 'Normal', 'Low') NOT NULL DEFAULT 'Normal',
                channel VARCHAR(30) NOT NULL DEFAULT 'in_app',
                title VARCHAR(150) NOT NULL,
                message TEXT NOT NULL,
                action_url VARCHAR(255) NULL,
                reference_type VARCHAR(50) NULL,
                reference_id BIGINT NULL,
                idempotency_key VARCHAR(100) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notif_user_read (user_id, is_read, created_at),
                INDEX idx_notif_society (society_id, created_at),
                UNIQUE KEY uq_notif_idempotency (user_id, idempotency_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } else {
        if (!v2ColumnExists($conn, 'sms_notifications', 'society_id')) {
            v2SafeAlter($conn, "ALTER TABLE sms_notifications ADD COLUMN society_id INT NULL AFTER id");
        }
        if (!v2ColumnExists($conn, 'sms_notifications', 'category')) {
            v2SafeAlter($conn, "ALTER TABLE sms_notifications ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'general' AFTER user_id");
        }
        if (!v2ColumnExists($conn, 'sms_notifications', 'priority')) {
            v2SafeAlter($conn, "ALTER TABLE sms_notifications ADD COLUMN priority ENUM('Critical', 'High', 'Normal', 'Low') NOT NULL DEFAULT 'Normal' AFTER category");
        }
        if (!v2ColumnExists($conn, 'sms_notifications', 'channel')) {
            v2SafeAlter($conn, "ALTER TABLE sms_notifications ADD COLUMN channel VARCHAR(30) NOT NULL DEFAULT 'in_app' AFTER priority");
        }
        if (!v2ColumnExists($conn, 'sms_notifications', 'action_url')) {
            v2SafeAlter($conn, "ALTER TABLE sms_notifications ADD COLUMN action_url VARCHAR(255) NULL AFTER message");
        }
        if (!v2ColumnExists($conn, 'sms_notifications', 'reference_type')) {
            v2SafeAlter($conn, "ALTER TABLE sms_notifications ADD COLUMN reference_type VARCHAR(50) NULL AFTER action_url, ADD COLUMN reference_id BIGINT NULL AFTER reference_type, ADD COLUMN idempotency_key VARCHAR(100) NULL AFTER reference_id");
        }
        v2SafeAlter($conn, "ALTER TABLE sms_notifications MODIFY title VARCHAR(150) NOT NULL");
        v2SafeAlter($conn, "ALTER TABLE sms_notifications ADD UNIQUE KEY uq_notif_idempotency (user_id, idempotency_key)");
        v2SafeAlter($conn, "ALTER TABLE sms_notifications ADD INDEX idx_notif_user_read (user_id, is_read, created_at)");
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS sms_notification_preferences (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            category VARCHAR(50) NOT NULL,
            in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
            push_enabled TINYINT(1) NOT NULL DEFAULT 1,
            email_enabled TINYINT(1) NOT NULL DEFAULT 1,
            whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 1,
            sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_notif_pref (user_id, category),
            FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_notification_deliveries (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NOT NULL,
            society_id INT NULL,
            user_id BIGINT NOT NULL,
            channel ENUM('in_app', 'web_push', 'android', 'whatsapp', 'sms', 'email') NOT NULL,
            priority ENUM('Critical', 'High', 'Normal', 'Low') NOT NULL DEFAULT 'Normal',
            status ENUM('Pending', 'Sent', 'Failed', 'Skipped') NOT NULL DEFAULT 'Pending',
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            provider_response VARCHAR(255) NULL,
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_delivery_queue (status, channel, scheduled_at),
            INDEX idx_delivery_notif (notification_id),
            FOREIGN KEY (notification_id) REFERENCES sms_notifications(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Buildings, Towers & Flat Types Topology
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sms_buildings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            building_name VARCHAR(80) NOT NULL,
            building_code VARCHAR(20) NOT NULL,
            total_floors INT NOT NULL DEFAULT 10,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_building_code (society_id, building_code),
            INDEX idx_buildings_soc (society_id, is_active),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_units (
            id INT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            building_id INT NOT NULL,
            unit_number VARCHAR(30) NOT NULL,
            floor_number INT NOT NULL DEFAULT 1,
            flat_type ENUM('1BHK', '2BHK', '3BHK', '4BHK', 'Penthouse', 'Studio', 'Villa', 'Commercial') NOT NULL DEFAULT '2BHK',
            area_sqft INT NOT NULL DEFAULT 1100,
            status ENUM('Occupied', 'Vacant', 'Maintenance') NOT NULL DEFAULT 'Occupied',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_society_unit_number (society_id, unit_number),
            INDEX idx_units_building (society_id, building_id, flat_type),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
            FOREIGN KEY (building_id) REFERENCES sms_buildings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seed Sunshine Residency Buildings & Existing Registered Units safely
    $conn->exec("
        INSERT IGNORE INTO sms_buildings (id, society_id, building_name, building_code, total_floors, is_active) VALUES
        (1, 1, 'Tower A', 'A', 12, 1),
        (2, 1, 'Tower B', 'B', 12, 1),
        (3, 1, 'Tower C', 'C', 10, 1);

        INSERT IGNORE INTO sms_units (society_id, building_id, unit_number, floor_number, flat_type, area_sqft, status) VALUES
        (1, 1, '101', 1, '2BHK', 1150, 'Occupied'),
        (1, 1, 'A-101', 1, '3BHK', 1450, 'Occupied'),
        (1, 1, 'A-32', 3, '2BHK', 1150, 'Occupied'),
        (1, 2, 'B-801', 8, '3BHK', 1500, 'Occupied'),
        (1, 2, 'B-Ty', 2, '2BHK', 1100, 'Occupied'),
        (1, 3, 'C-H', 4, '2BHK', 1200, 'Occupied');
    ");
}

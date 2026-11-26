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
    migratePhase1Security($conn);
    migratePhase2Community($conn);
    migratePhase3Facilities($conn);
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

/**
 * Phase 1: Multiple Gates, Guard Assignments, Gate Handover & Security Incidents.
 */
function migratePhase1Security(PDO $conn): void {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sms_gates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            gate_name VARCHAR(80) NOT NULL,
            gate_type ENUM('Main', 'Back', 'Tower', 'Service', 'Pedestrian', 'Parking', 'Delivery') NOT NULL DEFAULT 'Main',
            building_id INT NULL,
            open_time TIME NOT NULL DEFAULT '00:00:00',
            close_time TIME NOT NULL DEFAULT '23:59:59',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_society_gate_name (society_id, gate_name),
            INDEX idx_gates_society (society_id, is_active),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_gate_assignments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            gate_id INT NOT NULL,
            guard_id BIGINT NOT NULL,
            shift_name ENUM('Morning', 'Afternoon', 'Night') NOT NULL DEFAULT 'Morning',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            assigned_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_guard_gate_shift (gate_id, guard_id, shift_name),
            INDEX idx_gate_assign_guard (society_id, guard_id, is_active),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
            FOREIGN KEY (gate_id) REFERENCES sms_gates(id) ON DELETE CASCADE,
            FOREIGN KEY (guard_id) REFERENCES sms_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_gate_handovers (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            gate_id INT NOT NULL,
            outgoing_guard_id BIGINT NULL,
            incoming_guard_id BIGINT NULL,
            shift_name ENUM('Morning', 'Afternoon', 'Night') NOT NULL DEFAULT 'Morning',
            handover_type ENUM('Normal', 'Emergency') NOT NULL DEFAULT 'Normal',
            pending_visitors_count INT NOT NULL DEFAULT 0,
            pending_parcels_count INT NOT NULL DEFAULT 0,
            unresolved_incidents_count INT NOT NULL DEFAULT 0,
            shift_notes TEXT NULL,
            suspicious_activity_notes TEXT NULL,
            equipment_issues TEXT NULL,
            next_guard_instructions TEXT NULL,
            discrepancy_notes TEXT NULL,
            status ENUM('Pending_Ack', 'Acknowledged', 'Discrepancy_Reported') NOT NULL DEFAULT 'Pending_Ack',
            started_at DATETIME NOT NULL,
            acknowledged_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_handovers_gate (society_id, gate_id, status, started_at),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
            FOREIGN KEY (gate_id) REFERENCES sms_gates(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_gate_incidents (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            gate_id INT NOT NULL,
            handover_id BIGINT NULL,
            reported_by_guard_id BIGINT NOT NULL,
            incident_type VARCHAR(60) NOT NULL,
            severity ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
            title VARCHAR(150) NOT NULL,
            description TEXT NULL,
            status ENUM('Open', 'Investigating', 'Resolved') NOT NULL DEFAULT 'Open',
            resolved_by BIGINT NULL,
            resolution_notes TEXT NULL,
            created_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            INDEX idx_incidents_society (society_id, status, created_at),
            INDEX idx_incidents_gate (society_id, gate_id, status),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
            FOREIGN KEY (gate_id) REFERENCES sms_gates(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Add gate_id columns to visitors, parcels, and emergency alerts cleanly
    if (v2TableExists($conn, 'sms_visitors') && !v2ColumnExists($conn, 'sms_visitors', 'gate_id')) {
        v2SafeAlter($conn, "ALTER TABLE sms_visitors ADD COLUMN gate_id INT NULL AFTER guard_id");
        v2SafeAlter($conn, "ALTER TABLE sms_visitors ADD INDEX idx_visitors_gate_entry (society_id, gate_id, entry_time)");
    }
    if (v2TableExists($conn, 'sms_gate_parcels') && !v2ColumnExists($conn, 'sms_gate_parcels', 'gate_id')) {
        v2SafeAlter($conn, "ALTER TABLE sms_gate_parcels ADD COLUMN gate_id INT NULL AFTER received_by_guard_id");
    }
    if (v2TableExists($conn, 'sms_emergency_alerts') && !v2ColumnExists($conn, 'sms_emergency_alerts', 'gate_id')) {
        v2SafeAlter($conn, "ALTER TABLE sms_emergency_alerts ADD COLUMN gate_id INT NULL AFTER unit_number");
    }

    // Seed default gates and assign default guard (User #4) cleanly
    $conn->exec("
        INSERT IGNORE INTO sms_gates (id, society_id, gate_name, gate_type, building_id, open_time, close_time, is_active) VALUES
        (1, 1, 'Main Gate', 'Main', NULL, '00:00:00', '23:59:59', 1),
        (2, 1, 'Delivery & Service Gate', 'Delivery', NULL, '06:00:00', '22:00:00', 1),
        (3, 1, 'Tower A Entrance', 'Tower', 1, '00:00:00', '23:59:59', 1),
        (4, 1, 'Parking Gate', 'Parking', NULL, '00:00:00', '23:59:59', 1);

        INSERT IGNORE INTO sms_gate_assignments (society_id, gate_id, guard_id, shift_name, is_active, assigned_by)
        SELECT 1, 1, id, 'Morning', 1, 1 FROM sms_users WHERE id = 4 AND role_id = 4;

        UPDATE sms_visitors SET gate_id = 1 WHERE society_id = 1 AND gate_id IS NULL;
    ");
}

/**
 * Phase 2: Community Management (Polls, Events, Lost & Found, Notices Upgrade, Suggestions, Discussions, Directory Privacy).
 */
function migratePhase2Community(PDO $conn): void {
    // 1. Polls & Confidential Voting Tables
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sms_polls (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            created_by BIGINT NOT NULL,
            title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            poll_type ENUM('General Survey', 'Society Improvement', 'Facility Preference', 'Festival Planning', 'Society Election', 'Committee Decision') NOT NULL DEFAULT 'General Survey',
            eligible_voters ENUM('all', 'owners', 'tenants') NOT NULL DEFAULT 'all',
            is_anonymous TINYINT(1) NOT NULL DEFAULT 1,
            results_visibility ENUM('Immediate', 'After_Close', 'Admin_Only') NOT NULL DEFAULT 'Immediate',
            is_governance_certified TINYINT(1) NOT NULL DEFAULT 0,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            status ENUM('Draft', 'Active', 'Closed') NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_polls_society (society_id, status, end_at),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_poll_options (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            poll_id BIGINT NOT NULL,
            option_text VARCHAR(200) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            FOREIGN KEY (poll_id) REFERENCES sms_polls(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- Tracks unique voter participation & eligibility without storing choice for anonymous polls
        CREATE TABLE IF NOT EXISTS sms_poll_voters (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            poll_id BIGINT NOT NULL,
            society_id INT NOT NULL,
            user_id BIGINT NOT NULL,
            voted_at DATETIME NOT NULL,
            UNIQUE KEY uq_poll_voter (poll_id, user_id),
            FOREIGN KEY (poll_id) REFERENCES sms_polls(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- Ballot box: user_id is NULL when is_anonymous = 1 to protect ballot confidentiality
        CREATE TABLE IF NOT EXISTS sms_poll_votes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            poll_id BIGINT NOT NULL,
            option_id BIGINT NOT NULL,
            user_id BIGINT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_votes_poll_opt (poll_id, option_id),
            FOREIGN KEY (poll_id) REFERENCES sms_polls(id) ON DELETE CASCADE,
            FOREIGN KEY (option_id) REFERENCES sms_poll_options(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 2. Events & Celebrations
        CREATE TABLE IF NOT EXISTS sms_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            created_by BIGINT NOT NULL,
            title VARCHAR(180) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'Community Gatherings',
            description TEXT NULL,
            poster_path VARCHAR(255) NULL,
            event_location VARCHAR(150) NOT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            registration_required TINYINT(1) NOT NULL DEFAULT 1,
            max_participants INT NOT NULL DEFAULT 100,
            is_paid TINYINT(1) NOT NULL DEFAULT 0,
            registration_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            status ENUM('Upcoming', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Upcoming',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_events_society (society_id, status, start_datetime),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_event_registrations (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            event_id BIGINT NOT NULL,
            society_id INT NOT NULL,
            user_id BIGINT NOT NULL,
            guests_count INT NOT NULL DEFAULT 1,
            payment_status ENUM('Free', 'Pending', 'Paid', 'Refunded') NOT NULL DEFAULT 'Free',
            payment_ref VARCHAR(100) NULL,
            status ENUM('Registered', 'Cancelled') NOT NULL DEFAULT 'Registered',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_event_reg (event_id, user_id),
            FOREIGN KEY (event_id) REFERENCES sms_events(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_event_photos (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            event_id BIGINT NOT NULL,
            society_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            caption VARCHAR(200) NULL,
            uploaded_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES sms_events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 3. Lost & Found
        CREATE TABLE IF NOT EXISTS sms_lost_found (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            reported_by BIGINT NOT NULL,
            report_type ENUM('Lost', 'Found') NOT NULL,
            item_name VARCHAR(150) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'Personal Item',
            description TEXT NOT NULL,
            event_date DATE NOT NULL,
            location VARCHAR(150) NOT NULL,
            photo_path VARCHAR(255) NULL,
            custody_status VARCHAR(120) NULL DEFAULT 'With Finder / Security Desk',
            contact_preference ENUM('Society_Desk', 'In_App_Claim') NOT NULL DEFAULT 'In_App_Claim',
            status ENUM('New', 'Under Review', 'Claimed', 'Returned', 'Closed') NOT NULL DEFAULT 'New',
            reviewed_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lf_society (society_id, status, created_at),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_lost_found_claims (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            item_id BIGINT NOT NULL,
            society_id INT NOT NULL,
            claimant_id BIGINT NOT NULL,
            proof_details TEXT NOT NULL,
            status ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
            verified_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES sms_lost_found(id) ON DELETE CASCADE,
            FOREIGN KEY (claimant_id) REFERENCES sms_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 4. Notice Board View Stats
        CREATE TABLE IF NOT EXISTS sms_notice_views (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            notice_id INT NOT NULL,
            user_id BIGINT NOT NULL,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_notice_view (notice_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 5. Suggestions Box
        CREATE TABLE IF NOT EXISTS sms_suggestions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            resident_id BIGINT NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'Society Improvement',
            title VARCHAR(150) NOT NULL,
            description TEXT NOT NULL,
            attachment_path VARCHAR(255) NULL,
            is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
            assigned_to BIGINT NULL,
            internal_notes TEXT NULL,
            admin_response TEXT NULL,
            status ENUM('Submitted', 'Under Review', 'Approved', 'In Progress', 'Implemented', 'Rejected') NOT NULL DEFAULT 'Submitted',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sugg_society (society_id, status, created_at),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 6. Society Discussions & Moderation
        CREATE TABLE IF NOT EXISTS sms_discussions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            author_id BIGINT NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'General Discussion',
            title VARCHAR(180) NOT NULL,
            content TEXT NOT NULL,
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            likes_count INT NOT NULL DEFAULT 0,
            status ENUM('Active', 'Removed', 'Hidden') NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_disc_society (society_id, status, is_pinned, created_at),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_discussion_comments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            discussion_id BIGINT NOT NULL,
            society_id INT NOT NULL,
            author_id BIGINT NOT NULL,
            parent_id BIGINT NULL,
            comment_text TEXT NOT NULL,
            status ENUM('Active', 'Removed') NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_comments_disc (discussion_id, created_at),
            FOREIGN KEY (discussion_id) REFERENCES sms_discussions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_discussion_likes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            discussion_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_disc_like (discussion_id, user_id),
            FOREIGN KEY (discussion_id) REFERENCES sms_discussions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_discussion_reports (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            target_type ENUM('discussion', 'comment') NOT NULL DEFAULT 'discussion',
            target_id BIGINT NOT NULL,
            reporter_id BIGINT NOT NULL,
            reason VARCHAR(255) NOT NULL,
            status ENUM('Pending', 'Action_Taken', 'Dismissed') NOT NULL DEFAULT 'Pending',
            reviewed_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS sms_discussion_restrictions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            user_id BIGINT NOT NULL,
            reason VARCHAR(255) NULL,
            restricted_until DATETIME NULL,
            created_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_disc_ban (society_id, user_id),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        -- 7. Resident Directory Privacy Settings (Phone/Email hidden by default)
        CREATE TABLE IF NOT EXISTS sms_resident_privacy_settings (
            user_id BIGINT PRIMARY KEY,
            society_id INT NOT NULL,
            show_in_directory TINYINT(1) NOT NULL DEFAULT 1,
            show_phone TINYINT(1) NOT NULL DEFAULT 0,
            show_email TINYINT(1) NOT NULL DEFAULT 0,
            show_family TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Upgrade sms_notices columns cleanly
    if (v2TableExists($conn, 'sms_notices')) {
        if (!v2ColumnExists($conn, 'sms_notices', 'category')) {
            v2SafeAlter($conn, "ALTER TABLE sms_notices ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'General' AFTER content");
        }
        if (!v2ColumnExists($conn, 'sms_notices', 'is_important')) {
            v2SafeAlter($conn, "ALTER TABLE sms_notices ADD COLUMN is_important TINYINT(1) NOT NULL DEFAULT 0 AFTER category, ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER is_important, ADD COLUMN target_building_id INT NULL AFTER is_pinned, ADD COLUMN scheduled_at DATETIME NULL AFTER target_building_id, ADD COLUMN expires_at DATETIME NULL AFTER scheduled_at, ADD COLUMN attachment_path VARCHAR(255) NULL AFTER expires_at, ADD COLUMN attachment_type VARCHAR(30) NULL AFTER attachment_path");
        }
    }
}

/**
 * Phase 3: Facility Management, Slot Availability, Approvals & Blocked Maintenance Dates.
 */
function migratePhase3Facilities(PDO $conn): void {
    if (v2TableExists($conn, 'sms_society_facilities')) {
        if (!v2ColumnExists($conn, 'sms_society_facilities', 'facility_type')) {
            v2SafeAlter($conn, "ALTER TABLE sms_society_facilities ADD COLUMN facility_type VARCHAR(50) NOT NULL DEFAULT 'Clubhouse' AFTER facility_name, ADD COLUMN image_path VARCHAR(255) NULL AFTER description, ADD COLUMN available_days VARCHAR(60) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun' AFTER close_time, ADD COLUMN slot_duration_mins INT NOT NULL DEFAULT 60 AFTER available_days, ADD COLUMN min_booking_hours DECIMAL(4,1) NOT NULL DEFAULT 1.0 AFTER slot_duration_mins, ADD COLUMN max_booking_hours DECIMAL(4,1) NOT NULL DEFAULT 4.0 AFTER min_booking_hours, ADD COLUMN max_bookings_per_unit_month INT NOT NULL DEFAULT 4 AFTER max_booking_hours, ADD COLUMN requires_approval TINYINT(1) NOT NULL DEFAULT 0 AFTER max_bookings_per_unit_month, ADD COLUMN cancellation_hours_cutoff INT NOT NULL DEFAULT 24 AFTER requires_approval, ADD COLUMN refund_policy_pct INT NOT NULL DEFAULT 100 AFTER cancellation_hours_cutoff");
        }
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS sms_facility_blocked_dates (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            society_id INT NOT NULL,
            facility_id BIGINT NOT NULL,
            blocked_date DATE NOT NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            reason VARCHAR(200) NOT NULL DEFAULT 'Maintenance Closure',
            blocked_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_blocked_lookup (society_id, facility_id, blocked_date),
            FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
            FOREIGN KEY (facility_id) REFERENCES sms_society_facilities(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    if (v2TableExists($conn, 'sms_facility_bookings') && !v2ColumnExists($conn, 'sms_facility_bookings', 'booking_fee')) {
        v2SafeAlter($conn, "ALTER TABLE sms_facility_bookings MODIFY status ENUM('Pending_Approval', 'Confirmed', 'Rejected', 'Cancelled', 'Completed') NOT NULL DEFAULT 'Confirmed'");
        v2SafeAlter($conn, "ALTER TABLE sms_facility_bookings ADD COLUMN booking_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER notes, ADD COLUMN payment_status ENUM('Free', 'Pending', 'Paid', 'Refunded') NOT NULL DEFAULT 'Free' AFTER booking_fee, ADD COLUMN payment_ref VARCHAR(100) NULL AFTER payment_status, ADD COLUMN refund_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER payment_ref, ADD COLUMN approved_by BIGINT NULL AFTER refund_amount, ADD COLUMN cancelled_at DATETIME NULL AFTER approved_by, ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER cancelled_at");
    }

    // Ensure all 8 core facility types exist cleanly for society 1
    $conn->exec("
        INSERT IGNORE INTO sms_society_facilities (id, society_id, facility_name, facility_type, description, location, capacity, booking_cost_per_slot, open_time, close_time, slot_duration_mins, max_bookings_per_unit_month, requires_approval) VALUES
        (1, 1, 'Clubhouse & Lounge', 'Clubhouse', 'Executive resident lounge, indoor games and workspace', 'Ground Floor, Block A', 60, 0.00, '07:00:00', '22:00:00', 60, 6, 0),
        (2, 1, 'Fitness Gymnasium', 'Gym', 'Workout sessions with cardio, weights and strength equipment', '1st Floor, Clubhouse', 25, 0.00, '06:00:00', '22:00:00', 60, 15, 0),
        (3, 1, 'Swimming Pool', 'Swimming Pool', 'Lap pool with splash zone and lifeguard supervision', 'Central Garden Area', 30, 0.00, '06:30:00', '20:30:00', 60, 10, 0),
        (4, 1, 'Badminton & Tennis Courts', 'Sports Court', 'Floodlit wooden indoor court & outdoor tennis court', 'Sports Complex', 8, 100.00, '06:00:00', '22:00:00', 60, 6, 0),
        (5, 1, 'Grand Banquet Hall', 'Banquet Hall', 'Air-conditioned banquet hall for receptions and large celebrations', 'Clubhouse Ground Wing', 150, 2500.00, '08:00:00', '23:00:00', 240, 2, 1),
        (6, 1, 'Executive Guest Room', 'Guest Room', 'Furnished AC guest suite for visiting resident guests (per day/slot)', 'Tower B Lobby Mezzanine', 4, 1200.00, '00:00:00', '23:59:59', 720, 3, 1),
        (7, 1, 'Community Hall', 'Community Hall', 'Workshops, yoga sessions, and cultural rehearsal hall', 'Tower C Atrium', 50, 500.00, '07:00:00', '21:00:00', 120, 3, 1),
        (8, 1, 'Visitor Temp Parking Bay', 'Parking', 'Reserved short-term visitor parking bay near Main Gate', 'Visitor Lot P1-P8', 8, 50.00, '00:00:00', '23:59:59', 240, 8, 0);
    ");
}

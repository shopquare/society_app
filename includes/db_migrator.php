<?php
// includes/db_migrator.php
// Automated Database Migrator for SocietyHub

/**
 * Check if all core and feature tables exist in the current database.
 */
function isDatabaseInitialized(PDO $conn): bool {
    try {
        $requiredTables = [
            'sms_users',
            'sms_roles',
            'sms_societies',
            'sms_user_profiles',
            'sms_user_units',
            'sms_visitors',
            'sms_visitor_passes',
            'sms_emergency_alerts',
            'sms_delivery_preapprovals',
            'sms_persistent_tokens',
            'sms_vehicles',
            'sms_gate_parcels',
            'sms_audit_logs',
            'sms_society_facilities',
            'sms_domestic_staff',
            'sms_family_members',
            'sms_marketplace_listings',
            'sms_ad_settings',
            'sms_advertisements'
        ];
        $stmt = $conn->query("SHOW TABLES");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($requiredTables as $req) {
            if (!in_array($req, $existing, true)) {
                return false;
            }
        }
        return true;
    } catch (Throwable $e) {
        error_log('isDatabaseInitialized check failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Execute the database schema and seed script.
 * Supports both multi-query and statement-by-statement execution for maximum compatibility.
 *
 * @param PDO $conn
 * @param string|null $sqlFilePath Path to the .sql file (defaults to config/db.sql)
 * @return array ['success' => bool, 'tables' => array, 'message' => string]
 */
function runDatabaseMigration(PDO $conn, ?string $sqlFilePath = null): array {
    $filePath = $sqlFilePath ?? dirname(__DIR__) . '/config/db.sql';

    if (!is_file($filePath)) {
        return [
            'success' => false,
            'tables' => [],
            'message' => 'Migration SQL file not found: ' . basename($filePath)
        ];
    }

    $sql = @file_get_contents($filePath);
    if ($sql === false || trim($sql) === '') {
        return [
            'success' => false,
            'tables' => [],
            'message' => 'Migration SQL file is empty or unreadable.'
        ];
    }

    try {
        // Attempt multi-statement execution first
        $conn->exec($sql);
    } catch (Throwable $multiEx) {
        // Fallback: execute statement by statement
        $statements = splitSqlStatements($sql);
        foreach ($statements as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '') {
                continue;
            }
            try {
                $conn->exec($trimmed);
            } catch (Throwable $stmtEx) {
                // Ignore idempotent errors like duplicate keys or table exists
                $msg = $stmtEx->getMessage();
                if (!str_contains($msg, 'already exists') && !str_contains($msg, 'Duplicate entry')) {
                    error_log('Statement migration notice: ' . $msg . ' in statement: ' . substr($trimmed, 0, 80));
                }
            }
        }
    }

    // Verify created tables
    try {
        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        return [
            'success' => in_array('sms_users', $tables, true),
            'tables' => $tables,
            'message' => 'Database migration completed. ' . count($tables) . ' tables present.'
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'tables' => [],
            'message' => 'Failed to verify tables: ' . $e->getMessage()
        ];
    }
}

/**
 * Ensures the database is migrated automatically on demand.
 */
function ensureDatabaseMigrated(PDO $conn): bool {
    if (!isDatabaseInitialized($conn)) {
        $result = runDatabaseMigration($conn);
        if (empty($result['success'])) {
            return false;
        }
    }

    ensureRegisteredResidentsSeeded($conn);
    ensureSchemaUpgrades($conn);
    return true;
}

/**
 * Ensures all registered resident accounts (Units A-101, B-801, A-32, C-H, B-Ty)
 * exist across both local development and live production environments.
 */
function ensureRegisteredResidentsSeeded(PDO $conn): void {
    try {
        $sql = "INSERT IGNORE INTO sms_users (id, society_id, email, password_hash, role_id, status, created_at) VALUES
            (5, 1, 'yarun520@gmail.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'active', '2026-08-06 22:36:11'),
            (6, 1, 'yarun037@gmail.com', '\$2y\$10\$fCzPYbrySVLnHHFSHj7QgOCXAcv0Oh0Iw6crtklGFVEhf/TAb6OWu', 3, 'active', '2026-08-06 22:39:22'),
            (7, 1, 'bosdikee@gmail.com', '\$2y\$10\$iAiYKIlLPNt4UoXY2b6Xu.fpfo/MDWU09WutnR0/DVXfO8Zut4QE2', 3, 'active', '2026-08-06 23:58:44'),
            (8, 1, 'laundarirani@gmail.com', '\$2y\$10\$Z2uyhQTQLDEzyt6BzIm7t.Ly16ON0D39uav04mM9AxhMIPrpq7gZK', 3, 'active', '2026-08-07 00:00:10'),
            (9, 1, 'ohhyehhbaby@ohh.com', '\$2y\$10\$Z0fvr8Zg7ox58t7EVAnDVe2PIaXp7xOSREooyfNRxC97nnxiQAW3.', 3, 'active', '2026-08-07 00:01:44');

        INSERT IGNORE INTO sms_user_profiles (user_id, first_name, last_name, phone_number) VALUES
            (5, 'Arun', 'Yadav', '9026521138'),
            (6, 'Fateh', 'Singh', '+91 9119810565'),
            (7, 'Bosdiee', 'Kee', '982536'),
            (8, 'Laundri', 'Rani', '2580'),
            (9, 'BABY', '', '258');

        INSERT IGNORE INTO sms_user_units (id, society_id, user_id, unit_number, occupancy_type, move_in_date, move_out_date, is_current_occupant) VALUES
            (13, 1, 5, 'A-101', 'owner', '2026-08-06', NULL, 1),
            (14, 1, 6, 'B-801', 'tenant', '2026-08-06', '2026-10-17', 1),
            (15, 1, 7, 'A-32', 'owner', '2026-08-06', NULL, 1),
            (16, 1, 8, 'C-H', 'owner', '2026-08-06', NULL, 1),
            (17, 1, 9, 'B-Ty', 'owner', '2026-08-06', NULL, 1);

        UPDATE sms_users SET status = 'active' WHERE id IN (5, 6, 7, 8, 9) AND status != 'active';
        UPDATE sms_user_units SET is_current_occupant = 1 WHERE user_id IN (5, 6, 7, 8, 9);";

        $statements = splitSqlStatements($sql);
        foreach ($statements as $stmt) {
            $trimmed = trim($stmt);
            if ($trimmed !== '') {
                try {
                    $conn->exec($trimmed);
                } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $ex) {
        error_log('ensureRegisteredResidentsSeeded notice: ' . $ex->getMessage());
    }
}

/**
 * Automatically applies non-destructive schema upgrades, new tables, and column alterations.
 */
function ensureSchemaUpgrades(PDO $conn): void {
    try {
        // 1. Upgrade sms_visitor_passes (valid_until 48-hour expiration)
        $chkPasses = $conn->query("SHOW COLUMNS FROM sms_visitor_passes LIKE 'valid_until'")->fetch();
        if (!$chkPasses) {
            $conn->exec("ALTER TABLE sms_visitor_passes ADD COLUMN valid_until DATETIME NULL AFTER valid_date");
            $conn->exec("UPDATE sms_visitor_passes SET valid_until = DATE_ADD(created_at, INTERVAL 48 HOUR) WHERE valid_until IS NULL");
        }

        // 2. Upgrade sms_visitors (visitor_tag separation)
        $chkVisitorTag = $conn->query("SHOW COLUMNS FROM sms_visitors LIKE 'visitor_tag'")->fetch();
        if (!$chkVisitorTag) {
            $conn->exec("ALTER TABLE sms_visitors ADD COLUMN visitor_tag VARCHAR(50) NOT NULL DEFAULT 'Guest' AFTER visitor_name");
        }

        // 3. Upgrade sms_emergency_alerts (coordinates, description, personnel assignment, cancelled lifecycle)
        $chkAlertDesc = $conn->query("SHOW COLUMNS FROM sms_emergency_alerts LIKE 'description'")->fetch();
        if (!$chkAlertDesc) {
            $conn->exec("ALTER TABLE sms_emergency_alerts ADD COLUMN description TEXT NULL AFTER status");
        }
        $chkAlertCoords = $conn->query("SHOW COLUMNS FROM sms_emergency_alerts LIKE 'latitude'")->fetch();
        if (!$chkAlertCoords) {
            $conn->exec("ALTER TABLE sms_emergency_alerts ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER description, ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude");
        }
        $chkAlertAssign = $conn->query("SHOW COLUMNS FROM sms_emergency_alerts LIKE 'assigned_to'")->fetch();
        if (!$chkAlertAssign) {
            $conn->exec("ALTER TABLE sms_emergency_alerts ADD COLUMN assigned_to BIGINT NULL AFTER notes, ADD COLUMN resolved_by BIGINT NULL AFTER assigned_to, ADD COLUMN resolved_at DATETIME NULL AFTER resolved_by, ADD COLUMN cancelled_at DATETIME NULL AFTER resolved_at, ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER cancelled_at");
        }
        // Expand alert_type and status
        try {
            $conn->exec("ALTER TABLE sms_emergency_alerts MODIFY alert_type VARCHAR(60) NOT NULL DEFAULT 'General SOS'");
            $conn->exec("ALTER TABLE sms_emergency_alerts MODIFY status ENUM('Active', 'Acknowledged', 'Responding', 'Resolved', 'Cancelled') DEFAULT 'Active'");
        } catch (Throwable $e) {}

        // 4. Create New Modules Tables
        $conn->exec("
            CREATE TABLE IF NOT EXISTS sms_society_facilities (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                facility_name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                location VARCHAR(100) NULL,
                capacity INT DEFAULT 20,
                open_time TIME DEFAULT '06:00:00',
                close_time TIME DEFAULT '22:00:00',
                rules TEXT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_facilities_society (society_id, is_active),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_facility_bookings (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                facility_id BIGINT NOT NULL,
                resident_id BIGINT NOT NULL,
                booking_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                status ENUM('Confirmed', 'Cancelled') DEFAULT 'Confirmed',
                notes VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bookings_lookup (society_id, facility_id, booking_date),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
                FOREIGN KEY (facility_id) REFERENCES sms_society_facilities(id) ON DELETE CASCADE,
                FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_family_members (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                resident_id BIGINT NOT NULL,
                unit_number VARCHAR(20) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                relation VARCHAR(50) NOT NULL,
                phone_number VARCHAR(20) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_family_resident (society_id, resident_id),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
                FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_domestic_staff (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                staff_name VARCHAR(100) NOT NULL,
                staff_type VARCHAR(50) NOT NULL,
                phone_number VARCHAR(20) NULL,
                photo_path VARCHAR(255) NULL,
                pass_code VARCHAR(20) NULL,
                rating DECIMAL(2, 1) DEFAULT 4.5,
                is_verified TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_staff_society (society_id, staff_type),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_resident_staff_mapping (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                resident_id BIGINT NOT NULL,
                staff_id BIGINT NOT NULL,
                unit_number VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_resident_staff (resident_id, staff_id),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
                FOREIGN KEY (resident_id) REFERENCES sms_users(id) ON DELETE CASCADE,
                FOREIGN KEY (staff_id) REFERENCES sms_domestic_staff(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_marketplace_listings (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                seller_id BIGINT NOT NULL,
                unit_number VARCHAR(20) NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NOT NULL,
                category VARCHAR(50) NOT NULL,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                item_condition ENUM('New', 'Like New', 'Good', 'Used') NOT NULL DEFAULT 'Good',
                contact_preference ENUM('Phone', 'WhatsApp', 'In-Person') NOT NULL DEFAULT 'Phone',
                contact_number VARCHAR(20) NULL,
                status ENUM('Active', 'Sold', 'Archived') DEFAULT 'Active',
                views_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_mkt_society_status (society_id, status, category),
                INDEX idx_mkt_seller (seller_id, created_at),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
                FOREIGN KEY (seller_id) REFERENCES sms_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_marketplace_images (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                listing_id BIGINT NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                is_primary TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mkt_images_listing (listing_id),
                FOREIGN KEY (listing_id) REFERENCES sms_marketplace_listings(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_marketplace_saved (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                listing_id BIGINT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_saved (user_id, listing_id),
                FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE,
                FOREIGN KEY (listing_id) REFERENCES sms_marketplace_listings(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_marketplace_reports (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                listing_id BIGINT NOT NULL,
                reporter_id BIGINT NOT NULL,
                reason VARCHAR(255) NOT NULL,
                status ENUM('Pending', 'Reviewed', 'Dismissed') DEFAULT 'Pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reports_lookup (society_id, status),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
                FOREIGN KEY (listing_id) REFERENCES sms_marketplace_listings(id) ON DELETE CASCADE,
                FOREIGN KEY (reporter_id) REFERENCES sms_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_ad_settings (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                ads_enabled TINYINT(1) DEFAULT 1,
                adsense_publisher_id VARCHAR(100) NULL,
                adsense_slot_id VARCHAR(100) NULL,
                allow_resident_ads TINYINT(1) DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_ad_settings_society (society_id),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_advertisement_packages (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                package_name VARCHAR(100) NOT NULL,
                duration_days INT NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                description VARCHAR(255) NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_advertisements (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NULL,
                advertiser_name VARCHAR(100) NOT NULL,
                user_id BIGINT NULL,
                ad_type ENUM('Google_AdSense', 'Custom_Banner', 'Resident_Paid') NOT NULL DEFAULT 'Custom_Banner',
                headline VARCHAR(150) NOT NULL,
                body_text TEXT NULL,
                banner_image_path VARCHAR(255) NULL,
                target_url VARCHAR(255) NULL,
                display_locations VARCHAR(255) DEFAULT 'resident_dashboard,marketplace',
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status ENUM('Pending_Approval', 'Active', 'Paused', 'Expired', 'Rejected') DEFAULT 'Active',
                impressions_count INT DEFAULT 0,
                clicks_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ads_lookup (society_id, status, start_date, end_date),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS sms_advertisement_payments (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                society_id INT NOT NULL,
                ad_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                package_id BIGINT NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                payment_method ENUM('UPI_Transfer', 'Bank_Transfer', 'Cash', 'Cheque') NOT NULL,
                transaction_ref VARCHAR(100) NOT NULL,
                payment_status ENUM('Pending_Approval', 'Approved', 'Rejected') DEFAULT 'Pending_Approval',
                approved_by BIGINT NULL,
                approved_at DATETIME NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ad_payments_lookup (society_id, payment_status),
                FOREIGN KEY (society_id) REFERENCES sms_societies(id) ON DELETE CASCADE,
                FOREIGN KEY (ad_id) REFERENCES sms_advertisements(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES sms_users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 5. Seed default records if empty
        $conn->exec("
            INSERT IGNORE INTO sms_society_facilities (id, society_id, facility_name, description, location, capacity, open_time, close_time) VALUES
            (1, 1, 'Clubhouse & Banquet', 'Multi-purpose hall for community gatherings, birthday celebrations and events', 'Ground Floor, Block A', 100, '08:00:00', '23:00:00'),
            (2, 1, 'Fitness Gymnasium', 'Fully equipped gym with cardio, weights and strength equipment', '1st Floor, Clubhouse', 30, '06:00:00', '22:00:00'),
            (3, 1, 'Swimming Pool', 'Olympic standard pool with baby splash pool and certified lifeguard', 'Central Garden Area', 40, '06:30:00', '20:30:00'),
            (4, 1, 'Badminton Court', 'Indoor wooden flooring court with night lighting', 'Sports Complex', 8, '06:00:00', '22:00:00');

            INSERT IGNORE INTO sms_domestic_staff (id, society_id, staff_name, staff_type, phone_number, is_verified) VALUES
            (1, 1, 'Sunita Devi', 'Maid', '9871122334', 1),
            (2, 1, 'Rajesh Kumar', 'Driver', '9872233445', 1),
            (3, 1, 'Meena Sharma', 'Cook', '9873344556', 1),
            (4, 1, 'Kailash Nath', 'Cleaner', '9874455667', 1);

            INSERT IGNORE INTO sms_ad_settings (id, society_id, ads_enabled, allow_resident_ads) VALUES
            (1, 1, 1, 1);

            INSERT IGNORE INTO sms_advertisement_packages (id, package_name, duration_days, price, description, is_active) VALUES
            (1, 'Weekly Spotlight Banner', 7, 499.00, 'Display your business ad banner on the Resident Dashboard for 7 days', 1),
            (2, 'Monthly Premium Campaign', 30, 1499.00, 'Featured banner across Resident Dashboard, Marketplace & Notice Board for 30 days', 1),
            (3, 'Quarterly Partner Sponsorship', 90, 3999.00, 'Maximum exposure across all community pages for 3 months with priority placement', 1);
        ");

    } catch (Throwable $e) {
        error_log('ensureSchemaUpgrades notice: ' . $e->getMessage());
    }
}

/**
 * Split an SQL string into individual queries by semicolon, respecting quotes and comments.
 */
function splitSqlStatements(string $sql): array {
    $statements = [];
    $current = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

        // Comments handling
        if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            if (!$inLineComment && !$inBlockComment) {
                if ($char === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            } elseif ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }
                continue;
            } elseif ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }
        }

        // Quotes handling
        if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
            $inSingleQuote = !$inSingleQuote;
        } elseif ($char === '"' && !$inSingleQuote && !$inBacktick) {
            $inDoubleQuote = !$inDoubleQuote;
        } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
            $inBacktick = !$inBacktick;
        }

        // Statement separator
        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick && !$inLineComment && !$inBlockComment) {
            $t = trim($current);
            if ($t !== '') {
                $statements[] = $t;
            }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $t = trim($current);
    if ($t !== '') {
        $statements[] = $t;
    }

    return $statements;
}

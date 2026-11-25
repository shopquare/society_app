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
            'sms_audit_logs'
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

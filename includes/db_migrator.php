<?php
// includes/db_migrator.php
// Automated Database Migrator for SocietyHub

/**
 * Check if the core tables exist in the current database.
 */
function isDatabaseInitialized(PDO $conn): bool {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'sms_users'");
        return (bool) $stmt->fetch();
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
    if (isDatabaseInitialized($conn)) {
        return true;
    }

    $result = runDatabaseMigration($conn);
    return !empty($result['success']);
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

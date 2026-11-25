<?php
// includes/audit_logger.php
// Production-grade audit logger for enterprise security and compliance

if (!function_exists('logAudit')) {
    /**
     * Record an immutable audit log entry.
     *
     * @param PDO $conn
     * @param int $societyId
     * @param int|null $userId
     * @param string $action
     * @param string $entityType
     * @param int|null $entityId
     * @param array|string|null $details
     * @return bool
     */
    function logAudit(PDO $conn, int $societyId, ?int $userId, string $action, string $entityType, ?int $entityId = null, $details = null): bool {
        try {
            $detailsStr = is_array($details) ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string)$details;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ips[0]);
            }

            $stmt = $conn->prepare("
                INSERT INTO sms_audit_logs (society_id, user_id, action, entity_type, entity_id, details, ip_address, created_at)
                VALUES (:society_id, :user_id, :action, :entity_type, :entity_id, :details, :ip, NOW())
            ");
            return $stmt->execute([
                ':society_id' => $societyId,
                ':user_id' => $userId,
                ':action' => substr($action, 0, 100),
                ':entity_type' => substr($entityType, 0, 50),
                ':entity_id' => $entityId,
                ':details' => $detailsStr,
                ':ip' => substr($ip, 0, 45)
            ]);
        } catch (Throwable $e) {
            error_log('Audit log failure: ' . $e->getMessage());
            return false;
        }
    }
}

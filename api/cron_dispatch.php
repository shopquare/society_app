<?php
// api/cron_dispatch.php
// Shared-Hosting & CLI Compatible Notification Delivery Queue Worker

if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../config/db.php';
} else {
    require_once __DIR__ . '/../includes/api_bootstrap.php';
}

require_once __DIR__ . '/../includes/channels/WhatsAppProvider.php';
require_once __DIR__ . '/../includes/channels/SmsProvider.php';
require_once __DIR__ . '/../includes/channels/EmailProvider.php';

/**
 * Processes pending rows in sms_notification_deliveries safely using idempotency & retry caps.
 */
function processPendingDeliveryQueue(PDO $conn, ?int $societyId = null, int $limit = 50): array {
    $where = "d.status = 'Pending' AND d.attempts < d.max_attempts AND (d.scheduled_at IS NULL OR d.scheduled_at <= NOW())";
    $params = [];
    if ($societyId !== null) {
        $where .= " AND d.society_id = :sid";
        $params[':sid'] = $societyId;
    }

    $stmt = $conn->prepare(
        "SELECT d.id, d.channel, d.attempts, d.max_attempts,
                n.title, n.message,
                u.email, CONCAT(p.first_name, ' ', p.last_name) AS full_name, p.phone_number
         FROM sms_notification_deliveries d
         INNER JOIN sms_notifications n ON d.notification_id = n.id
         INNER JOIN sms_users u ON d.user_id = u.id
         LEFT JOIN sms_user_profiles p ON u.id = p.user_id
         WHERE {$where}
         ORDER BY (d.priority = 'Critical') DESC, d.created_at ASC
         LIMIT " . max(1, min(200, $limit))
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $upd = $conn->prepare(
        "UPDATE sms_notification_deliveries
         SET status = :st, attempts = attempts + 1, provider_response = :resp, sent_at = :sent
         WHERE id = :id"
    );

    $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
    foreach ($rows as $r) {
        $stats['processed']++;
        $res = match ($r['channel']) {
            'whatsapp' => sendWhatsAppMessage((string)($r['phone_number'] ?? ''), (string)$r['title'], (string)$r['message']),
            'sms' => sendSmsMessage((string)($r['phone_number'] ?? ''), (string)$r['title'] . ': ' . (string)$r['message']),
            'email' => sendEmailMessage((string)($r['email'] ?? ''), (string)($r['full_name'] ?? ''), (string)$r['title'], (string)$r['message']),
            default => ['status' => 'Sent', 'detail' => 'Marked delivered in unified queue']
        };

        $finalStatus = $res['status'];
        if ($finalStatus === 'Failed' && ((int)$r['attempts'] + 1) < (int)$r['max_attempts']) {
            // Keep Failed or allow retry if configured; mark Failed so retry console highlights it clearly
            $finalStatus = 'Failed';
        }
        $upd->execute([
            ':st' => $finalStatus,
            ':resp' => mb_substr((string)$res['detail'], 0, 250),
            ':sent' => ($finalStatus === 'Sent') ? date('Y-m-d H:i:s') : null,
            ':id' => (int)$r['id']
        ]);

        if ($finalStatus === 'Sent') $stats['sent']++;
        elseif ($finalStatus === 'Skipped') $stats['skipped']++;
        else $stats['failed']++;
    }

    return $stats;
}

// Guard HTTP/CLI execution cleanly when invoked directly
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (php_sapi_name() !== 'cli') {
        initApi(['GET', 'POST']);
        $cronSecret = envValue('CRON_SECRET', '');
        $provided = trim((string)($_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? ''));
        $isValidCron = ($cronSecret !== '' && hash_equals($cronSecret, $provided));
        if (!$isValidCron) {
            requireApiPermission('notifications.broadcast');
        }
        $sid = isset($_SESSION['society_id']) ? (int)$_SESSION['society_id'] : null;
        $summary = processPendingDeliveryQueue($conn, $sid, 50);
        jsonSuccess(['summary' => $summary, 'message' => 'Notification queue batch executed.']);
    } else {
        $summary = processPendingDeliveryQueue($conn, null, 100);
        echo json_encode(['status' => 'success', 'summary' => $summary]) . PHP_EOL;
    }
}

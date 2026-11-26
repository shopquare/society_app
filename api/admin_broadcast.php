<?php
// api/admin_broadcast.php
// Emergency Broadcast Composer, Multi-Channel Delivery Telemetry & Queue Retry API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/notification_service.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/cron_dispatch.php';

initApi(['GET', 'POST']);
requireApiPermission('notifications.broadcast');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sumStmt = $conn->prepare(
            "SELECT status, COUNT(*) AS cnt
             FROM sms_notification_deliveries
             WHERE society_id = :sid
             GROUP BY status"
        );
        $sumStmt->execute([':sid' => $societyId]);
        $counts = ['Pending' => 0, 'Sent' => 0, 'Failed' => 0, 'Skipped' => 0];
        foreach ($sumStmt->fetchAll() as $row) {
            $counts[$row['status']] = (int)$row['cnt'];
        }

        $logsStmt = $conn->prepare(
            "SELECT d.*, n.title, n.category, CONCAT(p.first_name, ' ', p.last_name) AS recipient_name
             FROM sms_notification_deliveries d
             INNER JOIN sms_notifications n ON d.notification_id = n.id
             LEFT JOIN sms_user_profiles p ON d.user_id = p.user_id
             WHERE d.society_id = :sid
             ORDER BY FIELD(d.status, 'Failed', 'Pending', 'Skipped', 'Sent'), d.created_at DESC
             LIMIT 80"
        );
        $logsStmt->execute([':sid' => $societyId]);

        jsonSuccess(['summary' => $counts, 'deliveries' => $logsStmt->fetchAll()]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load notification delivery monitor.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'send_broadcast') {
            $title = trim((string)($data['title'] ?? ''));
            $message = trim((string)($data['message'] ?? ''));
            $audience = trim((string)($data['audience'] ?? 'all'));
            $isEmergency = !empty($data['is_emergency']);
            $channels = is_array($data['channels'] ?? null) && !empty($data['channels'])
                ? array_values(array_intersect($data['channels'], ['in_app', 'web_push', 'android', 'whatsapp', 'sms', 'email']))
                : ['in_app', 'web_push', 'android'];
            $sched = trim((string)($data['scheduled_at'] ?? ''));

            if ($title === '' || $message === '') {
                jsonError('Broadcast title and message are required.', 400);
            }

            $roleIds = match ($audience) {
                'residents' => [3],
                'guards' => [4],
                'committee' => [1, 2],
                default => [1, 2, 3, 4]
            };

            $idemKey = 'bcast_' . date('YmdHis') . '_' . substr(md5($title . $message . $audience), 0, 8);
            $res = dispatchNotification($conn, [
                'society_id' => $societyId,
                'role_ids' => $roleIds,
                'category' => $isEmergency ? 'emergency_broadcast' : 'society_notice',
                'priority' => $isEmergency ? 'Critical' : trim((string)($data['priority'] ?? 'High')),
                'title' => $isEmergency ? ('🚨 EMERGENCY: ' . $title) : $title,
                'message' => $message,
                'action_url' => $isEmergency ? '/resident/dashboard' : '/resident/community',
                'idempotency_key' => $idemKey,
                'channels' => $channels,
                'scheduled_at' => $sched !== '' ? str_replace('T', ' ', $sched) . ':00' : null
            ]);

            logAudit($conn, $societyId, $actorId, $isEmergency ? 'EMERGENCY_BROADCAST' : 'SOCIETY_BROADCAST', 'notification', null, ['audience' => $audience, 'dispatched' => $res['dispatched']]);
            jsonSuccess([
                'message' => "Broadcast dispatched to {$res['dispatched']} recipient(s) cleanly.",
                'stats' => $res
            ], 201);
        } elseif ($action === 'run_queue_now') {
            $summary = processPendingDeliveryQueue($conn, $societyId, 60);
            jsonSuccess(['message' => "Queue processed {$summary['processed']} delivery item(s).", 'summary' => $summary]);
        } elseif ($action === 'retry_delivery') {
            $did = (int)($data['delivery_id'] ?? 0);
            if ($did <= 0) jsonError('Delivery ID required.', 400);
            $conn->prepare("UPDATE sms_notification_deliveries SET status = 'Pending', attempts = 0, scheduled_at = NULL WHERE id = :id AND society_id = :sid")
                ->execute([':id' => $did, ':sid' => $societyId]);
            $summary = processPendingDeliveryQueue($conn, $societyId, 5);
            jsonSuccess(['message' => 'Delivery retried cleanly.', 'summary' => $summary]);
        } else {
            jsonError('Invalid broadcast action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to complete broadcast operation.');
    }
}

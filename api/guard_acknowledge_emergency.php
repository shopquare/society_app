<?php
// /society_app/api/guard_acknowledge_emergency.php
// Manages the life cycle of emergency alerts: Acknowledged, Responding, Resolved, Cancelled

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['POST']);
requireApiAuth([1, 2, 4]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$alertId = (int)($data['alert_id'] ?? 0);
$status = trim((string)($data['status'] ?? 'Acknowledged'));
$assignedTo = isset($data['assigned_to']) && (int)$data['assigned_to'] > 0 ? (int)$data['assigned_to'] : null;
$cancellationReason = trim((string)($data['cancellation_reason'] ?? ''));

$allowedStatuses = ['Acknowledged', 'Responding', 'Resolved', 'Cancelled'];

if ($alertId <= 0 || !in_array($status, $allowedStatuses, true)) {
    jsonError('Valid alert ID and allowed status (Acknowledged, Responding, Resolved, Cancelled) are required.', 400);
}

try {
    $societyId = (int)$_SESSION['society_id'];
    $userId = (int)$_SESSION['user_id'];
    $now = (new DateTime('now'))->format('Y-m-d H:i:s');

    // Build dynamic update depending on status transition
    if ($status === 'Resolved') {
        $stmt = $conn->prepare(
            "UPDATE sms_emergency_alerts
             SET status = :status, resolved_by = :user_id, resolved_at = :now" .
             ($assignedTo !== null ? ", assigned_to = :assigned_to" : "") . "
             WHERE id = :alert_id AND society_id = :society_id"
        );
    } elseif ($status === 'Cancelled') {
        $stmt = $conn->prepare(
            "UPDATE sms_emergency_alerts
             SET status = :status, cancelled_at = :now, cancellation_reason = :reason
             WHERE id = :alert_id AND society_id = :society_id"
        );
    } else { // Acknowledged or Responding
        $stmt = $conn->prepare(
            "UPDATE sms_emergency_alerts
             SET status = :status, acknowledged_by = :user_id, acknowledged_at = :now" .
             ($assignedTo !== null ? ", assigned_to = :assigned_to" : "") . "
             WHERE id = :alert_id AND society_id = :society_id"
        );
    }

    $params = [
        ':status' => $status,
        ':alert_id' => $alertId,
        ':society_id' => $societyId
    ];

    if ($status === 'Cancelled') {
        $params[':now'] = $now;
        $params[':reason'] = $cancellationReason !== '' ? $cancellationReason : 'Cancelled by administrator / security';
    } else {
        $params[':user_id'] = $userId;
        $params[':now'] = $now;
        if ($assignedTo !== null) {
            $params[':assigned_to'] = $assignedTo;
        }
    }

    $stmt->execute($params);

    if (function_exists('logAudit')) {
        logAudit(
            $conn,
            $societyId,
            $userId,
            'UPDATE_SOS_STATUS',
            'emergency_alert',
            $alertId,
            "Updated status to {$status}" . ($assignedTo ? " (Assigned to user {$assignedTo})" : "")
        );
    }

    jsonSuccess([
        'message' => "Emergency alert updated to {$status}.",
        'status' => $status,
        'updated_at' => $now
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to update emergency alert.');
}

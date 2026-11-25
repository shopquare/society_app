<?php
// /society_app/api/guard_acknowledge_emergency.php
// Marks an emergency alert as Acknowledged or Resolved

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2, 4]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$alertId = (int)($data['alert_id'] ?? 0);
$status = trim((string)($data['status'] ?? 'Acknowledged'));

if ($alertId <= 0 || !in_array($status, ['Acknowledged', 'Resolved'], true)) {
    jsonError('Valid alert ID and status are required.', 400);
}

try {
    $societyId = (int)$_SESSION['society_id'];
    $guardId = (int)$_SESSION['user_id'];
    $now = (new DateTime())->format('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "UPDATE sms_emergency_alerts
         SET status = :status, acknowledged_by = :guard_id, acknowledged_at = :now
         WHERE id = :alert_id AND society_id = :society_id"
    );
    $stmt->execute([
        ':status' => $status,
        ':guard_id' => $guardId,
        ':now' => $now,
        ':alert_id' => $alertId,
        ':society_id' => $societyId
    ]);

    jsonSuccess(['message' => "Emergency alert updated to {$status}."]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to update emergency alert.');
}

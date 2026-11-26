<?php
// /society_app/api/resident_get_emergency_status.php
// Returns the current resident's active or recent emergency alert status

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([3]);

try {
    $residentId = (int)$_SESSION['user_id'];
    $societyId = (int)$_SESSION['society_id'];

    $stmt = $conn->prepare(
        "SELECT a.id, a.alert_type, a.status, a.description, a.notes, a.created_at,
                a.acknowledged_at, a.resolved_at, a.cancelled_at, a.cancellation_reason,
                gp.first_name AS guard_first_name, gp.last_name AS guard_last_name
         FROM sms_emergency_alerts a
         LEFT JOIN sms_user_profiles gp ON a.acknowledged_by = gp.user_id
         WHERE a.resident_id = :resident_id
           AND a.society_id = :society_id
           AND a.created_at >= NOW() - INTERVAL 24 HOUR
         ORDER BY a.created_at DESC
         LIMIT 1"
    );
    $stmt->execute([
        ':resident_id' => $residentId,
        ':society_id' => $societyId
    ]);
    $latestAlert = $stmt->fetch();

    jsonSuccess([
        'alert' => $latestAlert ?: null,
        'has_active' => $latestAlert && in_array($latestAlert['status'], ['Active', 'Acknowledged', 'Responding'], true)
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to retrieve emergency status.');
}

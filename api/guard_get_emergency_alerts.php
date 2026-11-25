<?php
// /society_app/api/guard_get_emergency_alerts.php
// Returns active emergency alerts for guards on duty

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2, 4]);

try {
    $societyId = (int)$_SESSION['society_id'];

    $stmt = $conn->prepare(
        "SELECT a.id, a.unit_number, a.alert_type, a.status, a.notes, a.created_at,
                p.first_name, p.last_name, p.phone_number
         FROM sms_emergency_alerts a
         LEFT JOIN sms_user_profiles p ON a.resident_id = p.user_id
         WHERE a.society_id = :society_id AND a.status IN ('Active', 'Acknowledged')
         ORDER BY (a.status = 'Active') DESC, a.created_at DESC LIMIT 10"
    );
    $stmt->execute([':society_id' => $societyId]);
    $alerts = $stmt->fetchAll();

    jsonSuccess([
        'alerts' => $alerts,
        'has_active' => !empty(array_filter($alerts, fn($a) => $a['status'] === 'Active'))
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to retrieve emergency alerts.');
}

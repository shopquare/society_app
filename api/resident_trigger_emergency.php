<?php
// /society_app/api/resident_trigger_emergency.php
// Triggers an immediate emergency siren alert to security guards

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$alertType = trim((string)($data['alert_type'] ?? 'General SOS'));
$notes = trim((string)($data['notes'] ?? ''));

$allowedTypes = ['Security Threat', 'Medical Emergency', 'Fire Alert', 'General SOS'];
if (!in_array($alertType, $allowedTypes, true)) {
    jsonError('Invalid emergency alert type.', 400);
}

try {
    $societyId = (int)$_SESSION['society_id'];
    $residentId = (int)$_SESSION['user_id'];

    // Retrieve resident unit
    $unitStmt = $conn->prepare("SELECT unit_number FROM sms_user_units WHERE user_id = :user_id AND society_id = :society_id AND is_current_occupant = 1 LIMIT 1");
    $unitStmt->execute([':user_id' => $residentId, ':society_id' => $societyId]);
    $unitRow = $unitStmt->fetch();
    $unitNumber = $unitRow ? $unitRow['unit_number'] : '101';

    $stmt = $conn->prepare(
        "INSERT INTO sms_emergency_alerts (society_id, resident_id, unit_number, alert_type, status, notes)
         VALUES (:society_id, :resident_id, :unit_number, :alert_type, 'Active', :notes)"
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':resident_id' => $residentId,
        ':unit_number' => $unitNumber,
        ':alert_type' => $alertType,
        ':notes' => $notes !== '' ? $notes : null
    ]);

    $alertId = (int)$conn->lastInsertId();

    jsonSuccess([
        'message' => '🚨 Emergency SOS dispatched to security guards immediately!',
        'alert_id' => $alertId,
        'alert_type' => $alertType,
        'unit_number' => $unitNumber
    ], 201);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to trigger emergency SOS.');
}

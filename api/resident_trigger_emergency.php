<?php
// /society_app/api/resident_trigger_emergency.php
// Triggers an immediate emergency siren alert to security guards and administrators

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['POST']);
requireApiAuth([3]);

$residentId = (int)$_SESSION['user_id'];
$societyId = (int)$_SESSION['society_id'];

// Prevent duplicate emergency submissions from button spamming (rate limit: max 3 attempts per 60s)
checkRateLimit('emergency_trigger_' . $residentId, 3, 60);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$alertType = trim((string)($data['alert_type'] ?? 'Security Threat'));
$description = trim((string)($data['description'] ?? ($data['notes'] ?? '')));
$latitude = isset($data['latitude']) && is_numeric($data['latitude']) ? (float)$data['latitude'] : null;
$longitude = isset($data['longitude']) && is_numeric($data['longitude']) ? (float)$data['longitude'] : null;

$allowedTypes = [
    'Medical Emergency',
    'Fire Emergency',
    'Security Threat',
    'Accident',
    'Theft / Suspicious Activity',
    'Other Emergency',
    // Backwards compatibility with previous versions
    'Fire Alert',
    'General SOS'
];

if (!in_array($alertType, $allowedTypes, true)) {
    jsonError('Invalid emergency alert type.', 400);
}

try {
    // Retrieve resident unit
    $unitStmt = $conn->prepare("SELECT unit_number FROM sms_user_units WHERE user_id = :user_id AND society_id = :society_id AND is_current_occupant = 1 LIMIT 1");
    $unitStmt->execute([':user_id' => $residentId, ':society_id' => $societyId]);
    $unitRow = $unitStmt->fetch();
    $unitNumber = $unitRow ? $unitRow['unit_number'] : '101';

    $nowIst = (new DateTime('now'))->format('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "INSERT INTO sms_emergency_alerts (society_id, resident_id, unit_number, alert_type, status, description, notes, latitude, longitude, created_at)
         VALUES (:society_id, :resident_id, :unit_number, :alert_type, 'Active', :description, :notes, :latitude, :longitude, :created_at)"
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':resident_id' => $residentId,
        ':unit_number' => $unitNumber,
        ':alert_type' => $alertType,
        ':description' => $description !== '' ? $description : null,
        ':notes' => $description !== '' ? $description : null,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':created_at' => $nowIst,
    ]);

    $alertId = (int)$conn->lastInsertId();

    // Immutable audit log entry
    if (function_exists('logAudit')) {
        logAudit(
            $conn,
            $societyId,
            $residentId,
            'TRIGGER_SOS',
            'emergency_alert',
            $alertId,
            "Type: {$alertType} from Unit {$unitNumber}. Coords: " . ($latitude !== null ? "{$latitude}, {$longitude}" : "Not provided")
        );
    }

    jsonSuccess([
        'message' => '🚨 Emergency SOS dispatched to security guards and administrators immediately!',
        'alert_id' => $alertId,
        'alert_type' => $alertType,
        'unit_number' => $unitNumber,
        'status' => 'Active',
        'created_at' => $nowIst
    ], 201);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to trigger emergency SOS.');
}
